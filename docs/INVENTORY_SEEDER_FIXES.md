````markdown
# Inventory Seeder Fixes - October 15, 2025

## Issues Identified

### 1. Offerings Not Marked as Sellable/Purchasable/Stockable
**Problem**: When creating offerings in the seeder, the boolean flags `sellable`, `purchasable`, and `stockable` were not being set to `true`. This caused:
- Items not appearing in sales/purchase contexts
- Inventory features not being enabled for these offerings

**Fix**: Updated the `Offering::firstOrCreate()` call to include these flags in the defaults:
```php
$offering = Offering::firstOrCreate(
    ['company_id' => $company->id, 'name' => $productData['name']],
    [
        'type' => $productData['type'],
        'description' => $productData['description'],
        'price' => random_int(50000, 200000),
        'sellable' => true,       //  Added
        'purchasable' => true,    //  Added
        'stockable' => true,      //  Added
    ]
);
```

Also added logic to update existing offerings:
```php
if (! $offering->wasRecentlyCreated) {
    $offering->update([
        'sellable' => true,
        'purchasable' => true,
        'stockable' => true,
    ]);
}
```

### 2. Inventory Items Not Marked as Active
**Problem**: Existing inventory items created in previous runs were not marked as active.

**Fix**: Added logic to update existing items:
```php
if (! $inventoryItem->wasRecentlyCreated && ! $inventoryItem->active) {
    $inventoryItem->update(['active' => true]);
}
```

### 3. Damage Adjustments Not Creating Proper Records
**Problem**: The `simulateDamageAdjustment()` method was only calling `recordMovement()`, which creates `InventoryMovement` records but not `InventoryAdjustment` records. This meant:
- No records appeared in the Inventory Adjustments table/page
- The proper adjustment workflow was not being followed
- Missing audit trail for adjustments

**Fix**: Completely rewrote the method to follow the proper flow:

```php
private function simulateDamageAdjustment(
    InventoryService $inventoryService,
    InventoryItem $inventoryItem,
    Warehouse $warehouse
): void {
    // 1. Create InventoryAdjustment record
    $adjustment = \App\Models\Inventory\InventoryAdjustment::create([
        'company_id' => $inventoryItem->company_id,
        'warehouse_id' => $warehouse->id,
        'adjustment_number' => 'ADJ-...',
        'adjustment_date' => $adjustmentDate,
        'status' => \App\Enums\Inventory\AdjustmentStatus::Approved,
        'reason' => 'Stock damaged during handling',
        'approved_by' => 1,
        'approved_at' => $adjustmentDate->addMinutes(30),
        'created_by' => 1,
    ]);

    // 2. Create InventoryAdjustmentItem (line item)
    $adjustmentItem = \App\Models\Inventory\InventoryAdjustmentItem::create([
        'company_id' => $inventoryItem->company_id,
        'adjustment_id' => $adjustment->id,
        'inventory_item_id' => $inventoryItem->id,
        'quantity_before' => $stockLevel->quantity_available,
        'quantity_after' => $stockLevel->quantity_available - $damageQuantity,
        'quantity_adjusted' => -$damageQuantity,
        'unit_cost' => $avgUnitCost,
        'reason' => "{$damageQuantity} units damaged - written off",
    ]);

    // 3. Record movement to update stock levels
    $inventoryService->recordMovement(
        item: $inventoryItem,
        warehouse: $warehouse,
        quantity: -$damageQuantity,
        movementType: \App\Enums\Inventory\MovementType::Adjustment,
        unitCost: $avgUnitCost,
        movementDate: $adjustmentDate,
        notes: "Adjustment #{$adjustment->adjustment_number} - ..."
    );
}
```

## Verification Results

After running the updated seeder:

### Offerings
```
Keyboard Mechanical: sellable=YES, purchasable=YES, stockable=YES
Laptop Computer: sellable=YES, purchasable=YES, stockable=YES
Monitor 27": sellable=YES, purchasable=YES, stockable=YES
Wireless Mouse: sellable=YES, purchasable=YES, stockable=YES
```

### Inventory Items
```
Laptop Computer (SKU: LAPTOPCO-0011): active=YES
Wireless Mouse (SKU: WIRELESS-0012): active=YES
Monitor 27" (SKU: MONITOR2-0013): active=YES
Keyboard Mechanical (SKU: KEYBOARD-0014): active=YES
```

### Inventory Adjustments
```
Total adjustments: 4
ADJ-MAIN-251008-911 - approved - 1 items
  - Laptop Computer: -1.00 units
ADJ-MAIN-251014-189 - approved - 1 items
  - Wireless Mouse: -5.00 units
ADJ-MAIN-251008-323 - approved - 1 items
  - Monitor 27": -7.00 units
ADJ-MAIN-251011-678 - approved - 1 items
  - Keyboard Mechanical: -5.00 units
```

## What Should Now Work

1. **Inventory Items Page** (`/company/1/inventory/inventory-items`): Should show 4 active inventory items
2. **Inventory Adjustments Page** (`/company/1/inventory/inventory-adjustments`): Should show 4 approved adjustment records
3. **Offerings Page**: Items should show as sellable, purchasable, and stockable
4. **Inventory Valuation Report**: Should display correct values with purchase, sales, and adjustment history

## Testing Checklist

- [ ] Navigate to Inventory  Inventory Items - verify 4 items visible
- [ ] Navigate to Inventory  Inventory Adjustments - verify 4 adjustments visible
- [ ] Navigate to Offerings - verify stockable checkbox is checked for these items
- [ ] View Inventory Valuation Report - verify correct values and movements
- [ ] Try creating a new adjustment - verify the workflow works as expected

## Files Modified

- `/database/seeders/InventorySeeder.php`
  - Added sellable/purchasable/stockable flags to offering creation
  - Added logic to update existing offerings and inventory items
  - Rewrote simulateDamageAdjustment to create proper InventoryAdjustment records

## Notes

- The seeder is idempotent - safe to run multiple times
- Old inventory items from previous test data remain inactive (not from our seeder)
- Each run adds new purchases, sales, and adjustments to existing items
- Adjustment numbers are unique per run with random suffixes

````