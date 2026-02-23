<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$adj = \Zrm\Inventory\Models\InventoryAdjustment::find(6);

echo "Before cancellation:\n";
echo "Status: {$adj->status->value}\n";

$stockBefore = \Zrm\Inventory\Models\InventoryStockLevel::where('inventory_item_id', 1)->first();
echo "Stock: {$stockBefore->quantity_on_hand}\n\n";

echo "Cancelling...\n";

try {
    $adj->cancel();
    echo "SUCCESS: Adjustment cancelled\n\n";
    
    echo "After cancellation:\n";
    $adj->refresh();
    echo "Status: {$adj->status->value}\n";
    
    $stockAfter = \Zrm\Inventory\Models\InventoryStockLevel::where('inventory_item_id', 1)->first();
    $change = $stockAfter->quantity_on_hand - $stockBefore->quantity_on_hand;
    echo "Stock: {$stockAfter->quantity_on_hand} (change: {$change})\n\n";
    
    echo "New movements created:\n";
    $newMovements = \Zrm\Inventory\Models\InventoryMovement::where('reference_type', 'Zrm\\Inventory\\Models\\InventoryAdjustment')
        ->where('reference_id', 6)
        ->where('notes', 'like', 'REVERSAL%')
        ->get();
    
    foreach ($newMovements as $m) {
        $batchId = $m->batch_id ?? 'NULL';
        echo "Movement #{$m->id}: qty={$m->quantity}, batch_id={$batchId}\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
}
