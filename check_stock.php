<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Movement #19 details:\n";
$m = \Zrm\Inventory\Models\InventoryMovement::find(19);
echo "Quantity: {$m->quantity}\n";
echo "Item ID: {$m->inventory_item_id}\n";
echo "Warehouse ID: {$m->warehouse_id}\n";
echo "Batch ID: " . ($m->batch_id ?? 'NULL') . "\n";
echo "Notes: {$m->notes}\n";

echo "\nStock level for item {$m->inventory_item_id}:\n";
$stock = \Zrm\Inventory\Models\InventoryStockLevel::where('inventory_item_id', $m->inventory_item_id)
    ->where('warehouse_id', $m->warehouse_id)
    ->first();
echo "Quantity on hand: {$stock->quantity_on_hand}\n";

echo "\nAll movements for Monitor (item_id=3):\n";
$movements = \Zrm\Inventory\Models\InventoryMovement::where('inventory_item_id', 3)
    ->orderBy('id')
    ->get();
$total = 0;
foreach ($movements as $mv) {
    $total += $mv->quantity;
    echo "Movement #{$mv->id}: qty={$mv->quantity} (running total: {$total})\n";
}
echo "Expected stock from movements: {$total}\n";
echo "Actual stock from stock_level table: {$stock->quantity_on_hand}\n";
echo "Difference: " . ($stock->quantity_on_hand - $total) . "\n";
