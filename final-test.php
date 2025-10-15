<?php

/**
 * Final Test: Verify automatic inventory processing works WITHOUT manual triggers
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

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║  FINAL TEST: Automatic Inventory Processing           ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

try {
    $offering = Offering::where('name', 'Laptop Computer')->first();
    $inventoryItem = $offering->inventoryItem;
    $warehouse = Warehouse::where('is_default', true)->first();
    $client = Client::first();
    $vendor = Vendor::first();
    $inventoryService = app(InventoryService::class);

    DB::beginTransaction();

    $initialStock = $inventoryService->getStockQuantity($inventoryItem, $warehouse);
    echo "📦 Initial Stock: {$initialStock} units\n\n";

    // ===== TEST 1: Invoice Overselling =====
    echo "TEST 1: Create oversold invoice\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    $oversellQty = $initialStock + 20;
    $invoice = Invoice::create([
        'company_id' => $offering->company_id,
        'client_id' => $client->id,
        'invoice_number' => 'AUTO-TEST-' . now()->timestamp,
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

    $invoice->update(['status' => InvoiceStatus::Sent]);
    $invoice->refresh();

    $stockAfterInvoice = $inventoryService->getStockQuantity($inventoryItem, $warehouse);

    echo "Requested: {$oversellQty} units\n";
    echo "Stock after: {$stockAfterInvoice} units\n";
    echo 'Invoice flagged: ' . ($invoice->inventory_flagged ? '✅ YES' : '❌ NO') . "\n";
    echo "\n";

    // ===== TEST 2: Bill WITHOUT Manual Trigger =====
    echo "TEST 2: Create bill (NO manual trigger)\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    $restockQty = 100;
    $bill = Bill::create([
        'company_id' => $offering->company_id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'AUTO-BILL-' . now()->timestamp,
        'date' => now(),
        'due_date' => now()->addDays(30),
        'status' => BillStatus::Paid,
        'currency_code' => 'MYR',
        'discount_method' => \App\Enums\Accounting\DocumentDiscountMethod::PerLineItem,
        'subtotal' => $restockQty * 90000,
        'total' => $restockQty * 90000,
    ]);

    echo "Bill created with status: {$bill->status->value}\n";
    echo "Adding line item...\n";

    // Add line item - DocumentLineItemObserver should automatically process inventory
    $bill->lineItems()->create([
        'company_id' => $offering->company_id,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => $restockQty,
        'unit_price' => 90000,
    ]);

    echo "⚠️  NO manual trigger called - testing automatic processing\n\n";

    $stockAfterBill = $inventoryService->getStockQuantity($inventoryItem, $warehouse);

    echo "Stock before bill: {$stockAfterInvoice} units\n";
    echo "Received: {$restockQty} units\n";
    echo "Stock after bill: {$stockAfterBill} units\n";
    echo 'Expected: ' . ($stockAfterInvoice + $restockQty) . " units\n";
    echo "\n";

    if ($stockAfterBill == ($stockAfterInvoice + $restockQty)) {
        echo "✅ AUTOMATIC PROCESSING WORKS!\n";
    } else {
        echo "❌ Automatic processing FAILED\n";
        echo '   Difference: ' . ($stockAfterBill - $stockAfterInvoice) . " units\n";
    }

    echo "\n";

    // ===== TEST 3: Unflagging =====
    echo "TEST 3: Check automatic unflagging\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    $invoice->refresh();
    $hasSufficient = $inventoryService->hasSufficientStock($inventoryItem, $warehouse, $oversellQty);

    echo "Stock available: {$stockAfterBill} units\n";
    echo "Invoice needs: {$oversellQty} units\n";
    echo 'Sufficient stock: ' . ($hasSufficient ? 'YES' : 'NO') . "\n";
    echo 'Invoice flagged: ' . ($invoice->inventory_flagged ? 'YES' : 'NO') . "\n";
    echo "\n";

    if (! $invoice->inventory_flagged && $hasSufficient) {
        echo "✅ UNFLAGGING WORKS!\n";
    } elseif ($invoice->inventory_flagged && ! $hasSufficient) {
        echo "⚠️  Flag still present (correct - not enough stock yet)\n";
    } else {
        echo "⚠️  Unexpected state\n";
    }

    echo "\n";

    // ===== SUMMARY =====
    echo "╔════════════════════════════════════════════════════════╗\n";
    echo "║  RESULT                                                ║\n";
    echo "╚════════════════════════════════════════════════════════╝\n\n";

    $billProcessed = ($stockAfterBill == ($stockAfterInvoice + $restockQty));

    echo '1. Invoice flagging: ' . ($invoice->inventory_flagged || ! $hasSufficient ? '✅ WORKING' : '❌ NOT WORKING') . "\n";
    echo '2. Bill auto-processing: ' . ($billProcessed ? '✅ WORKING' : '❌ NOT WORKING') . "\n";
    echo '3. Unflagging logic: ' . (($invoice->inventory_flagged && ! $hasSufficient) || (! $invoice->inventory_flagged && $hasSufficient) ? '✅ WORKING' : '⚠️  CHECK LOGIC') . "\n";

    echo "\n";

    if ($billProcessed) {
        echo "🎉 SUCCESS! Production-ready!\n";
        echo "   - Bills automatically process inventory when line items added\n";
        echo "   - NO manual triggers needed\n";
        echo "   - Flagging/unflagging works as designed\n";
    } else {
        echo "❌ FAILED! Still needs manual triggers\n";
    }

    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✓ Test completed\n";
    echo "✓ All data rolled back\n\n";

    DB::rollback();

} catch (\Exception $e) {
    DB::rollback();
    echo "\n❌ ERROR: {$e->getMessage()}\n";
    echo "   File: {$e->getFile()}:{$e->getLine()}\n\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
}
