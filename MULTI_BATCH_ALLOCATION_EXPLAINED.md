# Multi-Batch Allocation System - How It Works

## Overview

When selling items using **FIFO (First In, First Out)** or **LIFO (Last In, First Out)** methods, a single sale may need to consume inventory from **multiple batches** if the first batch doesn't have enough quantity.

## The Algorithm: `allocateBatches()`

### Location
`app/Services/Inventory/InventoryService.php` - Line 187-215

### How It Works

```php
protected function allocateBatches($batches, float $quantity): array
{
    $remainingQty = $quantity;           // Start with total quantity needed
    $totalCost = 0;                      // Track total COGS
    $allocatedBatches = [];              // Store all batch allocations
    
    foreach ($batches as $batch) {
        if ($remainingQty <= 0) {
            break;                        // Done! All quantity allocated
        }
        
        // Take as much as possible from this batch
        $qtyFromBatch = min($remainingQty, $batch->quantity_remaining);
        $costFromBatch = (int) round($qtyFromBatch * $batch->unit_cost);
        
        $allocatedBatches[] = [
            'batch_id' => $batch->id,
            'quantity' => $qtyFromBatch,
            'unit_cost' => $batch->unit_cost,
            'total_cost' => $costFromBatch,
        ];
        
        $totalCost += $costFromBatch;
        $remainingQty -= $qtyFromBatch;   // Reduce remaining needed
    }
    
    return [
        'total_cost' => $totalCost,
        'batches' => $allocatedBatches
    ];
}
```

## Step-by-Step Example: FIFO with 50 Unit Sale

### Initial State: Laptop Computer Batches

```
Batch 1 (ID=2):  Received=50 units, Remaining=18 units, Cost=RM1,000/unit, Date=Aug 25
Batch 2 (ID=5):  Received=39 units, Remaining=39 units, Cost=RM1,000/unit, Date=Sep 04
Batch 3 (ID=7):  Received=30 units, Remaining=30 units, Cost=RM1,000/unit, Date=Sep 04
Batch 4 (ID=12): Received=29 units, Remaining=29 units, Cost=RM1,000/unit, Date=Sep 08
```

### Customer Orders: 50 Units

**Step 1:** Calculate COGS using FIFO
```php
$warehouse = Warehouse::find(1);
$cogsCalculation = $inventoryService->calculateCOGS($laptop, $warehouse, 50);
```

This calls `calculateFIFO()` which:
1. Gets batches ordered by `received_date` ASC (oldest first)
2. Calls `allocateBatches($batches, 50)`

**Step 2:** Allocation Process

```
Iteration 1:
  - Need: 50 units
  - Batch 1 has: 18 units remaining
  - Take: min(50, 18) = 18 units from Batch 1
  - Cost: 18 × RM1,000 = RM18,000
  - Remaining needed: 50 - 18 = 32 units
  
Iteration 2:
  - Need: 32 units
  - Batch 2 has: 39 units remaining
  - Take: min(32, 39) = 32 units from Batch 2
  - Cost: 32 × RM1,000 = RM32,000
  - Remaining needed: 32 - 32 = 0 units
  - ✅ DONE!
```

**Step 3:** Result Array

```php
[
    'total_cost' => 50000,  // RM50,000 (18,000 + 32,000)
    'batches' => [
        [
            'batch_id' => 2,
            'quantity' => 18,
            'unit_cost' => 1000,
            'total_cost' => 18000
        ],
        [
            'batch_id' => 5,
            'quantity' => 32,
            'unit_cost' => 1000,
            'total_cost' => 32000
        ]
    ]
]
```

## How It's Recorded

### 1. Movement Record Creation

In `recordMovement()`, when processing the sale:

```php
// Calculate COGS and get batch allocations
$cogsCalculation = $this->calculateCOGS($item, $warehouse, abs($quantity));

if (isset($cogsCalculation['batches']) && !empty($cogsCalculation['batches'])) {
    $batchAllocations = $cogsCalculation['batches'];
    
    // Link movement to PRIMARY (first) batch
    $batchId = $batchAllocations[0]['batch_id'];  // Batch 2 in our example
    
    $totalCost = $cogsCalculation['total_cost'];  // RM50,000
    $unitCost = (int) round($totalCost / abs($quantity));  // RM1,000
}

// Create ONE movement record
$movement = InventoryMovement::create([
    'inventory_item_id' => $item->id,
    'warehouse_id' => $warehouse->id,
    'batch_id' => $batchId,              // Links to Batch 2 (first consumed)
    'movement_type' => MovementType::Sale,
    'quantity' => -50,
    'unit_cost' => 1000,
    'total_cost' => 50000,               // Combined cost from both batches
    'reference_type' => 'Invoice',
    'reference_id' => 123,
    // ...
]);
```

### 2. Batch Quantity Reduction

```php
// Reduce quantities in ALL consumed batches
$this->reduceBatches($batchAllocations);
```

This loops through the allocations:

```php
public function reduceBatches(array $batchAllocations): void
{
    foreach ($batchAllocations as $allocation) {
        $batch = InventoryBatch::find($allocation['batch_id']);
        if ($batch) {
            $batch->reduceQuantity($allocation['quantity']);
        }
    }
}
```

