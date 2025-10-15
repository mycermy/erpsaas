<?php

/**
 * Test Script: Verify Invoice Flagging on Insufficient Stock
 *
 * This script tests:
 * 1. Invoice flagging when overselling (quantity > available)
 * 2. Batch allocation when there's insufficient stock
 * 3. Negative stock allowed (soft block)
 * 4. Automatic unflagging when bill received
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
    echo str_repeat('-', 50) . "\n";

    $initialStock = $inventoryService->getStockQuantity($inventoryItem, $warehouse);
    echo "Available stock: {$initialStock}\n";
    echo 'Item tracks batches: ' . ($inventoryItem->track_batches ? 'YES' : 'NO') . "\n";

    // Check batches if tracked
    if ($inventoryItem->track_batches) {
        $batches = \App\Models\Inventory\InventoryBatch::where('inventory_item_id', $inventoryItem->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_date')
            ->get();

        echo "\nAvailable Batches:\n";
        foreach ($batches as $batch) {
            echo "  - Batch #{$batch->id}: {$batch->quantity_remaining} units @ RM {$batch->unit_cost} (received: {$batch->received_date})\n";
        }
    }

    echo "\n";

    // === STEP 2: Create oversold invoice ===
    echo "STEP 2: Create Invoice with Overselling\n";
    echo str_repeat('-', 50) . "\n";

    $oversellQty = $initialStock + 15; // Request 15 more than available
    echo "Requesting quantity: {$oversellQty} (available: {$initialStock})\n";
    echo "Shortage: 15 units\n\n";

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

    echo "Created Invoice #{$invoice->invoice_number}\n";
    echo "Status: Draft\n\n";

    // Add line item using relationship
    $lineItem = new DocumentLineItem([
        'company_id' => $companyId,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => $oversellQty,
        'unit_price' => 100000, // RM 1,000.00
    ]);
    $invoice->lineItems()->save($lineItem);

    $invoice->update([
        'subtotal' => $oversellQty * 100000,
        'total' => $oversellQty * 100000,
    ]);

    // Approve invoice to trigger inventory processing
    echo "Changing status to Sent (triggers inventory outbound)...\n";
    $invoice->update(['status' => InvoiceStatus::Sent]);
    $invoice->refresh();

    echo "\n";

    // === STEP 3: Verify flagging ===
    echo "STEP 3: Verify Invoice Flagging\n";
    echo str_repeat('-', 50) . "\n";

    echo 'Invoice flagged: ' . ($invoice->inventory_flagged ? 'YES ✓' : 'NO ✗') . "\n";
    if ($invoice->inventory_flagged) {
        echo "Flagged at: {$invoice->inventory_flagged_at}\n";
    }

    $stockAfterInvoice = $inventoryService->getStockQuantity($inventoryItem, $warehouse);
    echo "Stock after invoice: {$stockAfterInvoice}\n";
    echo 'Expected stock: ' . ($initialStock - $oversellQty) . "\n";
    echo 'Negative stock allowed: ' . ($stockAfterInvoice < 0 ? 'YES ✓' : 'NO') . "\n";

    // Check movements created
    $movements = \App\Models\Inventory\InventoryMovement::where('reference_type', Invoice::class)
        ->where('reference_id', $invoice->id)
        ->get();

    echo "\nMovements created: {$movements->count()}\n";
    foreach ($movements as $movement) {
        $batchInfo = $movement->batch_id ? " (Batch #{$movement->batch_id})" : ' (No batch)';
        echo "  - Movement #{$movement->id}: {$movement->quantity} units{$batchInfo}\n";
        echo "    Type: {$movement->movement_type->value}\n";
        echo '    Cost: RM ' . number_format($movement->total_cost / 100, 2) . "\n";
    }

    // Check batch status if tracked
    if ($inventoryItem->track_batches) {
        echo "\nBatches after invoice:\n";
        $batches = \App\Models\Inventory\InventoryBatch::where('inventory_item_id', $inventoryItem->id)
            ->where('warehouse_id', $warehouse->id)
            ->orderBy('received_date')
            ->get();

        foreach ($batches as $batch) {
            echo "  - Batch #{$batch->id}: {$batch->quantity_remaining} remaining (original: {$batch->original_quantity})\n";
        }
    }

    echo "\n";

    // === STEP 4: Create bill to restock ===
    echo "STEP 4: Create Bill to Replenish Stock\n";
    echo str_repeat('-', 50) . "\n";

    $restockQty = 50; // Add 50 units to cover shortage and then some
    echo "Restocking with: {$restockQty} units\n\n";

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

    echo "Created Bill #{$bill->bill_number}\n";
    echo "Status: Open\n";
    echo "Line items before save: {$bill->lineItems()->count()}\n\n";

    // Add line item using relationship
    $billLineItem = new DocumentLineItem([
        'company_id' => $companyId,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => $restockQty,
        'unit_price' => 90000, // RM 900.00
    ]);
    $bill->lineItems()->save($billLineItem);

    echo "Line items after save: {$bill->lineItems()->count()}\n";

    // Refresh and check stock
    $bill->refresh();
    $stockAfterLineItem = $inventoryService->getStockQuantity($inventoryItem, $warehouse);
    echo "Stock after adding line item: {$stockAfterLineItem}\n\n";

    $bill->update([
        'subtotal' => $restockQty * 90000,
        'total' => $restockQty * 90000,
    ]);

    // Approve bill to trigger inventory inbound
    echo "Changing status to Paid (triggers inventory inbound)...\n";
    $bill->update(['status' => BillStatus::Paid]);
    $bill->refresh();

    echo "\n";

    // === STEP 5: Verify unflagging ===
    echo "STEP 5: Verify Automatic Unflagging\n";
    echo str_repeat('-', 50) . "\n";

    $invoice->refresh();

    $stockAfterBill = $inventoryService->getStockQuantity($inventoryItem, $warehouse);
    echo "Stock after bill: {$stockAfterBill}\n";
    echo 'Expected stock: ' . ($stockAfterInvoice + $restockQty) . "\n";

    echo "\nInvoice flagged: " . ($invoice->inventory_flagged ? 'YES ✗' : 'NO ✓') . "\n";

    if (! $invoice->inventory_flagged) {
        echo "✓ Flag automatically cleared after restock!\n";
    } else {
        echo "✗ Flag still present (unexpected)\n";
    }

    // Check if stock is now sufficient
    $hasSufficient = $inventoryService->hasSufficientStock($inventoryItem, $warehouse, $oversellQty);
    echo "\nSufficient stock for invoice quantity ({$oversellQty}): " . ($hasSufficient ? 'YES ✓' : 'NO ✗') . "\n";

    DB::rollback(); // Clean up test data

    echo "\n";
    echo "=== TEST COMPLETED ===\n";
    echo "Transaction rolled back (no data persisted)\n\n";

    // === SUMMARY ===
    echo "SUMMARY OF FINDINGS:\n";
    echo str_repeat('=', 50) . "\n";
    echo '1. Overselling behavior: ' . ($stockAfterInvoice < 0 ? 'ALLOWED (soft block) ✓' : 'BLOCKED') . "\n";
    echo '2. Invoice flagging: ' . ($invoice->inventory_flagged && $stockAfterInvoice < 0 ? 'WORKING ✓' : 'NOT FLAGGED') . "\n";
    echo '3. Batch handling: ' . ($movements->count() > 0 ? 'MOVEMENTS CREATED ✓' : 'NO MOVEMENTS') . "\n";
    echo '4. Automatic unflagging: ' . (! $invoice->inventory_flagged && $stockAfterBill > 0 ? 'WORKING ✓' : 'NOT WORKING') . "\n";

} catch (\Exception $e) {
    DB::rollback();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo 'File: ' . $e->getFile() . ':' . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
