<?php

/**
 * COMPREHENSIVE TEST: Complete Flagging/Unflagging Cycle
 *
 * This test verifies:
 * 1. Invoice flagging when overselling
 * 2. Bill automatically processing inventory
 * 3. Automatic unflagging when sufficient stock restored
 * 4. Batch allocation during the process
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\Accounting\BillStatus;
use App\Enums\Accounting\InvoiceStatus;
use App\Models\Accounting\Bill;
use App\Models\Accounting\Invoice;
use App\Models\Common\Client;
use App\Models\Common\Offering;
use App\Models\Common\Vendor;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  COMPREHENSIVE TEST: Flagging → Unflagging Cycle              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

try {
    $offering = Offering::where('name', 'Laptop Computer')->first();
    $inventoryItem = $offering->inventoryItem;
    $warehouse = Warehouse::where('is_default', true)->first();
    $client = Client::first();
    $vendor = Vendor::first();
    $inventoryService = app(InventoryService::class);

    DB::beginTransaction();

    // ═══════════════════════════════════════════════════════════════
    // PHASE 1: OVERSELLING - FLAG SHOULD BE SET
    // ═══════════════════════════════════════════════════════════════

    echo "═══════════════════════════════════════════════════════════════\n";
    echo " PHASE 1: Overselling (Invoice Flagging)\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $initialStock = $inventoryService->getStockQuantity($inventoryItem, $warehouse);
    echo "📦 Initial stock: {$initialStock} units\n\n";

    // Create invoice that requests MORE than available
    $oversellQty = $initialStock + 15; // Request 15 more than we have
    echo "📋 Creating invoice:\n";
    echo "   Requested quantity: {$oversellQty} units\n";
    echo "   Shortage: 15 units\n\n";

    $invoice = Invoice::create([
        'company_id' => $offering->company_id,
        'client_id' => $client->id,
        'invoice_number' => 'CYCLE-TEST-' . now()->timestamp,
        'date' => now(),
        'due_date' => now()->addDays(30),
        'status' => InvoiceStatus::Draft,
        'currency_code' => 'MYR',
        'discount_method' => \App\Enums\Accounting\DocumentDiscountMethod::PerLineItem,
        'subtotal' => $oversellQty * 100000,
        'total' => $oversellQty * 100000,
    ]);

    $invoice->lineItems()->create([
        'company_id' => $offering->company_id,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => $oversellQty,
        'unit_price' => 100000,
    ]);

    echo "   Invoice #{$invoice->invoice_number} created (Draft)\n";
    echo "   Approving invoice...\n\n";

    $invoice->update(['status' => InvoiceStatus::Sent]);
    $invoice->refresh();

    $stockAfterInvoice = $inventoryService->getStockQuantity($inventoryItem, $warehouse);

    echo "✨ RESULTS:\n";
    echo "   Stock level: {$stockAfterInvoice} units\n";
    echo '   Invoice flagged: ' . ($invoice->inventory_flagged ? '✅ YES' : '❌ NO') . "\n";
    if ($invoice->inventory_flagged) {
        echo "   Flagged at: {$invoice->inventory_flagged_at}\n";
    }

    if ($invoice->inventory_flagged && $stockAfterInvoice < 0) {
        echo "\n   ✅ PHASE 1 PASSED: Invoice correctly flagged for shortage\n";
    } else {
        echo "\n   ❌ PHASE 1 FAILED\n";
    }

    echo "\n";

    // ═══════════════════════════════════════════════════════════════
    // PHASE 2: PARTIAL RESTOCK - FLAG SHOULD REMAIN
    // ═══════════════════════════════════════════════════════════════

    echo "═══════════════════════════════════════════════════════════════\n";
    echo " PHASE 2: Partial Restock (Flag Should Remain)\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $partialQty = 50; // Not enough to cover the shortage
    echo "📦 Receiving partial stock:\n";
    echo "   Receiving: {$partialQty} units\n";
    echo '   Current shortage: ' . abs($stockAfterInvoice) . " units\n";
    echo '   Still short after: ' . (abs($stockAfterInvoice) - $partialQty) . " units\n\n";

    $bill1 = Bill::create([
        'company_id' => $offering->company_id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'PARTIAL-' . now()->timestamp,
        'date' => now(),
        'due_date' => now()->addDays(30),
        'status' => BillStatus::Paid,
        'currency_code' => 'MYR',
        'discount_method' => \App\Enums\Accounting\DocumentDiscountMethod::PerLineItem,
        'subtotal' => $partialQty * 90000,
        'total' => $partialQty * 90000,
    ]);

    $bill1->lineItems()->create([
        'company_id' => $offering->company_id,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => $partialQty,
        'unit_price' => 90000,
    ]);

    $stockAfterPartial = $inventoryService->getStockQuantity($inventoryItem, $warehouse);
    $invoice->refresh();

    $hasSufficientAfterPartial = $inventoryService->hasSufficientStock($inventoryItem, $warehouse, $oversellQty);

    echo "✨ RESULTS:\n";
    echo "   Stock level: {$stockAfterPartial} units\n";
    echo "   Sufficient for invoice ({$oversellQty} units): " . ($hasSufficientAfterPartial ? 'YES' : 'NO') . "\n";
    echo '   Invoice flagged: ' . ($invoice->inventory_flagged ? '✅ YES' : '❌ NO') . "\n";

    if ($invoice->inventory_flagged && ! $hasSufficientAfterPartial) {
        echo "\n   ✅ PHASE 2 PASSED: Flag correctly remains (still insufficient stock)\n";
    } else {
        echo "\n   ⚠️  PHASE 2: Unexpected state\n";
    }

    echo "\n";

    // ═══════════════════════════════════════════════════════════════
    // PHASE 3: FULL RESTOCK - FLAG SHOULD BE CLEARED
    // ═══════════════════════════════════════════════════════════════

    echo "═══════════════════════════════════════════════════════════════\n";
    echo " PHASE 3: Full Restock (Flag Should Clear)\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $additionalQty = 100; // Enough to fully cover the invoice
    $expectedStockAfterFull = $stockAfterPartial + $additionalQty;

    echo "📦 Receiving additional stock:\n";
    echo "   Receiving: {$additionalQty} units\n";
    echo "   Current stock: {$stockAfterPartial} units\n";
    echo "   Expected after: {$expectedStockAfterFull} units\n";
    echo "   Invoice needs: {$oversellQty} units\n";
    echo '   Will be sufficient: ' . ($expectedStockAfterFull >= $oversellQty ? 'YES ✓' : 'NO ✗') . "\n\n";

    $bill2 = Bill::create([
        'company_id' => $offering->company_id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'FULL-' . now()->timestamp,
        'date' => now(),
        'due_date' => now()->addDays(30),
        'status' => BillStatus::Paid,
        'currency_code' => 'MYR',
        'discount_method' => \App\Enums\Accounting\DocumentDiscountMethod::PerLineItem,
        'subtotal' => $additionalQty * 90000,
        'total' => $additionalQty * 90000,
    ]);

    $bill2->lineItems()->create([
        'company_id' => $offering->company_id,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => $additionalQty,
        'unit_price' => 90000,
    ]);

    $stockAfterFull = $inventoryService->getStockQuantity($inventoryItem, $warehouse);

    // Check database directly (avoid potential model refresh issues in transactions)
    $flaggedInDb = DB::table('invoices')->where('id', $invoice->id)->value('inventory_flagged');
    $invoice->refresh();

    $hasSufficientAfterFull = $inventoryService->hasSufficientStock($inventoryItem, $warehouse, $oversellQty);

    echo "✨ RESULTS:\n";
    echo "   Stock level: {$stockAfterFull} units\n";
    echo "   Sufficient for invoice ({$oversellQty} units): " . ($hasSufficientAfterFull ? '✅ YES' : '❌ NO') . "\n";
    echo '   Invoice flagged (DB): ' . ($flaggedInDb ? '⚠️  YES' : '✅ NO') . "\n";
    echo '   Invoice flagged (Model): ' . ($invoice->inventory_flagged ? '⚠️  YES' : '✅ NO') . "\n";

    if (! $flaggedInDb && $hasSufficientAfterFull) {
        echo "\n   ✅ PHASE 3 PASSED: Flag automatically cleared after sufficient restock!\n";
    } elseif ($flaggedInDb && $hasSufficientAfterFull) {
        echo "\n   ⚠️  PHASE 3: We have sufficient stock but flag not cleared\n";
        echo "       This suggests the unflagging logic needs review\n";
    } elseif ($flaggedInDb && ! $hasSufficientAfterFull) {
        echo "\n   ❌ PHASE 3: Still insufficient stock\n";
        echo "       Expected: {$expectedStockAfterFull}, Got: {$stockAfterFull}\n";
    }

    echo "\n";

    // ═══════════════════════════════════════════════════════════════
    // PHASE 4: BATCH VERIFICATION
    // ═══════════════════════════════════════════════════════════════

    echo "═══════════════════════════════════════════════════════════════\n";
    echo " PHASE 4: Batch Tracking Verification\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $batches = \App\Models\Inventory\InventoryBatch::where('inventory_item_id', $inventoryItem->id)
        ->where('warehouse_id', $warehouse->id)
        ->orderBy('received_date')
        ->get();

    echo "📦 Current batches:\n\n";
    $totalInBatches = 0;
    foreach ($batches as $batch) {
        $totalInBatches += $batch->quantity_remaining;
        echo "   Batch #{$batch->id}: {$batch->batch_number}\n";
        echo "      Received: {$batch->received_date}\n";
        echo "      Original: {$batch->original_quantity} units\n";
        echo "      Remaining: {$batch->quantity_remaining} units\n";
        echo '      Unit cost: RM ' . number_format($batch->unit_cost / 100, 2) . "\n";
        echo "\n";
    }

    echo "   Total in all batches: {$totalInBatches} units\n";
    echo "   Stock level (calculated): {$stockAfterFull} units\n";

    if ($totalInBatches == $stockAfterFull) {
        echo "\n   ✅ PHASE 4 PASSED: Batch quantities match stock level\n";
    } else {
        echo "\n   ⚠️  PHASE 4: Batch total mismatch (difference: " . abs($totalInBatches - $stockAfterFull) . ")\n";
    }

    echo "\n";

    // ═══════════════════════════════════════════════════════════════
    // FINAL SUMMARY
    // ═══════════════════════════════════════════════════════════════

    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║  FINAL SUMMARY                                                 ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";

    $phase1Pass = $invoice->inventory_flagged && $stockAfterInvoice < 0;
    $phase2Pass = $invoice->inventory_flagged && ! $hasSufficientAfterPartial;
    $phase3Pass = ! $invoice->inventory_flagged && $hasSufficientAfterFull;
    $phase4Pass = $totalInBatches == $stockAfterFull;

    echo "Test Results:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo 'Phase 1 - Flagging on oversell:        ' . ($phase1Pass ? '✅ PASS' : '❌ FAIL') . "\n";
    echo 'Phase 2 - Flag remains when partial:   ' . ($phase2Pass ? '✅ PASS' : '⚠️  CHECK') . "\n";
    echo 'Phase 3 - Unflagging when sufficient:  ' . ($phase3Pass ? '✅ PASS' : '❌ FAIL') . "\n";
    echo 'Phase 4 - Batch tracking accuracy:     ' . ($phase4Pass ? '✅ PASS' : '⚠️  CHECK') . "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    $allPass = $phase1Pass && $phase2Pass && $phase3Pass && $phase4Pass;

    if ($allPass) {
        echo "\n🎉 ALL TESTS PASSED!\n";
        echo "\n✓ System is production-ready\n";
        echo "✓ Automatic flagging works\n";
        echo "✓ Automatic unflagging works\n";
        echo "✓ Batch tracking is accurate\n";
        echo "✓ No manual triggers needed\n";
    } else {
        echo "\n⚠️  SOME TESTS FAILED OR NEED REVIEW\n";

        if (! $phase1Pass) {
            echo "   - Phase 1: Flagging not working properly\n";
        }
        if (! $phase2Pass) {
            echo "   - Phase 2: Flag behavior on partial restock needs review\n";
        }
        if (! $phase3Pass) {
            echo "   - Phase 3: Automatic unflagging not working\n";
        }
        if (! $phase4Pass) {
            echo "   - Phase 4: Batch quantities don't match stock level\n";
        }
    }

    echo "\n";
    echo "Summary Statistics:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Initial stock:         {$initialStock} units\n";
    echo "Invoice quantity:      {$oversellQty} units\n";
    echo "Stock after invoice:   {$stockAfterInvoice} units\n";
    echo "After partial restock: {$stockAfterPartial} units\n";
    echo "After full restock:    {$stockAfterFull} units\n";
    echo "Total bills created:   2 bills\n";
    echo 'Total received:        ' . ($partialQty + $additionalQty) . " units\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    echo "\n✓ Test completed\n";
    echo "✓ All data rolled back (nothing saved to database)\n\n";

    DB::rollback();

} catch (\Exception $e) {
    DB::rollback();
    echo "\n";
    echo "❌ ERROR: {$e->getMessage()}\n";
    echo "   File: {$e->getFile()}:{$e->getLine()}\n";
    echo "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