**Result:**
- Batch 2: `quantity_remaining` = 18 - 18 = **0** ✅
- Batch 5: `quantity_remaining` = 39 - 32 = **7** ✅

### 3. Stock Level Update

```php
$this->updateStockLevel($item, $warehouse, -50, 1000);
```

Updates:
- `quantity_on_hand` decreased by 50
- `quantity_available` decreased by 50
- `average_cost` recalculated

## Database State After 50-Unit Sale

### inventory_movements table
```
id | item_id | batch_id | quantity | unit_cost | total_cost | type
---+----------+----------+----------+-----------+------------+------
45 | 1        | 2        | -50      | 1000      | 50000      | sale
```

**Note:** `batch_id` = 2 (first batch consumed), even though Batch 5 was also used.

### inventory_batches table
```
id | item_id | qty_received | qty_remaining | unit_cost | date
---+---------+--------------+---------------+-----------+--------
2  | 1       | 50           | 0             | 1000      | Aug 25  ⬅ Fully depleted
5  | 1       | 39           | 7             | 1000      | Sep 04  ⬅ Partially used
7  | 1       | 30           | 30            | 1000      | Sep 04
12 | 1       | 29           | 29            | 1000      | Sep 08
```

## Current Limitations

### Single Movement Record
- Creates **ONE** movement record per sale
- Links to the **first** batch only (`batch_id = 2`)
- Does NOT show that Batch 5 was also consumed

### Implications
1. **Batch traceability:** Can't see all batches consumed in a single sale
2. **Notes field:** Could store batch details but not queryable
3. **Reports:** Batch usage reports need to calculate from `quantity_remaining` changes

## What Works Correctly ✅

1. **COGS Calculation:** Total cost is accurate (RM50,000)
2. **Batch Reduction:** Both batches correctly reduced
3. **Stock Level:** Overall inventory count accurate
4. **FIFO Order:** Oldest batches consumed first
5. **Expiry Management:** Old batches depleted first (good for perishables)

## Potential Improvements 🚀

### Option 1: Multiple Movement Records
Create separate movement records for each batch consumed:

```php
foreach ($batchAllocations as $allocation) {
    InventoryMovement::create([
        'batch_id' => $allocation['batch_id'],
        'quantity' => -$allocation['quantity'],
        'unit_cost' => $allocation['unit_cost'],
        'total_cost' => $allocation['total_cost'],
        'parent_movement_id' => $parentMovement->id,  // Link to parent
        // ...
    ]);
}
```

**Pros:**
- Complete traceability
- Each batch usage visible

**Cons:**
- More records (storage)
- Need parent/child relationship

### Option 2: JSON Metadata
Store all allocations in a JSON column:

```php
InventoryMovement::create([
    'batch_id' => $batchAllocations[0]['batch_id'],
    'batch_allocations' => json_encode($batchAllocations),  // New column
    // ...
]);
```

**Pros:**
- Single record
- Complete data preserved

**Cons:**
- Not easily queryable
- Requires JSON column migration

### Option 3: Junction Table
Create `inventory_movement_batch_allocations` table:

```php
Schema::create('inventory_movement_batch_allocations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('movement_id');
    $table->foreignId('batch_id');
    $table->decimal('quantity', 15, 2);
    $table->bigInteger('unit_cost');
    $table->bigInteger('total_cost');
});
```

**Pros:**
- Fully normalized
- Queryable
- Supports reporting

**Cons:**
- Additional table
- More complex queries

## Testing Multi-Batch Allocation

### Scenario 1: Create situation requiring multi-batch

```php
// Setup: Create item with small batches
$inventoryService->createBatch($laptop, $warehouse, 10, 1000, now()->subDays(5));
$inventoryService->createBatch($laptop, $warehouse, 15, 1100, now()->subDays(3));
$inventoryService->createBatch($laptop, $warehouse, 20, 1200, now()->subDays(1));

// Sell 30 units (will need batches 1 + 2 + part of 3)
$result = $inventoryService->calculateCOGS($laptop, $warehouse, 30);

// Result:
// Batch 1: 10 units @ 1000 = 10,000
// Batch 2: 15 units @ 1100 = 16,500
// Batch 3: 5 units @ 1200 = 6,000
// Total: 30 units, cost = 32,500
```

### Scenario 2: View allocation in tinker

```bash
php artisan tinker

$laptop = InventoryItem::find(1);
$warehouse = Warehouse::find(1);
$result = app(InventoryService::class)->calculateCOGS($laptop, $warehouse, 50);

dd($result);
// Shows: total_cost and batches array with all allocations
```

## Summary

The multi-batch allocation system:
1. ✅ **Works correctly** for COGS calculation
2. ✅ **Reduces quantities** in all consumed batches
3. ✅ **Follows FIFO/LIFO** ordering properly
4. ⚠️ **Links to first batch** only in movement record
5. 💡 **Could be enhanced** with better traceability

The current implementation prioritizes simplicity and performance while maintaining accurate inventory valuation and quantities. The limitation is mainly in reporting/traceability, not in actual inventory management.
