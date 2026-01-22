<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\InventoryMovement;
use App\Models\Accounting\JournalEntry;

echo "=== COMPLETE INVENTORY VALUE RECONCILIATION ===\n\n";

// 1. Dashboard (using NEW batch-based calculation)
$dashboardValue = InventoryBatch::whereHas('inventoryItem', function ($q) {
    $q->where('company_id', 1);
})
    ->where('quantity_remaining', '>', 0)
    ->get()
    ->sum(function ($batch) {
        return $batch->quantity_remaining * $batch->unit_cost;
    });

echo "1. Dashboard Widget (from batches):\n";
echo "   RM" . number_format($dashboardValue / 100, 2) . "\n\n";

// 2. Inventory Movements
$movements = InventoryMovement::whereHas('inventoryItem', function ($q) {
    $q->where('company_id', 1);
})->get();

$movementsValue = 0;
foreach ($movements as $m) {
    $movementsValue += ($m->quantity * $m->unit_cost);
}

echo "2. Inventory Movements:\n";
echo "   RM" . number_format($movementsValue / 100, 2) . "\n\n";

// 3. Accounting (Inventory account journal entries)
$accountingDebits = JournalEntry::whereHas('account', function ($q) {
    $q->where('name', 'Inventory');
})
    ->whereHas('transaction', function ($q) {
        $q->where('company_id', 1);
    })
    ->where('type', 'debit')
    ->sum('amount');

$accountingCredits = JournalEntry::whereHas('account', function ($q) {
    $q->where('name', 'Inventory');
})
    ->whereHas('transaction', function ($q) {
        $q->where('company_id', 1);
    })
    ->where('type', 'credit')
    ->sum('amount');

$accountingValue = $accountingDebits - $accountingCredits;

echo "3. Accounting (Journal Entries):\n";
echo "   Debits:  RM" . number_format($accountingDebits / 100, 2) . "\n";
echo "   Credits: RM" . number_format($accountingCredits / 100, 2) . "\n";
echo "   Net:     RM" . number_format($accountingValue / 100, 2) . "\n\n";

// 4. Physical Batches (detailed breakdown)
echo "4. Physical Batches (Detailed):\n";
$batches = InventoryBatch::with('inventoryItem.offering')
    ->whereHas('inventoryItem', function ($q) {
        $q->where('company_id', 1);
    })
    ->where('quantity_remaining', '>', 0)
    ->orderBy('inventory_item_id')
    ->orderBy('received_date')
    ->get();

$itemTotals = [];
foreach ($batches as $batch) {
    $itemName = $batch->inventoryItem->offering->name ?? 'Unknown';
    $batchValue = $batch->quantity_remaining * $batch->unit_cost;

    if (!isset($itemTotals[$itemName])) {
        $itemTotals[$itemName] = ['qty' => 0, 'value' => 0];
    }

    $itemTotals[$itemName]['qty'] += $batch->quantity_remaining;
    $itemTotals[$itemName]['value'] += $batchValue;
}

foreach ($itemTotals as $name => $data) {
    $avgCost = $data['qty'] > 0 ? $data['value'] / $data['qty'] : 0;
    echo sprintf(
        "   %-30s: Qty=%5d, Avg=RM%8.2f, Value=RM%10.2f\n",
        $name,
        $data['qty'],
        $avgCost / 100,
        $data['value'] / 100
    );
}

echo "\n";
echo "==========================================\n";
echo "RECONCILIATION SUMMARY\n";
echo "==========================================\n";
echo sprintf("Dashboard:  RM%12s\n", number_format($dashboardValue / 100, 2));
echo sprintf("Movements:  RM%12s\n", number_format($movementsValue / 100, 2));
echo sprintf("Accounting: RM%12s\n", number_format($accountingValue / 100, 2));
echo "==========================================\n\n";

// Check differences
$diff1 = abs($dashboardValue - $movementsValue);
$diff2 = abs($dashboardValue - $accountingValue);
$diff3 = abs($movementsValue - $accountingValue);

if ($diff1 < 10 && $diff2 < 10 && $diff3 < 10) {
    echo "✅ PERFECT RECONCILIATION!\n";
    echo "   All three sources match within RM0.10\n";
    echo "   - Dashboard widget shows accurate inventory value\n";
    echo "   - Inventory movements are properly recorded\n";
    echo "   - Accounting journal entries are in sync\n\n";
    echo "🎉 System integrity verified!\n";
} else {
    echo "❌ DISCREPANCIES FOUND:\n";
    if ($diff1 >= 10) {
        echo "   Dashboard vs Movements: RM" . number_format($diff1 / 100, 2) . "\n";
    }
    if ($diff2 >= 10) {
        echo "   Dashboard vs Accounting: RM" . number_format($diff2 / 100, 2) . "\n";
    }
    if ($diff3 >= 10) {
        echo "   Movements vs Accounting: RM" . number_format($diff3 / 100, 2) . "\n";
    }
}
