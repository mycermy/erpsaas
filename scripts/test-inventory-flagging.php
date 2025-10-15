<?php

/**
 * Test Script: Verify Invoice Flagging on Insufficient Stock
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\Accounting\BillStatus;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\InvoiceStatus;
use App\Models\Accounting\Bill;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Invoice;
use App\Models\Common\Client;
use App\Models\Common\Offering;
use App\Models\Common\Vendor;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

echo "=== INVENTORY FLAGGING TEST ===\n\n";

try {
    // Get test data
    $offering = Offering::where('name', 'Laptop Computer')->first();
    if (! $offering) {
        exit("ERROR: Laptop Computer offering not found\n");
    }

    $inventoryItem = $offering->inventoryItem;
    if (! $inventoryItem) {
        exit("ERROR: No inventory item linked to offering\n");
    }

    $warehouse = Warehouse::where('is_default', true)->first();
    if (! $warehouse) {
        exit("ERROR: No default warehouse found\n");
    }

    $client = Client::first();
    if (! $client) {
        exit("ERROR: No client found\n");
    }
    $vendor = Vendor::first();
    if (! $vendor) {
        exit("ERROR: No vendor found\n");
    }

    $inventoryService = app(InventoryService::class);
    $companyId = $offering->company_id;

    // === STEP 1: Check initial state ===
    echo "STEP 1: Initial State\n";
    $initialStock = $inventoryService->getStockQuantity($inventoryItem, $warehouse);
    echo "Available stock: {$initialStock}\n";
    echo 'Item tracks batches: ' . ($inventoryItem->track_batches ? 'YES' : 'NO') . "\n";

    // === STEP 2: Create oversold invoice ===
    $oversellQty = $initialStock + 15; // Request 15 more than available
    DB::beginTransaction();

    $invoice = Invoice::create([
        'company_id' => $companyId,
        'client_id' => $client->id,
        'invoice_number' => 'TEST-OVERSOLD-' . now()->timestamp,
        'date' => now()->subDays(1),
        'due_date' => now()->addDays(30),
        'status' => InvoiceStatus::Draft,
        'currency_code' => 'MYR',
        'discount_method' => DocumentDiscountMethod::PerLineItem,
        'subtotal' => 0,
        'total' => 0,
    ]);

    $lineItem = new DocumentLineItem([
        'company_id' => $companyId,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => $oversellQty,
        'unit_price' => 100000,
    ]);
    $invoice->lineItems()->save($lineItem);

    $invoice->update([
        'subtotal' => $oversellQty * 100000,
        'total' => $oversellQty * 100000,
    ]);

    $invoice->update(['status' => InvoiceStatus::Sent]);
    $invoice->refresh();

    echo 'Invoice flagged: ' . ($invoice->inventory_flagged ? 'YES ✓' : 'NO ✗') . "\n";

    // === STEP 4: Create bill to restock ===
    $restockQty = 50;
    $bill = Bill::create([
        'company_id' => $companyId,
        'vendor_id' => $vendor->id,
        'bill_number' => 'TEST-RESTOCK-' . now()->timestamp,
        'date' => now(),
        'due_date' => now()->addDays(30),
        'status' => BillStatus::Open,
        'currency_code' => 'MYR',
        'discount_method' => DocumentDiscountMethod::PerLineItem,
        'subtotal' => 0,
        'total' => 0,
    ]);

    $billLineItem = new DocumentLineItem([
        'company_id' => $companyId,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => $restockQty,
        'unit_price' => 90000,
    ]);
    $bill->lineItems()->save($billLineItem);

    $bill->update(['subtotal' => $restockQty * 90000, 'total' => $restockQty * 90000]);
    $bill->update(['status' => BillStatus::Paid]);
    $bill->refresh();

    $invoice->refresh();

    DB::rollback();
    echo "\n=== TEST COMPLETED ===\n";

} catch (\Exception $e) {
    DB::rollback();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
