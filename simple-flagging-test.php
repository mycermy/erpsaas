<?php

/**
 * SIMPLE TEST: Verify Invoice Flagging System
 *
 * This test shows:
 * 1. What happens when you sell MORE than you have (overselling)
 * 2. Does the invoice get flagged?
 * 3. Does unflagging work when you receive new stock?
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
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║  INVENTORY FLAGGING TEST - Simple Version             ║\n";
echo "╚════════════════════════════════════════════════════════╝\n";
echo "\n";

try {
    // Setup
    $offering = Offering::where('name', 'Laptop Computer')->first();
    $inventoryItem = $offering->inventoryItem;
    $warehouse = Warehouse::where('is_default', true)->first();
    $client = Client::first();
    $vendor = Vendor::first();
    $inventoryService = app(InventoryService::class);

    // Start transaction (so we can rollback everything)
    DB::beginTransaction();

    echo "📦 INITIAL SITUATION\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $initialStock = $inventoryService->getStockQuantity($inventoryItem, $warehouse);
    echo "   Product: {$offering->name}\n";
    echo "   Available in warehouse: {$initialStock} units\n";
    echo "\n";

    // ====================
    // SCENARIO 1: OVERSELL
    // ====================
    echo "📋 SCENARIO 1: Create Invoice for MORE than we have\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    $requestedQty = $initialStock + 20; // Ask for 20 MORE than we have
    echo "   Customer wants: {$requestedQty} units\n";
    echo "   We only have: {$initialStock} units\n";
    echo "   Shortage: 20 units\n";
    echo "\n";

    // Create invoice
    $invoice = Invoice::create([
        'company_id' => $offering->company_id,
        'client_id' => $client->id,
        'invoice_number' => 'TEST-' . now()->timestamp,
        'date' => now(),
        'due_date' => now()->addDays(30),
        'status' => InvoiceStatus::Draft,
        'currency_code' => 'MYR',
        'discount_method' => \App\Enums\Accounting\DocumentDiscountMethod::PerLineItem,
        'subtotal' => $requestedQty * 100000,
        'total' => $requestedQty * 100000,
    ]);

    // Add line item
    $invoice->lineItems()->create([
        'company_id' => $offering->company_id,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => $requestedQty,
        'unit_price' => 100000,
    ]);

    echo "   ✓ Invoice created: #{$invoice->invoice_number}\n";
    echo "   Status: Draft\n";
    echo "\n";

    // Approve the invoice (this triggers inventory processing)
    echo "   → Approving invoice (changing status to Sent)...\n";
    $invoice->update(['status' => InvoiceStatus::Sent]);
    $invoice->refresh();

    echo "\n";
    echo "📊 RESULTS AFTER APPROVING INVOICE:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    // Check if invoice is flagged
    if ($invoice->inventory_flagged) {
        echo "   ✅ Invoice IS FLAGGED\n";
        echo "      Flagged at: {$invoice->inventory_flagged_at}\n";
        echo "      Reason: Not enough stock when approved\n";
    } else {
        echo "   ❌ Invoice NOT flagged (unexpected!)\n";
    }

    // Check current stock
    $stockAfterInvoice = $inventoryService->getStockQuantity($inventoryItem, $warehouse);
    echo "\n";
    echo "   Stock level now: {$stockAfterInvoice} units\n";

    if ($stockAfterInvoice < 0) {
        echo "   ✅ Negative stock allowed (soft block working)\n";
    } else {
        echo "   ❌ Stock should be negative\n";
    }

    // Show inventory movements
    $movements = \App\Models\Inventory\InventoryMovement::where('reference_type', Invoice::class)
        ->where('reference_id', $invoice->id)
        ->get();

    echo "\n";
    echo "   Inventory movements created: {$movements->count()}\n";
    foreach ($movements as $i => $movement) {
        echo '      Movement ' . ($i + 1) . ": {$movement->quantity} units\n";
    }

    echo "\n\n";

    // ====================
    // SCENARIO 2: RECEIVE STOCK
    // ====================
    echo "📦 SCENARIO 2: Receive new stock via Bill\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    $receivingQty = 100; // Receive 100 units
    echo "   Receiving: {$receivingQty} units from supplier\n";
    echo "\n";

    echo "   ⚠️  IMPORTANT: Bill status matters!\n";
    echo "      - Status 'Paid' or 'Partial' → Inventory IS processed\n";
    echo "      - Status 'Open' → Inventory NOT processed\n";
    echo "\n";

    // Test A: Bill with status "Paid" (correct way)
    echo "   TEST A: Creating bill with status 'Paid'...\n";

    $billPaid = Bill::create([
        'company_id' => $offering->company_id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'BILL-PAID-' . now()->timestamp,
        'date' => now(),
        'due_date' => now()->addDays(30),
        'status' => BillStatus::Paid, // ← IMPORTANT!
        'currency_code' => 'MYR',
        'discount_method' => \App\Enums\Accounting\DocumentDiscountMethod::PerLineItem,
        'subtotal' => $receivingQty * 90000,
        'total' => $receivingQty * 90000,
    ]);

    // Add line item (this triggers inventory processing)
    $billPaid->lineItems()->create([
        'company_id' => $offering->company_id,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => $receivingQty,
        'unit_price' => 90000,
    ]);

    // IMPORTANT: Manually trigger inventory processing (like the seeder does)
    // This is needed because BillObserver::created() runs before line items exist
    $billPaid->refresh();
    app(\App\Observers\BillObserver::class)->processInventoryInbound($billPaid);

    echo "   ✓ Bill created: #{$billPaid->bill_number}\n";
    echo "   ✓ Inventory processing manually triggered\n";
    echo "\n";

    // Check stock after bill
    $stockAfterBill = $inventoryService->getStockQuantity($inventoryItem, $warehouse);
    echo "📊 RESULTS AFTER RECEIVING STOCK:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "   Stock before bill: {$stockAfterInvoice} units\n";
    echo "   Received: {$receivingQty} units\n";
    echo "   Stock after bill: {$stockAfterBill} units\n";
    echo '   Expected: ' . ($stockAfterInvoice + $receivingQty) . " units\n";
    echo "\n";

    if ($stockAfterBill > $stockAfterInvoice) {
        echo "   ✅ Inventory inbound PROCESSED\n";
    } else {
        echo "   ❌ Inventory inbound NOT processed\n";
    }

    // Check if invoice flag was cleared
    $invoice->refresh();
    echo "\n";
    echo "   Invoice flag status:\n";
    if (! $invoice->inventory_flagged) {
        echo "   ✅ Flag CLEARED (automatic unflagging worked!)\n";
    } else {
        echo "   ⚠️  Flag still present\n";

        // Check if we have enough stock now
        $hasSufficient = $inventoryService->hasSufficientStock($inventoryItem, $warehouse, $requestedQty);
        if ($hasSufficient) {
            echo "      But we DO have enough stock now ({$stockAfterBill} >= {$requestedQty})\n";
            echo "      Flag should have been cleared\n";
        } else {
            echo "      We STILL don't have enough stock\n";
            echo "      Need: {$requestedQty} units\n";
            echo "      Have: {$stockAfterBill} units\n";
            echo '      Still short: ' . ($requestedQty - $stockAfterBill) . " units\n";
        }
    }

    echo "\n\n";

    // ====================
    // COMPARISON TEST
    // ====================
    echo "🔬 COMPARISON: What if we used status 'Open' instead?\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    $stockBefore = $inventoryService->getStockQuantity($inventoryItem, $warehouse);

    $billOpen = Bill::create([
        'company_id' => $offering->company_id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'BILL-OPEN-' . now()->timestamp,
        'date' => now(),
        'due_date' => now()->addDays(30),
        'status' => BillStatus::Open, // ← Different status
        'currency_code' => 'MYR',
        'discount_method' => \App\Enums\Accounting\DocumentDiscountMethod::PerLineItem,
        'subtotal' => 50 * 90000,
        'total' => 50 * 90000,
    ]);

    $billOpen->lineItems()->create([
        'company_id' => $offering->company_id,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => 50,
        'unit_price' => 90000,
    ]);

    $stockAfter = $inventoryService->getStockQuantity($inventoryItem, $warehouse);

    echo "   Bill with status 'Open' created\n";
    echo "   Stock before: {$stockBefore} units\n";
    echo "   Stock after: {$stockAfter} units\n";
    echo "\n";

    if ($stockAfter == $stockBefore) {
        echo "   ❌ Inventory NOT processed (status 'Open' doesn't trigger it)\n";
    } else {
        echo "   ✅ Inventory processed (unexpected!)\n";
    }

    echo "\n\n";

    // ====================
    // SUMMARY
    // ====================
    echo "╔════════════════════════════════════════════════════════╗\n";
    echo "║  SUMMARY                                               ║\n";
    echo "╚════════════════════════════════════════════════════════╝\n";
    echo "\n";

    $invoice->refresh();

    echo "1. Overselling (Invoice):\n";
    echo '   - Request more than available: ';
    echo ($requestedQty > $initialStock) ? "✅ YES\n" : "❌ NO\n";

    echo '   - Invoice flagged: ';
    echo ($invoice->inventory_flagged) ? "✅ YES\n" : "❌ NO\n";

    echo '   - Negative stock allowed: ';
    echo ($stockAfterInvoice < 0) ? "✅ YES\n" : "❌ NO\n";

    echo "\n";
    echo "2. Bill Processing:\n";
    echo "   - Bill status 'Paid' processes inventory: ";
    echo ($stockAfterBill > $stockAfterInvoice) ? "✅ YES\n" : "❌ NO\n";

    echo "   - Bill status 'Open' processes inventory: ";
    echo ($stockAfter > $stockBefore) ? "✅ YES\n" : "❌ NO\n";

    echo "\n";
    echo "3. Automatic Unflagging:\n";
    echo '   - Flag cleared after receiving stock: ';
    echo (! $invoice->inventory_flagged) ? "✅ YES\n" : "❌ NO\n";

    if ($invoice->inventory_flagged) {
        $hasSufficient = $inventoryService->hasSufficientStock($inventoryItem, $warehouse, $requestedQty);
        echo '   - Sufficient stock available now: ';
        echo ($hasSufficient) ? "✅ YES\n" : "❌ NO\n";

        if ($hasSufficient && $invoice->inventory_flagged) {
            echo "\n";
            echo "   ⚠️  NOTE: We have enough stock but flag not cleared.\n";
            echo "       This might mean the unflagging check needs adjustment.\n";
        }
    }

    echo "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✓ Test completed successfully\n";
    echo "✓ All data rolled back (nothing saved to database)\n";
    echo "\n";

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
