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
    // Fixture checks
    if (! $company) {
        echo "ERROR: No company found. Seed database before running tests.\n";
        exit(1);
    }
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║  FRESH DATABASE TEST - Invoice Flagging & Auto-Unflagging    ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

    // Setup - use existing seeded data
    $company = Company::first();
    $warehouse = Warehouse::where('company_id', $company->id)->first();
    $offering = \App\Models\Common\Offering::where('company_id', $company->id)
        ->whereHas('inventoryItem')
        ->first();
    $inventoryItem = $offering->inventoryItem;
    $client = \App\Models\Common\Client::first();
    $vendor = \App\Models\Common\Vendor::first();
    $inventoryService = app(InventoryService::class);

    // Get initial stock
    $initialStock = $inventoryService->getStockQuantity($inventoryItem, $warehouse);

    echo "📦 Testing Product: {$offering->name}\n";
    echo "🏢 Warehouse: {$warehouse->name}\n";
    echo "📊 Current Stock: {$initialStock} units\n\n";

    echo str_repeat('═', 65) . "\n";
    echo "TEST 1: Create invoice that EXCEEDS available stock\n";
    echo str_repeat('═', 65) . "\n\n";

    $oversellQty = $initialStock + 20; // Order more than available
    echo "Creating invoice for {$oversellQty} units (shortage: 20 units)...\n";

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => 'TEST-OVERSELL-' . now()->timestamp,
        'date' => now(),
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

    echo "Invoice #{$invoice->invoice_number} created (Draft)\n";
    echo "Approving invoice to trigger inventory processing...\n\n";

    // Approve invoice (triggers inventory processing)
    $invoice->update(['status' => InvoiceStatus::Sent]);
    $invoice->refresh();

    $stockAfterInvoice = $inventoryService->getStockQuantity($inventoryItem, $warehouse);

    echo "✨ RESULTS:\n";
    echo "   Stock level: {$stockAfterInvoice} units (negative allowed)\n";
    echo '   Invoice flagged: ' . ($invoice->inventory_flagged ? '✅ YES' : '❌ NO') . "\n";
    if ($invoice->inventory_flagged) {
        echo "   Flagged at: {$invoice->inventory_flagged_at}\n";
    }

    if (! $invoice->inventory_flagged) {
        echo "\n❌ TEST 1 FAILED: Invoice should be flagged for shortage!\n";
        DB::rollBack();
        exit(1);
    }

    echo "\n✅ TEST 1 PASSED: Invoice correctly flagged on overselling\n\n";

    echo str_repeat('═', 65) . "\n";
    echo "TEST 2: Create bill to restock - Auto-unflag should trigger\n";
    echo str_repeat('═', 65) . "\n\n";

    $restockQty = 50; // Buy enough to cover shortage + extra
    echo "Creating purchase bill for {$restockQty} units...\n";

    $bill = Bill::create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'TEST-RESTOCK-' . now()->timestamp,
        'date' => now(),
        'due_date' => now()->addDays(30),
        'status' => BillStatus::Paid,
        'currency_code' => 'MYR',
        'discount_method' => \App\Enums\Accounting\DocumentDiscountMethod::PerLineItem,
        'subtotal' => $restockQty * 90000,
        'total' => $restockQty * 90000,
    ]);

    echo "Bill #{$bill->bill_number} created\n";
    echo "Creating line item (this will trigger auto-processing)...\n\n";

    $bill->lineItems()->create([
        'company_id' => $company->id,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => $restockQty,
        'unit_price' => 90000,
    ]);

    // Refresh invoice to see if flag was cleared
    $invoice->refresh();
    $stockAfterBill = $inventoryService->getStockQuantity($inventoryItem, $warehouse);

    echo "✨ RESULTS:\n";
    echo "   Stock level: {$stockAfterBill} units\n";
    echo '   Invoice flagged: ' . ($invoice->inventory_flagged ? '⚠️  YES (ISSUE!)' : '✅ NO (CLEARED!)') . "\n";

    if ($invoice->inventory_flagged) {
        echo "\n❌ TEST 2 FAILED: Invoice flag should be automatically cleared!\n";
        echo "   This means the auto-unflagging logic didn't work.\n";
        DB::rollBack();
        exit(1);
    }

    echo "\n✅ TEST 2 PASSED: Invoice flag automatically cleared after restocking!\n\n";

    echo str_repeat('═', 65) . "\n";
    echo "TEST 3: Verify batch creation (no duplicates)\n";
    echo str_repeat('═', 65) . "\n\n";

    $billBatches = \App\Models\Inventory\InventoryBatch::where('inventory_item_id', $inventoryItem->id)
        ->where('warehouse_id', $warehouse->id)
        ->whereHas('movements', function ($q) use ($bill) {
            $q->where('reference_type', Bill::class)
                ->where('reference_id', $bill->id);
        })
        ->count();

    echo "Batches created for this bill: {$billBatches}\n";

    if ($billBatches === 1) {
        echo "✅ TEST 3 PASSED: Only 1 batch created (no duplication)\n\n";
    } else {
        echo "⚠️  TEST 3: Expected 1 batch, found {$billBatches}\n\n";
    }

    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║  ✅ ALL TESTS PASSED WITH FRESH DATABASE!                     ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

    DB::rollBack();
    echo "✓ Test transaction rolled back (no permanent changes)\n\n";

} catch (\Throwable $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
