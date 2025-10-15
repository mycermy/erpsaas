# Quick Start: Stockable Offerings

## What Changed?

You're absolutely right - instead of duplicating InventoryItem fields in the Offering table, we're using the **existing relationship**!

## Implementation Summary

### Database
- ✅ Added **only** `stockable` boolean to `offerings` table
- ✅ All inventory data stays in `inventory_items` table (accessed via relationship)

### Models
- ✅ `Offering` has new `stockable` field and `ensureInventoryItem()` method
- ✅ Uses existing `inventoryItem()` relationship - no new relationships needed

### UI
- ✅ Added "Stockable" checkbox to offering attributes
- ✅ Inventory Information section appears when stockable + product type
- ✅ All inventory fields map to `inventoryItem.field_name` (using relationship)
- ✅ Purchase section adapts based on stockable status

### Logic
- ✅ Auto-creates InventoryItem when offering becomes stockable
- ✅ Auto-deactivates InventoryItem when offering becomes non-stockable
- ✅ Form handlers save data to both Offering and related InventoryItem

## To Apply Changes

```bash
# Run the migration
php artisan migrate

# Seeding will still work with the updated models
php artisan db:seed --class=InventorySeeder
```

## How It Works

1. **Create Product Offering** → Check "Stockable" attribute
2. **Inventory Section Appears** → Fill in SKU, tracking method, accounts, etc.
3. **On Save** → Offering saved + InventoryItem auto-created/updated via relationship
4. **Purchase Stockable Item** → Uses `inventoryItem->inventory_account_id`
5. **Sell Stockable Item** → Uses `inventoryItem->cogs_account_id` + reduces stock

## Key Benefit

✅ **Single Source of Truth**: All inventory data in `inventory_items` table
✅ **No Duplication**: Accessing via relationship instead of copying columns
✅ **Clean Architecture**: Each table has its own responsibility
✅ **Backward Compatible**: Existing inventory items work as before

## Files Modified

1. `database/migrations/2025_10_15_060627_add_stockable_fields_to_offerings_table.php` - Adds stockable boolean
2. `app/Models/Common/Offering.php` - Adds stockable handling
3. `app/Observers/OfferingObserver.php` - Auto-creates/manages InventoryItem
4. `app/Filament/Company/Resources/Common/OfferingResource.php` - UI changes
5. `app/Filament/Company/Resources/Common/OfferingResource/Pages/CreateOffering.php` - Save handler
6. `app/Filament/Company/Resources/Common/OfferingResource/Pages/EditOffering.php` - Update handler

## Documentation

See `STOCKABLE_OFFERING_IMPLEMENTATION.md` for detailed architecture and data flow.
