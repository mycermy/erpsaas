# 🔄 Auto Inventory Item Creation

## Feature Overview

When you create a new **Product** offering, an inventory item is **automatically created** for you! This eliminates the need to manually create inventory items for every product.

---

## How It Works

### ✅ Automatic Creation
When you create an offering with type = **"Product"**:
1. ✅ System automatically creates an `InventoryItem`
2. ✅ Generates a unique SKU
3. ✅ Sets default inventory settings
4. ✅ Shows success notification with SKU

### 🔧 Default Inventory Settings
```php
Track Method: FIFO (First In, First Out)
Reorder Level: 10 units
Reorder Quantity: 25 units
Track Batches: Yes
Status: Active
```

### 🏷️ SKU Generation
SKU is auto-generated using:
- **Prefix**: First 8 characters of product name (cleaned)
- **Suffix**: 4-digit offering ID

**Examples:**
- "Laptop Computer" → `LAPTOPCO-0001`
- "Wireless Mouse" → `WIRELESS-0002`
- "27\" Monitor" → `27MONITO-0003`

If SKU already exists, adds counter: `LAPTOPCO-0001-1`

---

## What Happens When...

### 📦 Creating a Product Offering
```
User Action:
1. Go to Offerings → Create
2. Select Type: "Product"
3. Enter name: "Gaming Keyboard"
4. Fill other fields
5. Click Save

System Actions:
✅ Creates offering
✅ Auto-creates inventory item
✅ Generates SKU: GAMINGKE-0015
✅ Shows notification: "Inventory Item Created"
✅ Links offering ↔ inventory item
```

### 🛠️ Creating a Service Offering
```
User Action:
1. Go to Offerings → Create
2. Select Type: "Service"
3. Enter name: "Consulting"
4. Click Save

System Actions:
✅ Creates offering only
❌ No inventory item created (services don't need inventory)
```

### 🔄 Changing Offering Type

#### Product → Service
```
✅ Offering type changed
✅ Inventory item deactivated (not deleted)
✅ Stock preserved
📊 Historical data maintained
```

#### Service → Product
```
✅ Offering type changed
✅ Inventory item created (if doesn't exist)
✅ New SKU generated
✅ Default settings applied
```

### 🗑️ Deleting Offering
```
✅ Offering soft-deleted
✅ Inventory item deactivated
❌ Stock data NOT deleted
📊 Can be restored
```

### ♻️ Restoring Offering
```
✅ Offering restored
✅ Inventory item reactivated
✅ All stock data available
✅ Ready to use again
```

---

## Implementation Details

### Files Modified

#### 1. `app/Observers/OfferingObserver.php`
- **created()** - Auto-create inventory item for products
- **updated()** - Handle type changes
- **deleted()** - Deactivate inventory item
- **restored()** - Reactivate inventory item
- **createInventoryItem()** - Creation logic
- **generateSku()** - SKU generation with duplicate check

#### 2. `app/Filament/Company/Resources/Common/OfferingResource/Pages/CreateOffering.php`
- Added success notification
- Shows auto-generated SKU
- Links to inventory item

---

## User Experience

### Success Notification
After creating a product offering, you'll see:

```
✓ Inventory Item Created
  An inventory item has been automatically created with SKU: LAPTOPCO-0015
  
  [View Inventory Items →]
```

### Where to Find Auto-Created Items
1. Navigate to **Inventory > Inventory Items**
2. Look for item with matching offering name
3. SKU will be auto-generated
4. All default settings applied

---

## Customizing Inventory Items

After auto-creation, you can customize:

### Via Inventory Item Edit Page
- ✏️ Change SKU
- ✏️ Select track method (FIFO/LIFO/Average)
- ✏️ Adjust reorder levels
- ✏️ Set reorder quantities
- ✏️ Add description
- ✏️ Enable/disable batch tracking

### Via Offering Edit Page
- ✏️ Change product details
- ✏️ Update pricing
- ✏️ Modify accounts
- ⚠️ Changing type will deactivate inventory

---

## Business Logic

### Why Auto-Create?
1. **Reduces manual work** - No need to create inventory items separately
2. **Prevents errors** - Ensures every product has inventory tracking
3. **Saves time** - Immediate inventory management
4. **Maintains consistency** - Standardized SKU format

### Why Not Delete on Type Change?
1. **Preserve history** - Stock movements remain intact
2. **Allow reversion** - Can change back to product
3. **Audit trail** - Historical data for reporting
4. **Safety** - Prevent accidental data loss

---

## Testing the Feature

### Test Case 1: Create New Product
```bash
# Steps:
1. Go to Offerings
2. Click "Create"
3. Select Type: "Product"
4. Name: "Test Product"
5. Fill required fields
6. Save

# Expected:
✅ Offering created
✅ Notification appears
✅ Inventory item created
✅ SKU: TESTPROD-####

# Verify:
1. Check Inventory Items list
2. Find "Test Product"
3. View SKU, settings
4. Check it's active
```

