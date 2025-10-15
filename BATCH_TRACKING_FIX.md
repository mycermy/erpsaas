# Batch Tracking System - Fixed Implementation

## Problem Identified

The inventory batch tracking system was not functioning correctly:

1. **Purchase movements** were creating batches but NOT linking to them (`batch_id = NULL`)
2. **Sale movements** were NOT calculating which batches to consume
3. **Batch quantities** were NOT being reduced when items were sold
4. **Average cost items** were not tracking batch quantities at all

## Solution Implemented

### 1. Modified `InventoryService::recordMovement()`

**Location:** `app/Services/Inventory/InventoryService.php`

**Changes:**

#### For Outbound Movements (Sales, Adjustments, Transfers):
```php
// Calculate COGS and get batch allocations
if ($movementType->isOutbound() && $item->track_batches) {
    $cogsCalculation = $this->calculateCOGS($item, $warehouse, abs($quantity));
    
    if (isset($cogsCalculation['batches']) && !empty($cogsCalculation['batches'])) {
        $batchAllocations = $cogsCalculation['batches'];
        $batchId = $batchAllocations[0]['batch_id']; // Link to primary batch
        $totalCost = $cogsCalculation['total_cost'];
        
        // Calculate unit cost from COGS
        if ($unitCost === 0 && abs($quantity) > 0) {
            $unitCost = (int) round($totalCost / abs($quantity));
        }
    }
}
```

#### For Inbound Movements (Purchases):
```php
// Create batch and link to movement
if ($movementType->isInbound() && $item->track_batches) {
    $batch = $this->createBatch($item, $warehouse, $quantity, $unitCost, $movementDate ?? now(), $referenceId);
    $movement->update(['batch_id' => $batch->id]);
}
```

#### Reduce Batch Quantities:
```php
// Reduce batch quantities for outbound movements
if (!empty($batchAllocations)) {
    $this->reduceBatches($batchAllocations);
}
```

### 2. Enhanced `calculateAverage()` Method

**Problem:** Average cost items returned empty `batches` array, so quantities were never reduced.

**Solution:** Still allocate batches for quantity tracking (using FIFO order):

```php
protected function calculateAverage(InventoryItem $item, Warehouse $warehouse, float $quantity): array
{
    $stockLevel = InventoryStockLevel::where('inventory_item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->first();

    if (!$stockLevel || $stockLevel->quantity_on_hand <= 0) {
        return ['total_cost' => 0, 'batches' => []];
    }

    $averageCost = $stockLevel->average_cost;
    $totalCost = (int) round($quantity * $averageCost);

    // Even with average cost, we need to reduce batch quantities (use FIFO)
    $batches = InventoryBatch::where('inventory_item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->where('quantity_remaining', '>', 0)
        ->orderBy('received_date')
        ->orderBy('id')
        ->get();

    $batchAllocations = $this->allocateBatches($batches, $quantity);

    return [
        'total_cost' => $totalCost,
        'average_cost' => $averageCost,
        'batches' => $batchAllocations['batches'], // Include for quantity tracking
    ];
}
```

## How It Works Now

### Purchase Flow (Bill Created)
1. `BillObserver::processInventoryInbound()` calls `recordMovement()`
2. `recordMovement()` creates movement record
3. Since it's an inbound movement with `track_batches=true`:
   - Calls `createBatch()` to create new batch
   - Links movement to batch via `batch_id`
4. Result: Purchase movement shows which batch it created ✅

### Sale Flow (Invoice Approved)
1. `InvoiceObserver::processInventoryOutbound()` calls `recordMovement()`
2. Since it's an outbound movement with `track_batches=true`:
   - Calls `calculateCOGS()` which uses FIFO/LIFO/Average logic
   - Gets array of batches to consume
   - Links movement to primary batch via `batch_id`
3. Calls `reduceBatches()` to decrease `quantity_remaining` in batches
4. Result: Sale movement shows which batch it consumed ✅

### Batch Allocation by Track Method

**FIFO (First In, First Out):**
- Laptop Computer uses FIFO
- Oldest batches consumed first
- Example: Batch 1 created Aug 25 consumed before Batch 2 created Sep 04

**LIFO (Last In, First Out):**
- Monitor 27" uses LIFO
- Newest batches consumed first
- Example: Batch 5 created Sep 08 consumed before Batch 4 created Sep 05

**Average Cost:**
- Wireless Mouse uses Average
- Uses weighted average cost for COGS
- Uses FIFO order for quantity tracking (batch reduction)
- Example: Batch 1 quantity reduced first, but cost is averaged

## Verification

Run this to see batch tracking in action:

```bash
php artisan tinker --execute="
\$movements = \App\Models\Inventory\InventoryMovement::with(['inventoryItem.offering', 'batch'])
    ->orderBy('movement_date')
    ->get();

foreach(\$movements as \$m) {
    echo \$m->movement_date->format('M d') . ' | ' . 
         str_pad(\$m->inventoryItem->offering->name, 20) . ' | ' . 
         str_pad(\$m->movement_type->value, 10) . ' | Qty: ' . 
         str_pad(\$m->quantity, 6) . ' | Batch: ' . 
         (\$m->batch?->batch_number ?? 'NULL') . PHP_EOL;
}
"
```

### Expected Results:
- ✅ All movements have `batch_id` set (except non-tracked items)
- ✅ Purchase movements link to the batch they created
- ✅ Sale movements link to the batch they consumed
- ✅ Batch `quantity_remaining` decreases as sales occur
- ✅ Batches are consumed in correct order (FIFO/LIFO)

## Files Modified

1. **`app/Services/Inventory/InventoryService.php`**
   - `recordMovement()` - Added batch allocation logic
   - `calculateAverage()` - Added batch quantity tracking

2. **`app/Observers/BillObserver.php`**
   - Removed manual `createBatch()` call (was creating duplicates)
   - Let `recordMovement()` handle batch creation

## Benefits

1. **Complete Audit Trail**: Every movement shows exactly which batch was affected
2. **Accurate COGS**: Cost of goods sold calculated using correct batch costs
3. **Inventory Visibility**: Can see which batches are being consumed
4. **Expiry Tracking**: Can identify old batches that need attention (FIFO)
5. **Batch Reports**: Can generate batch-level reports and analysis

## UI Integration

The new **Stock Movements** tab in Inventory Items now shows:
- Which batch each sale consumed
- Which batch each purchase created
- Batch numbers linked to movements
- Complete traceability from document to batch

## Testing

Run the enhanced inventory seeder:
```bash
php artisan db:seed --class=EnhancedInventorySeeder
```

Then check:
1. Inventory Items → Select any item → Stock Movements tab
2. See batch numbers in the movement list
3. Batches tab shows remaining quantities decreasing
4. Different products follow their respective costing methods (FIFO/LIFO/Average)
