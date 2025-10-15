# Stockable Offering Implementation

## Overview
This document describes the implementation of the "stockable" feature for offerings, which integrates inventory tracking directly into the offering workflow.

## Architecture Decision

### Why Use Relationship Instead of Duplicating Fields?

**You were absolutely correct** in questioning why we would duplicate InventoryItem columns in the Offering table. The better approach is to:

1. **Add only a `stockable` boolean flag** to the `offerings` table
2. **Use the existing `inventoryItem` relationship** to access all inventory-related data
3. **Keep all inventory data in the `inventory_items` table** (single source of truth)

### Benefits of This Approach

1. **No Data Duplication**: Inventory fields (SKU, track_method, etc.) remain in one place
2. **Maintain Existing Relationships**: The `offering->inventoryItem` relationship works seamlessly
3. **Backward Compatibility**: Existing inventory items continue to work
4. **Cleaner Schema**: The offerings table stays focused on offering data
5. **Easier Maintenance**: Changes to inventory fields only need to be made in one table

## Database Changes

### Migration: `add_stockable_fields_to_offerings_table`

```php
Schema::table('offerings', function (Blueprint $table) {
    $table->boolean('stockable')->default(false)->after('purchasable');
});
```

**That's it!** Just one boolean field. All other inventory data lives in `inventory_items`.

## Model Changes

### Offering Model (`app/Models/Common/Offering.php`)

#### Added Field
- `stockable` (boolean) - Indicates if this offering should track inventory

#### New Methods
- `isStockable()` - Returns true if stockable AND type is Product
- `ensureInventoryItem()` - Creates InventoryItem if it doesn't exist, returns existing one if it does
- `generateSku()` - Helper to generate SKU when auto-creating inventory item

#### Relationships
The existing `inventoryItem()` HasOne relationship is used - no changes needed!

```php
public function inventoryItem(): HasOne
{
    return $this->hasOne(InventoryItem::class);
}
```

## Observer Changes

### OfferingObserver

Added `saved()` method to automatically:
- **Create InventoryItem** when stockable is enabled (via `ensureInventoryItem()`)
- **Deactivate InventoryItem** when stockable is disabled

```php
public function saved(Offering $offering): void
{
    if ($offering->stockable && $offering->type === OfferingType::Product && !$offering->inventoryItem) {
        $offering->ensureInventoryItem();
    }
    
    if (!$offering->stockable && $offering->inventoryItem) {
        $offering->inventoryItem->update(['active' => false]);
    }
}
```

## UI Changes

### OfferingResource Form

#### Attributes Checkbox
Added "Stockable" option to the attributes list:
```php
Forms\Components\CheckboxList::make('attributes')
    ->options([
        'Sellable' => 'Sellable',
        'Purchasable' => 'Purchasable',
        'Stockable' => 'Stockable', // NEW
    ])
```

#### New Section: Inventory Information
When stockable is checked AND type is Product, a new section appears with fields that **map to the InventoryItem relationship**:

```php
Forms\Components\TextInput::make('inventoryItem.sku')
Forms\Components\Select::make('inventoryItem.track_method')
Forms\Components\Toggle::make('inventoryItem.track_batches')
Forms\Components\TextInput::make('inventoryItem.reorder_level')
Forms\Components\TextInput::make('inventoryItem.reorder_quantity')
CreateAccountSelect::make('inventoryItem.inventory_account_id')
CreateAccountSelect::make('inventoryItem.cogs_account_id')
```

**Key Point**: All fields use dot notation (`inventoryItem.field_name`) to access the related InventoryItem record.

#### Purchase Information Section
When stockable is enabled:
- **Hide** the regular `expense_account_id` field
- **Show** a placeholder note indicating purchases will use the Inventory Asset Account configured in the Inventory Information section

### OfferingResource Table

Added columns that display data from the relationship:
- `inventoryItem.sku` - Shows SKU from related inventory item
- `inventoryItem.track_method` - Shows tracking method badge

## Page Handler Changes

### CreateOffering
```php
protected function handleRecordCreation(array $data): Model
{
    // Extract stockable from attributes
    $data['stockable'] = isset($attributes['Stockable']);
    
    // Extract inventoryItem data
    $inventoryItemData = $data['inventoryItem'] ?? [];
    
    // Create offering
    $offering = parent::handleRecordCreation($data);
    
    // Create/update inventory item if stockable
    if ($offering->stockable) {
        $inventoryItem = $offering->ensureInventoryItem();
        if (!empty($inventoryItemData)) {
            $inventoryItem->update($inventoryItemData);
        }
    }
}
```

### EditOffering
Similar logic to handle updates to the inventoryItem relationship data.

## How It Works

### Creating a Stockable Offering

1. User creates an Offering and checks "Stockable" in attributes
2. User selects type as "Product"
3. Inventory Information section becomes visible
4. User fills in inventory fields (or leaves defaults)
5. On save:
   - Offering is created with `stockable = true`
   - Observer's `saved()` method fires
   - `ensureInventoryItem()` creates an InventoryItem record
   - Form handler updates the InventoryItem with user-provided data
   - Both records are linked via `offering_id` foreign key

### Purchasing a Stockable Offering

When creating a purchase/bill with a stockable offering:
1. System checks `offering->stockable` is true
2. Uses `offering->inventoryItem->inventory_account_id` for the debit entry (Inventory Asset)
3. When sold:
   - Credits the `offering->incomeAccount` (revenue)
   - Debits the `offering->inventoryItem->cogs_account_id` (expense)
   - Reduces inventory quantity via InventoryService

## Data Flow

```
┌─────────────┐
│  Offering   │
│             │
│ stockable=1 │───────┐
│ type=Product│       │
└─────────────┘       │
                      │ HasOne
                      │
                      ▼
             ┌─────────────────┐
             │  InventoryItem  │
             │                 │
             │ sku             │
             │ track_method    │
             │ track_batches   │
             │ reorder_level   │
             │ reorder_quantity│
             │ inventory_acct  │
             │ cogs_acct       │
             └─────────────────┘
```

## Migration Command

To apply the changes:
```bash
php artisan migrate
```

To rollback:
```bash
php artisan migrate:rollback
```

## Testing Checklist

- [ ] Create a new Product offering with Stockable enabled
- [ ] Verify InventoryItem is auto-created
- [ ] Edit the offering and change inventory fields
- [ ] Verify InventoryItem is updated
- [ ] Disable Stockable on an existing offering
- [ ] Verify InventoryItem is deactivated (not deleted)
- [ ] Create a purchase with a stockable offering
- [ ] Verify inventory asset account is debited
- [ ] Create a sale with a stockable offering
- [ ] Verify income account is credited and COGS account is debited

## Backward Compatibility

✅ Existing InventoryItem records continue to work
✅ Offerings without `stockable=true` are unaffected
✅ InventoryItemResource still functions independently
✅ All existing inventory operations remain unchanged

## Summary

This implementation provides a clean integration of inventory tracking into the offering workflow by:
1. Using a single `stockable` boolean flag on offerings
2. Leveraging the existing `inventoryItem` relationship
3. Avoiding data duplication
4. Maintaining backward compatibility
5. Providing a user-friendly UI that conditionally shows inventory fields

The key insight (which you correctly identified) is that we don't need to duplicate InventoryItem columns in the Offering table - we can simply use the relationship to access them!