### Test Case 2: Create Service
```bash
# Steps:
1. Create offering with Type: "Service"

# Expected:
✅ Offering created
❌ No inventory item
❌ No notification about inventory

# Verify:
1. Check Inventory Items
2. Should NOT appear
```

### Test Case 3: Change Type Product → Service
```bash
# Steps:
1. Edit existing product offering
2. Change Type to "Service"
3. Save

# Expected:
✅ Type changed
✅ Inventory item deactivated

# Verify:
1. Check Inventory Items
2. Item shows "Inactive"
3. Stock data preserved
```

### Test Case 4: Change Type Service → Product
```bash
# Steps:
1. Edit existing service offering
2. Change Type to "Product"
3. Save

# Expected:
✅ Type changed
✅ New inventory item created
✅ New SKU generated

# Verify:
1. Check Inventory Items
2. New item appears
3. Has auto-generated SKU
```

### Test Case 5: Delete Product Offering
```bash
# Steps:
1. Delete a product offering

# Expected:
✅ Offering deleted
✅ Inventory item deactivated

# Verify:
1. Inventory item still exists
2. Status: Inactive
3. Stock data intact
```

---

## Advanced Scenarios

### Duplicate SKU Handling
If SKU already exists:
```
First:  LAPTOP-0001
Second: LAPTOP-0001-1
Third:  LAPTOP-0001-2
```

### Company Isolation
- SKUs checked within company only
- Different companies can have same SKU
- Multi-tenant safe

### Bulk Import
When importing offerings:
```php
// Each product offering triggers auto-creation
$offering = Offering::create([
    'type' => OfferingType::Product,
    'name' => 'Imported Product',
    // ... other fields
]);
// ✅ Inventory item auto-created
```

---

## Troubleshooting

### Issue: Inventory Item Not Created
**Possible causes:**
1. Offering type is "Service"
2. Observer not registered
3. Cache not cleared

**Solution:**
```bash
php artisan optimize:clear
```

### Issue: Duplicate SKU Error
**Cause:** SKU generation collision

**Solution:** System auto-adds counter suffix

### Issue: Can't Find Inventory Item
**Check:**
1. Offering type is "Product"
2. Inventory Item is active
3. Filter in Inventory Items list

---

## Configuration

### Default Settings
Located in: `app/Observers/OfferingObserver.php`

```php
private function createInventoryItem(Offering $offering): void
{
    InventoryItem::create([
        'track_method' => TrackMethod::FIFO, // Change default
        'reorder_level' => 10,               // Change default
        'reorder_quantity' => 25,            // Change default
        'track_batches' => true,             // Change default
        // ...
    ]);
}
```

### SKU Format
Customize in: `generateSku()` method

```php
private function generateSku(Offering $offering): string
{
    // Customize prefix length (default: 8)
    $prefix = strtoupper(substr(
        preg_replace('/[^A-Za-z0-9]/', '', $offering->name), 
        0, 8  // ← Change this number
    ));
    
    // Customize suffix padding (default: 4 digits)
    $suffix = str_pad(
        (string) $offering->id, 
        4,    // ← Change this number
        '0', 
        STR_PAD_LEFT
    );
    
    return $prefix . '-' . $suffix;
}
```

---

## Benefits

### For Users
- ✅ Faster data entry
- ✅ Less manual work
- ✅ Fewer errors
- ✅ Automatic SKU generation
- ✅ Immediate inventory tracking

### For Business
- ✅ Consistent data structure
- ✅ Better inventory control
- ✅ Audit trail maintained
- ✅ Historical data preserved
- ✅ Scalable process

### For Developers
- ✅ Clean separation of concerns
- ✅ Observer pattern
- ✅ Easy to customize
- ✅ Well documented
- ✅ Test-friendly

---

## Related Documentation

- `INVENTORY_SYSTEM_DOCUMENTATION.md` - Full inventory system guide
- `INVENTORY_FINAL_SUMMARY.md` - Complete feature list
- `BUG_FIX_REPORT_GENERATION.md` - Report generation fixes

---

## Quick Reference

### When Inventory Item IS Created
- ✅ New offering with Type = "Product"
- ✅ Existing offering changed to Type = "Product"
- ✅ Restoring deleted product offering

### When Inventory Item is NOT Created
- ❌ New offering with Type = "Service"
- ❌ Existing product (already has inventory item)
- ❌ Offering changed to Type = "Service" (deactivated instead)

### Default Values Applied
| Setting | Default Value |
|---------|--------------|
| Track Method | FIFO |
| Reorder Level | 10 units |
| Reorder Quantity | 25 units |
| Track Batches | Yes |
| Status | Active |
| SKU | Auto-generated |

---

**Status:** ✅ IMPLEMENTED  
**Date:** October 5, 2025  
**Files Modified:** 2  
**Tests Required:** 5 test cases  
**Impact:** All new product offerings
