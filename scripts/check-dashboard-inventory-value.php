<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Inventory\InventoryStockLevel;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryBatch;

echo "=== DASHBOARD INVENTORY VALUE CALCULATION ===\n\n";

// NEW: Calculate from batches (same as fixed dashboard widget)
$batchTotal = InventoryBatch::whereHas('inventoryItem', function ($q) {
    $q->where('company_id', 1);
})
    ->where('quantity_remaining', '>', 0)
    ->get()
    ->sum(function ($batch) {
        return $batch->quantity_remaining * $batch->unit_cost;
    });

echo "NEW Dashboard Calculation (from batches): RM" . number_format($batchTotal / 100, 2) . "\n\n";

// OLD: Get stock levels (old dashboard calculation for comparison)
$stockLevels = InventoryStockLevel::whereHas('inventoryItem', function ($q) {
    $q->where('company_id', 1);
})->with('inventoryItem.offering')->get();

echo "OLD Dashboard Calculation (from stock_levels.average_cost):\n";
echo "Stock Levels: {$stockLevels->count()}\n\n";

$totalFromWidget = 0;
foreach ($stockLevels as $level) {
    if (!$level->average_cost) {
        echo sprintf(
            "%-30s: SKIP (no average_cost)\n",
            $level->inventoryItem->offering->name ?? 'N/A'
        );
        continue;
    }
    $cost = is_object($level->average_cost)
        ? $level->average_cost->getAmount()
        : $level->average_cost;
    $value = $level->quantity_on_hand * $cost;
    $totalFromWidget += $value;

    echo sprintf(
        "%-30s: Qty=%5d, AvgCost=RM%8.2f, Value=RM%10.2f\n",
        $level->inventoryItem->offering->name ?? 'N/A',
        $level->quantity_on_hand,
        $cost / 100,
        $value / 100
    );
}

echo "\n";
echo "OLD Total from Dashboard Widget: RM" . number_format($totalFromWidget / 100, 2) . "\n\n";

// Now calculate from movements (like accounting does)
echo "=== INVENTORY VALUE FROM MOVEMENTS ===\n\n";

$movements = InventoryMovement::with('inventoryItem.offering')
    ->whereHas('inventoryItem', function ($q) {
        $q->where('company_id', 1);
    })
    ->orderBy('movement_date')
    ->orderBy('id')
    ->get();

$balances = [];

foreach ($movements as $m) {
    $name = $m->inventoryItem->offering->name ?? 'Unknown';

    if (!isset($balances[$name])) {
        $balances[$name] = ['qty' => 0, 'value' => 0];
    }

    $balances[$name]['qty'] += $m->quantity;
    $balances[$name]['value'] += ($m->quantity * $m->unit_cost);
}

$totalFromMovements = 0;
foreach ($balances as $name => $data) {
    $avgCost = $data['qty'] > 0 ? $data['value'] / $data['qty'] : 0;
    $totalFromMovements += $data['value'];

    echo sprintf(
        "%-30s: Qty=%5d, AvgCost=RM%8.2f, Value=RM%10.2f\n",
        $name,
        $data['qty'],
        $avgCost / 100,
        $data['value'] / 100
    );
}

echo "\n";
echo "Total from Movements: RM" . number_format($totalFromMovements / 100, 2) . "\n\n";

echo "=== COMPARISON ===\n";
echo "NEW Dashboard (batches):  RM" . number_format($batchTotal / 100, 2) . "\n";
echo "OLD Dashboard (avg_cost): RM" . number_format($totalFromWidget / 100, 2) . "\n";
echo "Movements Total:          RM" . number_format($totalFromMovements / 100, 2) . "\n";
$diff = $totalFromMovements - $batchTotal;
echo "Difference (Movements - NEW Dashboard): RM" . number_format($diff / 100, 2) . "\n";

if (abs($diff) < 10) { // Less than 10 cents
    echo "\n✅ PERFECT MATCH! NEW dashboard calculation matches movements and accounting!\n";
} else {
    echo "\n⚠️  Still a mismatch\n";
}
