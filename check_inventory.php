<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$movements = \App\Models\Inventory\InventoryMovement::with('inventoryItem.offering')
    ->orderBy('movement_date')
    ->get();

echo "INVENTORY MOVEMENTS:\n\n";

$balances = [];
foreach ($movements as $m) {
    $name = $m->inventoryItem->offering->name;
    if (! isset($balances[$name])) {
        $balances[$name] = ['qty' => 0, 'cost' => $m->unit_cost];
    }
    $balances[$name]['qty'] += $m->quantity;

    echo sprintf(
        "%s | %-25s | %-12s | Qty: %5.0f | Balance: %.0f\n",
        $m->movement_date->format('Y-m-d'),
        $name,
        $m->movement_type->value,
        $m->quantity,
        $balances[$name]['qty']
    );
}

echo "\nFINAL BALANCES:\n";
$totalValue = 0;
foreach ($balances as $name => $data) {
    $value = ($data['qty'] * $data['cost']) / 100;
    $totalValue += $value;
    echo sprintf(
        "%-25s: %5d units @ RM%8.2f = RM%10.2f\n",
        $name,
        $data['qty'],
        $data['cost'] / 100,
        $value
    );
}

echo sprintf("\nTOTAL INVENTORY VALUE: RM%10.2f\n", $totalValue);
