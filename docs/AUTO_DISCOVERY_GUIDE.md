````markdown
# 🔄 Auto-Discovery Navigation Setup Guide

## What We Just Changed

### ❌ Before (Manual Navigation)
```php
->navigation(function (NavigationBuilder $builder): NavigationBuilder {
    return $builder
        ->items([...Dashboard::getNavigationItems(), ...])
        ->groups([
            NavigationGroup::make('Inventory')
                ->items([
                    ...InventoryItemResource::getNavigationItems(),
                    // Had to manually add each item
                ]),
        ]);
})
```

**Problems:**
- Every new page/resource needs manual registration
- Easy to forget to add new items
- Provider file becomes bloated
- `discoverPages()` and `discoverResources()` were ignored

---

### ✅ After (Auto-Discovery)
```php
// Just removed the ->navigation() closure entirely!
// Filament now auto-discovers based on class properties
```

**Benefits:**
- ✨ **Automatic** - New pages/resources appear automatically
- 📁 **Clean** - Less code in provider
- 🎯 **DRY** - Define navigation once in each resource/page
- 🔧 **Maintainable** - Add new features without touching provider

---

## How Auto-Discovery Works

Filament automatically reads these properties from each Resource/Page:

```php
class InventoryItemResource extends Resource
{
    // 🎯 Which model this manages
    protected static ?string $model = InventoryItem::class;
    
    // 🎨 Icon in sidebar
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    
    // 📁 Group name (creates/joins group)
    protected static ?string $navigationGroup = 'Inventory';
    
    // 🔢 Order within group (lower = higher)
    protected static ?int $navigationSort = 1;
    
    // 🏷️ Custom label (optional, defaults to plural model name)
    protected static ?string $navigationLabel = 'Items';
    
    // 👁️ Hide from navigation (optional)
    protected static bool $shouldRegisterNavigation = true;
}
```

---

## Current Navigation Structure

With auto-discovery enabled, your navigation is now organized by the properties in each file:

### 🏠 Top Level Items
| Page | Icon | Sort | File |
|------|------|------|------|
| **Dashboard** | home | -2 | `Pages/Dashboard.php` |
| **Reports** | chart-bar | ? | `Pages/Reports.php` |
| Other pages... | - | - | Auto-discovered |

### 📦 Inventory Group
| Resource/Page | Icon | Sort | File |
|---------------|------|------|------|
| **Inventory Items** | cube | 1 | `Resources/Inventory/InventoryItemResource.php` |
| **Warehouses** | building-office-2 | 2 | `Resources/Inventory/WarehouseResource.php` |
| **Adjustments** | adjustments-horizontal | 3 | `Resources/Inventory/InventoryAdjustmentResource.php` |
| **Transfers** | arrow-path-rounded | 4 | `Resources/Inventory/InventoryTransferResource.php` |
| **Inventory Reports** | document-chart-bar | 5 | `Pages/Inventory/InventoryReports.php` |

---

## Adding New Pages/Resources

### Before (Manual):
1. Create new resource/page
2. Add properties
3. Open `CompanyPanelProvider.php`
4. Import the class
5. Add to navigation builder
6. Clear cache

### Now (Auto):
1. Create new resource/page
2. Add properties (icon, group, sort)
3. Done! ✨ (Auto-discovered on next request)

Example - Creating a new "Stock Alerts" page:

```php
<?php

namespace App\Filament\Company\Pages\Inventory;

use Filament\Pages\Page;

class StockAlerts extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';
    protected static string $view = 'filament.company.pages.inventory.stock-alerts';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 6;
    protected static ?string $title = 'Stock Alerts';
    
    // That's it! No provider changes needed!
}
```

---

## Customizing Navigation Groups

### Option 1: Use Existing Groups
Just set `$navigationGroup` to match existing groups:
- `'Sales'`
- `'Purchases'`
- `'Inventory'`
- `'Accounting'`
- `'Banking'`
- `'Services'`

### Option 2: Create New Groups
Set any string as group name, it will be auto-created:

```php
protected static ?string $navigationGroup = 'Warehouse Operations';
```

### Option 3: Top-Level Items (No Group)
Set to `null`:

```php
protected static ?string $navigationGroup = null;
```

---

## Navigation Sorting

### Sort Orders (Lower = Higher Position)
- **Negative numbers** - Top of list
  - `-2` - Dashboard (very top)
  - `-1` - Important items
- **0-10** - High priority
- **10-50** - Normal items
- **50+** - Lower priority

### Within Groups
Items are sorted by `$navigationSort` within their group:

```php
// Inventory group
InventoryItem: 1        // ← Appears first
Warehouse: 2            // ← Appears second
Adjustment: 3           // ← Appears third
Transfer: 4             // ← Appears fourth
Reports: 5              // ← Appears last
```

---

## Hiding from Navigation

### Temporarily Hide
```php
protected static bool $shouldRegisterNavigation = false;
```

### Conditionally Show
```php
public static function shouldRegisterNavigation(): bool
{
    return auth()->user()->can('viewInventory');
}
```

---

## Icon Options

Filament uses Heroicons (v2). Common inventory-related icons:

```php
'heroicon-o-cube'                    // Box/Product
'heroicon-o-building-office-2'       // Warehouse
'heroicon-o-adjustments-horizontal'  // Adjustments
'heroicon-o-arrow-path-rounded'      // Transfer/Sync
'heroicon-o-document-chart-bar'      // Reports
'heroicon-o-bell-alert'              // Alerts
'heroicon-o-chart-bar'               // Statistics
'heroicon-o-clipboard-document-list' // List/Inventory
'heroicon-o-archive-box'             // Archive/Storage
'heroicon-o-qr-code'                 // Barcode/SKU
```

Browse all icons: https://heroicons.com (use `o-` prefix for outline)

---

## Troubleshooting

### Navigation doesn't update
```bash
php artisan optimize:clear
```

### Item doesn't appear
Check these properties in your resource/page:
1. ✅ `$navigationIcon` is set
2. ✅ `$navigationGroup` matches existing group name (case-sensitive!)
3. ✅ `$shouldRegisterNavigation` is not `false`
4. ✅ File is in correct namespace

### Wrong order
Adjust `$navigationSort` numbers. Lower = higher position.

### Group icon missing
Group icons can only be set in manual navigation. With auto-discovery, groups don't have icons by default. You can add them by configuring groups separately if needed.

---

## Benefits Summary

| Feature | Manual | Auto-Discovery |
|---------|--------|----------------|
| Add new page | Edit provider + page | Just create page |
| Modify navigation | Edit provider | Edit page properties |
| Code in provider | 50+ lines | 0 lines |
| Maintenance | High | Low |
| Error-prone | Yes | No |
| Dynamic | No | Yes |
| Flexibility | High | Medium |

---

## When to Use Manual vs Auto

### Use Auto-Discovery When:
- ✅ Standard navigation needs
- ✅ Many resources/pages
- ✅ Team collaboration (less conflicts)
- ✅ Prefer convention over configuration

### Use Manual When:
- ⚙️ Need custom group icons
- ⚙️ Complex navigation logic
- ⚙️ Dynamic navigation based on data
- ⚙️ Need fine-grained control

---

## Current Status

✅ **Auto-discovery is now ENABLED**

Your navigation is controlled by properties in each resource/page file.

To see your inventory items:
1. Refresh browser (Cmd+Shift+R)
2. Look for "Inventory" group in sidebar
3. You should see all 5 inventory items

No more provider edits needed! 🎉

````
