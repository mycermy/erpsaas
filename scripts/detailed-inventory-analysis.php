<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Inventory\InventoryStockLevel;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryItem;

echo "=== DETAILED INVENTORY VALUE ANALYSIS ===\n\n";

$items = InventoryItem::where('company_id', 1)->with(['offering', 'stockLevels'])->get();

$totalDashboard = 0;
$totalMovements = 0;
$totalAccounting = 0;

foreach ($items as $item) {
    echo "==========================================\n";
    echo "{$item->offering->name}\n";
    echo "Track Method: {$item->track_method->value}\n";
    echo "==========================================\n";

    // Get stock level (dashboard calculation)
    $stockLevel = $item->stockLevels->first();
    if ($stockLevel) {
        $avgCost = is_object($stockLevel->average_cost)
            ? $stockLevel->average_cost->getAmount()
            : $stockLevel->average_cost;
        $dashboardValue = $stockLevel->quantity_on_hand * $avgCost;
        $totalDashboard += $dashboardValue;

        echo "Stock Level (Dashboard):\n";
        echo "  Quantity: {$stockLevel->quantity_on_hand}\n";
        echo "  Average Cost (from stock_levels): RM" . number_format($avgCost / 100, 2) . "\n";
        echo "  Value: RM" . number_format($dashboardValue / 100, 2) . "\n\n";
    }

    // Get all movements
    $movements = InventoryMovement::where('inventory_item_id', $item->id)
        ->orderBy('movement_date')
        ->orderBy('id')
        ->get();

    echo "Movements:\n";
    $balance = 0;
    $totalCost = 0;
    foreach ($movements as $m) {
        $balance += $m->quantity;
        $totalCost += ($m->quantity * $m->unit_cost);
        echo sprintf(
            "  %s: %+5d @ RM%8.2f = RM%10.2f | Balance: %5d | Total Cost: RM%10.2f\n",
            $m->movement_type->value,
            $m->quantity,
            $m->unit_cost / 100,
            ($m->quantity * $m->unit_cost) / 100,
            $balance,
            $totalCost / 100
        );
    }

    $movementAvgCost = $balance > 0 ? $totalCost / $balance : 0;
    $totalMovements += $totalCost;

    echo "\nMovements Summary:\n";
    echo "  Final Balance: {$balance}\n";
    echo "  Total Cost: RM" . number_format($totalCost / 100, 2) . "\n";
    echo "  Movement Avg Cost: RM" . number_format($movementAvgCost / 100, 2) . "\n";
    echo "  Movement Value: RM" . number_format($totalCost / 100, 2) . "\n\n";

    // Get accounting journal entries
    $accountingDebits = \App\Models\Accounting\JournalEntry::whereHas('account', function ($q) use ($item) {
        $q->where('name', 'Inventory');
    })
        ->whereHas('transaction', function ($q) {
            $q->where('company_id', 1);
        })
        ->where('type', 'debit')
        ->whereIn('transaction_id', function ($query) use ($item) {
            $query->select('id')
                ->from('transactions')
                ->where('company_id', 1)
                ->where(function ($q) use ($item) {
                    // From bills
                    $q->whereIn('id', function ($billQuery) use ($item) {
                        $billQuery->select('transaction_id')
                            ->from('document_line_items')
                            ->where('offering_id', $item->offering_id)
                            ->where('documentable_type', 'App\\Models\\Accounting\\Bill')
                            ->whereNotNull('transaction_id');
                    })
                        // From adjustments
                        ->orWhereIn('id', function ($adjQuery) use ($item) {
                            $adjQuery->select('transactions.id')
                                ->from('transactions')
                                ->join('inventory_movements', 'transactions.id', '=', 'inventory_movements.transaction_id')
                                ->where('inventory_movements.inventory_item_id', $item->id)
                                ->where('inventory_movements.quantity', '>', 0);
                        });
                });
        })
        ->sum('amount');

    $accountingCredits = \App\Models\Accounting\JournalEntry::whereHas('account', function ($q) use ($item) {
        $q->where('name', 'Inventory');
    })
        ->whereHas('transaction', function ($q) {
            $q->where('company_id', 1);
        })
        ->where('type', 'credit')
        ->whereIn('transaction_id', function ($query) use ($item) {
            $query->select('id')
                ->from('transactions')
                ->where('company_id', 1)
                ->where(function ($q) use ($item) {
                    // From invoices
                    $q->whereIn('id', function ($invQuery) use ($item) {
                        $invQuery->select('transaction_id')
                            ->from('document_line_items')
                            ->where('offering_id', $item->offering_id)
                            ->where('documentable_type', 'App\\Models\\Accounting\\Invoice')
                            ->whereNotNull('transaction_id');
                    })
                        // From damage adjustments
                        ->orWhereIn('id', function ($adjQuery) use ($item) {
                            $adjQuery->select('transactions.id')
                                ->from('transactions')
                                ->join('inventory_movements', 'transactions.id', '=', 'inventory_movements.transaction_id')
                                ->where('inventory_movements.inventory_item_id', $item->id)
                                ->where('inventory_movements.quantity', '<', 0);
                        });
                });
        })
        ->sum('amount');

    $accountingNet = $accountingDebits - $accountingCredits;
    $totalAccounting += $accountingNet;

    echo "Accounting (Journal Entries):\n";
    echo "  Debits: RM" . number_format($accountingDebits / 100, 2) . "\n";
    echo "  Credits: RM" . number_format($accountingCredits / 100, 2) . "\n";
    echo "  Net Value: RM" . number_format($accountingNet / 100, 2) . "\n\n";

    // Compare
    $diff = $totalCost - $dashboardValue;
    echo "COMPARISON:\n";
    if ($stockLevel) {
        echo "  Dashboard uses: Average Cost from stock_levels = RM" . number_format($avgCost / 100, 2) . "\n";
    }
    echo "  Movements use: Actual movement costs = RM" . number_format($movementAvgCost / 100, 2) . "\n";
    if ($stockLevel) {
        echo "  Difference per unit: RM" . number_format(($movementAvgCost - $avgCost) / 100, 2) . "\n";
        echo "  Total difference: RM" . number_format($diff / 100, 2) . "\n";
    }
    echo "\n\n";
}

echo "==========================================\n";
echo "GRAND TOTALS\n";
echo "==========================================\n";
echo "Dashboard Widget Total: RM" . number_format($totalDashboard / 100, 2) . "\n";
echo "Movements Total:        RM" . number_format($totalMovements / 100, 2) . "\n";
echo "Accounting Total:       RM" . number_format($totalAccounting / 100, 2) . "\n\n";

echo "Difference (Movements - Dashboard): RM" . number_format(($totalMovements - $totalDashboard) / 100, 2) . "\n";
echo "Difference (Accounting - Dashboard): RM" . number_format(($totalAccounting - $totalDashboard) / 100, 2) . "\n";

if ($totalMovements == $totalAccounting) {
    echo "\n✅ Movements and Accounting MATCH perfectly!\n";
} else {
    echo "\n⚠️  Movements and Accounting DON'T MATCH\n";
}

if (abs($totalMovements - $totalDashboard) > 100) {
    echo "\n❌ ISSUE: Dashboard average_cost calculation differs from actual movement costs\n";
    echo "   This is because stock_levels.average_cost is calculated using weighted average,\n";
    echo "   but items with FIFO/LIFO track methods should use their specific costing methods.\n";
}
