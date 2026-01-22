<?php

use Illuminate\Support\Facades\DB;
use App\Models\Accounting\Bill;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== BILL DETAILS ===\n\n";

$bills = Bill::with(['lineItems.offering', 'initialTransaction.journalEntries.account'])->get();

foreach ($bills as $bill) {
    echo "Bill #{$bill->id}: {$bill->bill_number}\n";
    echo "Status: {$bill->status->value}\n";
    echo "Total: {$bill->total}\n";
    echo "Line Items: " . $bill->lineItems->count() . "\n";

    foreach ($bill->lineItems as $item) {
        $offering = $item->offering;
        if (!$offering) {
            echo "  - No offering found for line item #{$item->id}\n";
            continue;
        }
        $offerable = $offering->offerable;
        $type = $offerable ? class_basename(get_class($offerable)) : 'Unknown';
        $stockable = ($offerable && method_exists($offerable, 'isStockable'))
            ? ($offerable->isStockable() ? 'YES' : 'NO')
            : 'N/A';
        echo "  - {$type}: {$offering->name}\n";
        echo "    Qty: {$item->quantity}, Price: {$item->unit_price}, Total: {$item->subtotal}\n";
        echo "    Stockable: {$stockable}\n";
    }

    if ($bill->initialTransaction) {
        echo "Transaction #{$bill->initialTransaction->id}:\n";
        $inventoryDebits = 0;
        foreach ($bill->initialTransaction->journalEntries as $entry) {
            $debit = $entry->type->value === 'debit' ? $entry->amount : 0;
            $credit = $entry->type->value === 'credit' ? $entry->amount : 0;
            echo "  {$entry->account->name} (Type: {$entry->account->type->value}): ";
            echo "Debit=" . number_format($debit, 2) . " Credit=" . number_format($credit, 2) . "\n";

            if ($entry->account->type->value === 'current_asset' && $entry->type->value === 'debit') {
                $inventoryDebits += $entry->amount;
            }
        }
        echo "Total Inventory Debits: RM" . number_format($inventoryDebits / 100, 2) . "\n";
    } else {
        echo "❌ NO TRANSACTION!\n";
    }
    echo "\n" . str_repeat('-', 80) . "\n\n";
}

// Check movements
echo "\n=== INVENTORY MOVEMENTS FROM BILLS ===\n\n";
$billMovements = DB::table('inventory_movements')
    ->where('reference_type', 'App\\Models\\Accounting\\Bill')
    ->get();

echo "Total Bill Movements: " . $billMovements->count() . "\n";
$totalValue = $billMovements->sum(function ($m) {
    return $m->quantity * $m->unit_cost;
});
echo "Total Movement Value: RM" . number_format($totalValue / 100, 2) . "\n\n";

foreach ($billMovements as $movement) {
    $bill = Bill::find($movement->reference_id);
    echo "Bill #{$movement->reference_id}: {$bill->bill_number}\n";
    echo "  Item ID: {$movement->inventory_item_id}\n";
    echo "  Qty: {$movement->quantity}, Cost: {$movement->unit_cost}\n";
    echo "  Value: RM" . number_format(($movement->quantity * $movement->unit_cost) / 100, 2) . "\n";
}
