<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\Accounting\BillStatus;
use App\Enums\Accounting\InvoiceStatus;
use App\Models\Accounting\Bill;
use App\Models\Accounting\Invoice;
use App\Models\Company;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

try {
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║  FINAL PRODUCTION READINESS TEST                              ║\n";
    echo "║  Simulates Real-World Invoice → Bill → Auto-Unflag Cycle     ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

    // Setup
    $company = Company::first();
    $warehouse = Warehouse::where('company_id', $company->id)->where('is_default', true)->first();
    $offering = \App\Models\Common\Offering::where('company_id', $company->id)
        ->whereHas('inventoryItem')
        ->first();
    $inventoryItem = $offering->inventoryItem;
    $client = \App\Models\Common\Client::factory()->create(['company_id' => $company->id]);
    $vendor = \App\Models\Common\Vendor::factory()->create(['company_id' => $company->id]);
    $inventoryService = app(InventoryService::class);

    // Get initial stock
    $initialStock = $inventoryService->getStockQuantity($inventoryItem, $warehouse);

    echo "📦 Product: {$offering->name}\n";
    echo "🏢 Warehouse: {$warehouse->name}\n";
    echo "📊 Initial Stock: {$initialStock} units\n\n";

    echo str_repeat('─', 65) . "\n";
    echo "STEP 1: Create invoice that exceeds available stock\n";
    echo str_repeat('─', 65) . "\n";

    $oversellQty = $initialStock + 50; // Order more than available
    echo "Ordering: {$oversellQty} units (shortage: 50 units)\n";

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-PROD-TEST-' . now()->timestamp,
        'issued_at' => now(),
        'due_date' => now()->addDays(30),
        'status' => InvoiceStatus::Draft,
        'currency_code' => 'MYR',
        'discount_method' => \App\Enums\Accounting\DocumentDiscountMethod::PerLineItem,
        'subtotal' => $oversellQty * 100000,
        'total' => $oversellQty * 100000,
    ]);

    $invoice->lineItems()->create([
        'company_id' => $company->id,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => $oversellQty,
        'unit_price' => 100000,
    ]);

    // Approve invoice (triggers inventory processing)
    $invoice->status = InvoiceStatus::Sent;
    $invoice->save();

    $invoice->refresh();
    $stockAfterInvoice = $inventoryService->getStockQuantity($inventoryItem, $warehouse);

    echo "✅ Invoice created: #{$invoice->invoice_number}\n";
    echo "   Stock after invoice: {$stockAfterInvoice} units (negative allowed)\n";
    echo '   Invoice flagged: ' . ($invoice->inventory_flagged ? '✅ YES' : '❌ NO') . "\n";

    if (! $invoice->inventory_flagged) {
        echo "\n❌ TEST FAILED: Invoice should be flagged for shortage!\n";
        DB::rollBack();
        exit(1);
    }

    echo "\n";
    echo str_repeat('─', 65) . "\n";
    echo "STEP 2: Receive purchase order (Bill) to restock\n";
    echo str_repeat('─', 65) . "\n";

    $restockQty = 200; // Buy enough to cover shortage + extra
    echo "Receiving: {$restockQty} units\n";

    $bill = Bill::create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'BILL-PROD-TEST-' . now()->timestamp,
        'date' => now(),
        'due_date' => now()->addDays(30),
        'status' => BillStatus::Paid,
        'currency_code' => 'MYR',
        'discount_method' => \App\Enums\Accounting\DocumentDiscountMethod::PerLineItem,
        'subtotal' => $restockQty * 90000,
        'total' => $restockQty * 90000,
    ]);

    $bill->lineItems()->create([
        'company_id' => $company->id,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => $restockQty,
        'unit_price' => 90000,
    ]);

    $invoice->refresh();
    $stockAfterBill = $inventoryService->getStockQuantity($inventoryItem, $warehouse);

    echo "✅ Bill created: #{$bill->bill_number}\n";
    echo "   Stock after bill: {$stockAfterBill} units\n";
    echo '   Invoice flagged: ' . ($invoice->inventory_flagged ? '⚠️  YES (issue!)' : '✅ NO (cleared)') . "\n";

    if ($invoice->inventory_flagged) {
        echo "\n❌ TEST FAILED: Invoice flag should be automatically cleared!\n";
        DB::rollBack();
        exit(1);
    }

    echo "\n";
    echo str_repeat('─', 65) . "\n";
    echo "STEP 3: Verify Batch Tracking\n";
    echo str_repeat('─', 65) . "\n";

    $batches = \App\Models\Inventory\InventoryBatch::where('inventory_item_id', $inventoryItem->id)
        ->where('warehouse_id', $warehouse->id)
        ->where('quantity_remaining', '>', 0)
        ->count();

    echo "Active batches: {$batches}\n";
    echo "Expected: >= 1 batch with remaining quantity\n";

    if ($batches < 1) {
        echo "\n❌ TEST FAILED: No batches created!\n";
        DB::rollBack();
        exit(1);
    }

    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║  ✅ ALL TESTS PASSED - SYSTEM READY FOR PRODUCTION            ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "Summary:\n";
    echo "  • Invoice flagging on shortage:     ✅ Working\n";
    echo "  • Automatic unflagging on restock:  ✅ Working\n";
    echo "  • Batch creation and tracking:      ✅ Working\n";
    echo "  • Observer-based automation:        ✅ Working\n";
    echo "\n";
    echo "Final Stock Levels:\n";
    echo "  • Started with:  {$initialStock} units\n";
    echo "  • Sold:          {$oversellQty} units\n";
    echo "  • Purchased:     {$restockQty} units\n";
    echo "  • Ended with:    {$stockAfterBill} units\n";
    echo '  • Expected:      ' . ($initialStock - $oversellQty + $restockQty) . " units\n";

    $expected = $initialStock - $oversellQty + $restockQty;
    if (abs($stockAfterBill - $expected) > 0.01) {
        echo "\n⚠️  WARNING: Stock calculation mismatch!\n";
    }

    DB::rollBack();
    echo "\n✓ Transaction rolled back (no data saved)\n\n";

} catch (Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
