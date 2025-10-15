# Multi-Batch Allocation - Quick Reference

## The Question
**"How does the system handle sales when one batch doesn't have enough quantity?"**

## The Answer
The system **automatically allocates from multiple batches** using the `allocateBatches()` method.

## Example: Selling 50 Units (FIFO)

### Before Sale
```
Batch 1: 18 units remaining @ RM1,000/unit  (oldest)
Batch 2: 39 units remaining @ RM1,000/unit
Batch 3: 30 units remaining @ RM1,000/unit
```

### Allocation Process
```
Step 1: Take 18 units from Batch 1 → Cost = RM18,000
Step 2: Take 32 units from Batch 2 → Cost = RM32,000
                                      ─────────────────
Total: 50 units                       Total = RM50,000
```

### After Sale
```
Batch 1:  0 units remaining ✅ Fully depleted (FIFO - oldest first)
Batch 2:  7 units remaining ✅ Partially used
Batch 3: 30 units remaining    Untouched
```

## What Gets Recorded

### inventory_movements (1 record)
```sql
INSERT INTO inventory_movements (
    quantity,
    batch_id,        -- Links to FIRST batch (Batch 1)
    unit_cost,       -- Weighted average: 50,000 / 50 = 1,000
    total_cost,      -- Sum from ALL batches: 50,000
    movement_type
) VALUES (
    -50,
    1,              -- Primary batch ID
    1000,
    50000,
    'sale'
);
```

### inventory_batches (Multiple updates)
```sql
-- Batch 1 reduced
UPDATE inventory_batches SET quantity_remaining = 0 WHERE id = 1;

-- Batch 2 reduced
UPDATE inventory_batches SET quantity_remaining = 7 WHERE id = 2;
```

## Code Flow

### 1. Invoice Observer Triggers
```php
InvoiceObserver::processInventoryOutbound($invoice)
```

### 2. Record Movement Called
```php
$inventoryService->recordMovement(
    item: $inventoryItem,
    quantity: -50,  // Negative = outbound
    movementType: MovementType::Sale,
    ...
);
```

### 3. Calculate COGS (inside recordMovement)
```php
if ($movementType->isOutbound() && $item->track_batches) {
    $cogsCalculation = $this->calculateCOGS($item, $warehouse, 50);
    
    // Result:
    // [
    //     'total_cost' => 50000,
    //     'batches' => [
    //         ['batch_id' => 1, 'quantity' => 18, 'total_cost' => 18000],
    //         ['batch_id' => 2, 'quantity' => 32, 'total_cost' => 32000]
    //     ]
    // ]
}
```

### 4. Create Movement Record
```php
$movement = InventoryMovement::create([
    'batch_id' => $cogsCalculation['batches'][0]['batch_id'],  // First batch
    'quantity' => -50,
    'total_cost' => 50000,  // Combined from all batches
    ...
]);
```

### 5. Reduce All Batches
```php
$this->reduceBatches($cogsCalculation['batches']);

// Loops through:
// Batch 1: reduce by 18
// Batch 2: reduce by 32
```

## Methods Involved

| Method | Purpose | Returns |
|--------|---------|---------|
| `calculateCOGS()` | Routes to FIFO/LIFO/Average | Array with total_cost and batches |
| `calculateFIFO()` | Gets oldest batches first | Calls allocateBatches() |
| `calculateLIFO()` | Gets newest batches first | Calls allocateBatches() |
| `allocateBatches()` | **Core logic** - distributes quantity | Array of batch allocations |
| `reduceBatches()` | Updates quantity_remaining | void |

## The Core Algorithm

```php
protected function allocateBatches($batches, float $quantity): array
{
    $remainingQty = $quantity;
    $allocatedBatches = [];
    
    foreach ($batches as $batch) {
        if ($remainingQty <= 0) break;
        
        // Take what's available or what's needed (whichever is smaller)
        $qtyFromBatch = min($remainingQty, $batch->quantity_remaining);
        
        $allocatedBatches[] = [
            'batch_id' => $batch->id,
            'quantity' => $qtyFromBatch,
            'unit_cost' => $batch->unit_cost,
            'total_cost' => $qtyFromBatch * $batch->unit_cost,
        ];
        
        $remainingQty -= $qtyFromBatch;
    }
    
    return ['batches' => $allocatedBatches, 'total_cost' => ...];
}
```

## Key Characteristics

✅ **Automatic** - No manual batch selection needed  
✅ **Accurate COGS** - Sums costs from all consumed batches  
✅ **Follows Method** - FIFO/LIFO/Average rules applied  
✅ **Reduces All** - All consumed batches updated  
✅ **Single Movement** - One record per sale (links to first batch)  

⚠️ **Limitation** - Movement record only shows first batch in `batch_id`  
💡 **Workaround** - Batch history shows quantity reductions  

## Testing

```bash
# Run this to see multi-batch allocation in action:
php artisan tinker

$service = app(\App\Services\Inventory\InventoryService::class);
$item = \App\Models\Inventory\InventoryItem::first();
$warehouse = \App\Models\Inventory\Warehouse::first();

# Calculate for 50 units
$result = $service->calculateCOGS($item, $warehouse, 50);

# See the allocation
dd($result['batches']);
```

## Summary

The multi-batch allocation system:
1. Automatically uses multiple batches when needed
2. Follows FIFO/LIFO rules correctly
3. Calculates accurate total cost
4. Reduces all involved batches
5. Creates one movement record (linked to primary batch)

**It just works!** 🎯
