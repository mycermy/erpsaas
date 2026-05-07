# Filament TableWidget + #[Reactive] Race Condition

## Problem Summary

When using Filament's `TableWidget` with the `#[Reactive]` attribute (typically via `InteractsWithPageFilters` trait), you may encounter this error after navigating between pages and then changing filters:

```
Typed property Filament\Widgets\TableWidget::$table must not be accessed before initialization
```

## Symptoms

- Widget renders correctly on initial page load
- Widget works fine when directly accessing the page
- **Error occurs** when:
  1. Loading the dashboard page with the widget
  2. Navigating to another page
  3. Returning to the original dashboard
  4. Changing any filter that uses `#[Reactive]`

## Root Cause

This is a **Livewire hydration lifecycle race condition**:

1. The `InteractsWithPageFilters` trait uses `#[Reactive]` on the `$filters` property
2. When the parent dashboard updates filters, Livewire re-renders child widgets
3. Livewire runs: `hydrate()` → `cacheForms()` → `getTableFiltersForm()` → `getTable()` → accesses `$this->table`
4. **Problem**: `$this->table` hasn't been initialized yet because `bootedInteractsWithTable()` hasn't run
5. Result: "Typed property $table must not be accessed before initialization" error

### Lifecycle Timing Issue

```php
// What happens during re-hydration:
1. Livewire hydrates component properties (#[Reactive] triggers here)
2. Livewire calls rendering() hook
3. View tries to access $this->table
4. ❌ ERROR: $table is uninitialized

// Normal lifecycle (no re-hydration):
1. Component boots
2. bootedInteractsWithTable() initializes $table
3. rendering() hook
4. View accesses $this->table
5. ✅ SUCCESS: $table is initialized
```

## Solution

Override the `rendering()` lifecycle hook to ensure `$table` is initialized before the view renders:

```php
use Filament\Widgets\TableWidget;
use Livewire\Attributes\Reactive;

class YourTableWidget extends TableWidget
{
    #[Reactive]
    public ?array $filters = null;

    /**
     * Ensure table is initialized before view renders.
     * Fixes race condition when #[Reactive] triggers hydration.
     */
    public function rendering(): void
    {
        if (!isset($this->table)) {
            $this->table = $this->table($this->makeTable());
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(YourModel::query())
            ->columns([
                // Your columns...
            ]);
    }
}
```

## Why This Works

The `rendering()` hook is called **after hydration but before view rendering**, giving us the perfect opportunity to lazy-initialize the `$table` property if it hasn't been set yet. This ensures the table is always ready when the Blade view tries to access it, regardless of the hydration path Livewire takes.

## Alternative Solutions

### 1. Use Simple Widget Instead (Recommended for Custom Views)

If you don't need TableWidget's built-in features, use a regular `Widget` with a custom Blade view:

```php
use Filament\Widgets\Widget;
use Livewire\Attributes\Reactive;

class YourWidget extends Widget
{
    #[Reactive]
    public ?array $filters = null;

    protected static string $view = 'filament.widgets.your-widget';

    public function getViewData(): array
    {
        return [
            'records' => YourModel::query()->get(),
        ];
    }
}
```

**Pros**: No race condition possible, simpler code
**Cons**: Lose TableWidget features (sorting, searching, pagination, bulk actions)

### 2. Remove #[Reactive] (Auto-Refresh)

Remove the `#[Reactive]` attribute and manually refresh:

```php
class YourTableWidget extends TableWidget
{
    public ?array $filters = null; // No #[Reactive]
    
    public function table(Table $table): Table
    {
        return $table->query($this->getFilteredQuery());
    }
}
```

**Pros**: Avoids race condition
**Cons**: Widget won't auto-refresh when filters change (requires manual page reload)

### 3. Override rendering() Hook (Recommended for TableWidget)

Use the solution shown above.

**Pros**: Maintains all TableWidget features + auto-refresh
**Cons**: Requires understanding of the underlying issue

## When to Use This Pattern

Use the `rendering()` hook fix when:

- ✅ You need Filament TableWidget features (sorting, searching, pagination, bulk actions)
- ✅ You want auto-refresh when filters change via `#[Reactive]`
- ✅ Your widget is used in a dashboard with `InteractsWithPageFilters` or similar reactive properties
- ✅ Users navigate between pages and return to the widget

## Testing Checklist

To verify the fix works:

1. ✅ Load page with widget directly - should render
2. ✅ Change filters - should auto-refresh
3. ✅ Navigate to another page
4. ✅ Return to original page
5. ✅ Change filters - should auto-refresh without errors
6. ✅ Check error logs - no "must not be accessed before initialization" errors

## Related Files

- `Filament\Widgets\TableWidget` - Base widget class with `$table` property
- `Erpsaas\Dashboard\Concerns\InteractsWithPageFilters` - Trait that adds `#[Reactive]` to `$filters`
- `vendor/filament/widgets/resources/views/table-widget.blade.php` - View that accesses `$this->table`

## Additional Notes

- This issue is specific to the combination of TableWidget + #[Reactive] attribute
- Regular Widgets don't have this issue because they don't have a `$table` property
- The issue only manifests after navigation because Livewire caches component state
- Browser cache can persist stale state even after `php artisan optimize:clear`
- Both `User::query()` and `Vendor::query()` work identically - the issue is purely timing

## See Also

- [Filament TableWidget Documentation](https://filamentphp.com/docs/3.x/widgets/table-widgets)
- [Livewire Lifecycle Hooks](https://livewire.laravel.com/docs/lifecycle-hooks)
- [Livewire #[Reactive] Attribute](https://livewire.laravel.com/docs/properties#reactive-properties)

---

**Last Updated**: May 7, 2026  
**Issue First Encountered**: Sales Team Performance Widget development  
**Resolution**: Override `rendering()` hook to lazy-initialize `$table` property
