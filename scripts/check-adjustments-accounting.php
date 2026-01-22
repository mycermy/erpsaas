<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Inventory\InventoryAdjustment;
use App\Models\Accounting\Transaction;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\InventoryMovement;

echo "\n=== INVENTORY ADJUSTMENTS & ACCOUNTING CHECK ===\n\n";

$adjustments = InventoryAdjustment::all();
echo "Total Adjustments: " . $adjustments->count() . "\n\n";

foreach ($adjustments as $adjustment) {
    echo "Adjustment: {$adjustment->adjustment_number}\n";
    echo "  Type: " . ($adjustment->adjustment_type?->value ?? 'N/A') . "\n";
    echo "  Status: {$adjustment->status->value}\n";
    echo "  Reason: {$adjustment->reason}\n";

    // Check if adjustment has items
    $items = $adjustment->items;
    echo "  Items: " . $items->count() . "\n";

    foreach ($items as $item) {
        echo "    - {$item->inventoryItem->sku}: Qty {$item->quantity_adjusted}, Cost " . ($item->unit_cost ?? 'N/A') . "\n";
    }

    // Check for related transactions
    $transactions = Transaction::where('transactionable_type', InventoryAdjustment::class)
        ->where('transactionable_id', $adjustment->id)
        ->get();

    echo "  Transactions: " . $transactions->count() . "\n";

    if ($transactions->isEmpty()) {
        echo "  ⚠️  NO ACCOUNTING TRANSACTIONS!\n";
    } else {
        foreach ($transactions as $transaction) {
            $entries = JournalEntry::where('transaction_id', $transaction->id)->get();
            echo "    Transaction #{$transaction->id}: " . $entries->count() . " entries\n";

            foreach ($entries as $entry) {
                $type = $entry->type->value ?? $entry->type;
                $category = $entry->account->category->value ?? $entry->account->category;
                echo "      - {$entry->account->name} ({$category}): {$type} {$entry->amount}\n";
            }
        }
    }

    // Check for related inventory movements
    $movements = InventoryMovement::where('reference_type', 'App\Models\Inventory\InventoryAdjustmentItem')
        ->whereIn('reference_id', $items->pluck('id'))
        ->get();

    echo "  Inventory Movements: " . $movements->count() . "\n";

    if ($movements->isEmpty()) {
        echo "  ⚠️  NO INVENTORY MOVEMENTS!\n";
    } else {
        foreach ($movements as $movement) {
            $type = $movement->movement_type->value ?? $movement->movement_type;
            echo "    - {$type}: Qty {$movement->quantity} @ {$movement->unit_cost} = {$movement->total_cost}\n";
        }
    }

    echo "\n";
}

// Summary
echo "=== SUMMARY ===\n";
$initialStock = $adjustments->filter(fn($a) => $a->adjustment_type?->value === 'stocktake' && str_contains($a->adjustment_number, 'INIT'));
$damageAdj = $adjustments->filter(fn($a) => $a->adjustment_type?->value === 'damage' && str_contains($a->adjustment_number, 'DMG'));

echo "Initial Stock Adjustments: " . $initialStock->count() . "\n";
echo "Damage Adjustments: " . $damageAdj->count() . "\n\n";

$adjWithTransactions = $adjustments->filter(function ($adj) {
    return Transaction::where('transactionable_type', InventoryAdjustment::class)
        ->where('transactionable_id', $adj->id)
        ->exists();
})->count();

$adjWithMovements = $adjustments->filter(function ($adj) {
    $itemIds = $adj->items->pluck('id');
    return InventoryMovement::where('reference_type', 'App\Models\Inventory\InventoryAdjustmentItem')
        ->whereIn('reference_id', $itemIds)
        ->exists();
})->count();

echo "Adjustments with Accounting Transactions: {$adjWithTransactions} / " . $adjustments->count() . "\n";
echo "Adjustments with Inventory Movements: {$adjWithMovements} / " . $adjustments->count() . "\n\n";

if ($adjWithTransactions == 0) {
    echo "❌ PROBLEM: Inventory adjustments are NOT creating accounting transactions!\n";
    echo "   This means initial stock and damage adjustments don't affect accounting reports.\n";
} else if ($adjWithTransactions < $adjustments->count()) {
    echo "⚠️  WARNING: Some adjustments missing accounting transactions!\n";
} else {
    echo "✅ All adjustments have accounting transactions.\n";
}

if ($adjWithMovements == 0) {
    echo "❌ PROBLEM: Inventory adjustments are NOT creating inventory movements!\n";
} else if ($adjWithMovements < $adjustments->count()) {
    echo "⚠️  WARNING: Some adjustments missing inventory movements!\n";
} else {
    echo "✅ All adjustments have inventory movements.\n";
}
