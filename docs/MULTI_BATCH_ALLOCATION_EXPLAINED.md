````markdown
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

... (omitted for brevity) ...

## Summary

The multi-batch allocation system:
1.  **Works correctly** for COGS calculation
2.  **Reduces quantities** in all consumed batches
3.  **Follows FIFO/LIFO** ordering properly
4.  **Links to first batch** only in movement record
5.  **Could be enhanced** with better traceability

The current implementation prioritizes simplicity and performance while maintaining accurate inventory valuation and quantities. The limitation is mainly in reporting/traceability, not in actual inventory management.

````