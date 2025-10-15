<?php

/**
 * SIMPLE TEST: Verify Invoice Flagging System
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
    $offering = Offering::where('name', 'Laptop Computer')->first();
        if (! $offering) {
            echo "ERROR: Offering 'Laptop Computer' not found. Seed fixtures or update offering name.\n";
            exit(1);
        }
    $inventoryItem = $offering->inventoryItem;
    $warehouse = Warehouse::where('is_default', true)->first();
    $client = Client::first();
    $vendor = Vendor::first();
    $inventoryService = app(InventoryService::class);

    DB::beginTransaction();

    echo "📦 INITIAL SITUATION\n";
    $initialStock = $inventoryService->getStockQuantity($inventoryItem, $warehouse);
    echo "   Product: {$offering->name}\n";
    echo "   Available in warehouse: {$initialStock} units\n";
    echo "\n";

    $requestedQty = $initialStock + 20; // Ask for 20 MORE than we have

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

    $invoice->lineItems()->create([
        'company_id' => $offering->company_id,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => $requestedQty,
        'unit_price' => 100000,
    ]);

    $invoice->update(['status' => InvoiceStatus::Sent]);
    $invoice->refresh();

    $stockAfterInvoice = $inventoryService->getStockQuantity($inventoryItem, $warehouse);

    if ($invoice->inventory_flagged) {
        echo "   ✓ Invoice IS FLAGGED\n";
        echo "      Reason: Not enough stock when approved\n";
    } else {
        echo "   ✗ Invoice NOT flagged (unexpected!)\n";
    }

    echo "   Stock level now: {$stockAfterInvoice} units\n";

    // Receive stock via bill (Paid)
    $receivingQty = 100;
    $billPaid = Bill::create([
        'company_id' => $offering->company_id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'BILL-PAID-' . now()->timestamp,
        'date' => now(),
        'due_date' => now()->addDays(30),
        'status' => BillStatus::Paid,
        'currency_code' => 'MYR',
        'discount_method' => \App\Enums\Accounting\DocumentDiscountMethod::PerLineItem,
        'subtotal' => $receivingQty * 90000,
        'total' => $receivingQty * 90000,
    ]);

    $billPaid->lineItems()->create([
        'company_id' => $offering->company_id,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => $receivingQty,
        'unit_price' => 90000,
    ]);

    $billPaid->refresh();
    app(\App\Observers\BillObserver::class)->processInventoryInbound($billPaid);

    $stockAfterBill = $inventoryService->getStockQuantity($inventoryItem, $warehouse);

    if (! $invoice->inventory_flagged) {
        echo "   ✅ Flag CLEARED (automatic unflagging worked!)\n";
    } else {
        echo "   ⚠️  Flag still present\n";
    }

    DB::rollback();

} catch (\Exception $e) {
} catch (\Throwable $e) {
    DB::rollback();
    echo "ERROR: {$e->getMessage()}\n";
}
