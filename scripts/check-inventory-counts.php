<?php

use App\Models\Accounting\Bill;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Invoice;
use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryMovement;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "\n=== SEEDER VERIFICATION ===\n\n";

// Count offerings and inventory items
echo "=== OFFERINGS & INVENTORY ===\n";
$offerings = \App\Models\Common\Offering::whereHas('inventoryItem')->get();
echo "Stockable Offerings: " . $offerings->count() . "\n";
foreach ($offerings as $offering) {
    echo "  - {$offering->name} (SKU: {$offering->sku})\n";
}

$inventoryItems = InventoryItem::all();
echo "Inventory Items: " . $inventoryItems->count() . "\n\n";

// Count bills and their line items
echo "=== BILLS (PURCHASES) ===\n";
$bills = Bill::all();
echo "Total Bills: " . $bills->count() . "\n";

$billLines = DocumentLineItem::where('documentable_type', Bill::class)->get();
echo "Bill Line Items: " . $billLines->count() . "\n";

foreach ($bills as $bill) {
    $lines = $bill->lineItems;
    echo "  Bill {$bill->bill_number}: {$lines->count()} line items\n";
    foreach ($lines as $line) {
        $offeringName = $line->offering ? $line->offering->name : 'N/A';
        echo "    - {$offeringName}: Qty {$line->quantity}\n";
    }
}
echo "\n";

// Count invoices and their line items
echo "=== INVOICES (SALES) ===\n";
$invoices = Invoice::all();
echo "Total Invoices: " . $invoices->count() . "\n";

$invoiceLines = DocumentLineItem::where('documentable_type', Invoice::class)->get();
echo "Invoice Line Items: " . $invoiceLines->count() . "\n";

foreach ($invoices as $invoice) {
    $lines = $invoice->lineItems;
    echo "  Invoice {$invoice->invoice_number} (Status: {$invoice->status->value}): {$lines->count()} line items\n";
    foreach ($lines as $line) {
        $offeringName = $line->offering ? $line->offering->name : 'N/A';
        echo "    - {$offeringName}: Qty {$line->quantity}\n";
    }
}
echo "\n";

// Count movements
echo "=== INVENTORY MOVEMENTS ===\n";
$movements = InventoryMovement::all();
$inboundCount = $movements->filter(fn($m) => $m->movement_type->value === 'inbound')->count();
$outboundCount = $movements->filter(fn($m) => $m->movement_type->value === 'outbound')->count();
echo "Total Movements: " . $movements->count() . "\n";
echo "Inbound Movements: {$inboundCount}\n";
echo "Outbound Movements: {$outboundCount}\n\n";

foreach ($movements as $movement) {
    $item = $movement->inventoryItem;
    $movementType = $movement->movement_type->value;
    echo "  {$movementType} - {$item->sku}: Qty {$movement->quantity} @ {$movement->unit_cost} (Total: " . ($movement->quantity * $movement->unit_cost) . ")\n";
}
echo "\n";

// Count batches
echo "=== INVENTORY BATCHES ===\n";
$batches = InventoryBatch::all();
echo "Total Batches: " . $batches->count() . "\n";

foreach ($batches as $batch) {
    $item = $batch->inventoryItem;
    echo "  {$item->sku} - Batch #{$batch->id}: Qty {$batch->current_quantity}/{$batch->original_quantity} @ {$batch->unit_cost}\n";
}
echo "\n";

// Calculate expected vs actual
echo "=== ANALYSIS ===\n";
echo "Expected:\n";
echo "  - Bills: 2 (from seeder)\n";
echo "  - Bill Lines: 2-4 items per bill = 4-8 line items\n";
echo "  - Invoices: 3 (from seeder)\n";
echo "  - Invoice Lines: 1-3 items per invoice = 3-9 line items\n";
echo "  - Inbound Movements: Should equal bill line items ({$billLines->count()})\n";
echo "  - Outbound Movements: Should equal invoice line items with status Sent/Partial/Paid\n";
echo "  - Batches: Should be created for each inbound movement\n\n";

echo "Actual:\n";
echo "  - Bills: {$bills->count()}\n";
echo "  - Bill Lines: {$billLines->count()}\n";
echo "  - Invoices: {$invoices->count()}\n";
echo "  - Invoice Lines: {$invoiceLines->count()}\n";
echo "  - Inbound Movements: " . $movements->where('movement_type', 'inbound')->count() . "\n";
echo "  - Outbound Movements: " . $movements->where('movement_type', 'outbound')->count() . "\n";
echo "  - Batches: {$batches->count()}\n\n";

// Check for discrepancies
echo "=== POTENTIAL ISSUES ===\n";
if ($billLines->count() != $movements->where('movement_type', 'inbound')->count()) {
    echo "⚠️  Bill line items ({$billLines->count()}) != Inbound movements (" . $movements->where('movement_type', 'inbound')->count() . ")\n";
    echo "    This suggests DocumentLineItemObserver may not be firing for all bill line items!\n";
}

// Count invoice lines that should create outbound movements (non-draft)
$activeInvoices = Invoice::whereIn('status', [
    \App\Enums\Accounting\InvoiceStatus::Sent,
    \App\Enums\Accounting\InvoiceStatus::Partial,
    \App\Enums\Accounting\InvoiceStatus::Paid
])->get();
$expectedOutboundLines = DocumentLineItem::where('documentable_type', Invoice::class)
    ->whereIn('documentable_id', $activeInvoices->pluck('id'))
    ->count();

if ($expectedOutboundLines != $movements->where('movement_type', 'outbound')->count()) {
    echo "⚠️  Active invoice line items ({$expectedOutboundLines}) != Outbound movements (" . $movements->where('movement_type', 'outbound')->count() . ")\n";
    echo "    This suggests InvoiceObserver may not be firing correctly!\n";
}

if ($movements->where('movement_type', 'inbound')->count() != $batches->count()) {
    echo "⚠️  Inbound movements (" . $movements->where('movement_type', 'inbound')->count() . ") != Batches ({$batches->count()})\n";
    echo "    Each inbound movement should create a batch!\n";
}

// Calculate inventory balance
echo "\n=== INVENTORY BALANCE CALCULATION ===\n";
foreach ($inventoryItems as $item) {
    $inbound = InventoryMovement::where('inventory_item_id', $item->id)
        ->where('movement_type', 'inbound')
        ->sum('quantity');

    $outbound = InventoryMovement::where('inventory_item_id', $item->id)
        ->where('movement_type', 'outbound')
        ->sum('quantity');

    $batchQty = InventoryBatch::where('inventory_item_id', $item->id)
        ->sum('current_quantity');

    $balance = $inbound - $outbound;

    echo "{$item->sku}:\n";
    echo "  Inbound: {$inbound}\n";
    echo "  Outbound: {$outbound}\n";
    echo "  Balance: {$balance}\n";
    echo "  Batch Total: {$batchQty}\n";

    if ($balance != $batchQty) {
        echo "  ⚠️  MISMATCH: Balance ({$balance}) != Batch Total ({$batchQty})\n";
    }

    // Calculate inventory value
    $batchValue = InventoryBatch::where('inventory_item_id', $item->id)
        ->get()
        ->sum(function ($batch) {
            return $batch->current_quantity * $batch->unit_cost;
        });

    echo "  Inventory Value (from batches): {$batchValue}\n\n";
}

echo "=== DONE ===\n";
