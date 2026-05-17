# PanelShiftDropdown — Plugin Grid Menu

The `PanelShiftDropdown` is a custom Filament plugin that renders as a dropdown in the top navigation (before the user menu). It acts as the application's module/plugin grid menu, giving users access to cross-panel navigation, company settings, and accessibility controls from one place.

## Key Files

| Purpose | Path |
|---|---|
| Plugin class | `plugins/erpsaas/core/src/Filament/Components/PanelShiftDropdown.php` |
| Root Blade view | `plugins/erpsaas/core/resources/views/components/panel-shift-dropdown.blade.php` |
| Sub-components | `plugins/erpsaas/core/resources/views/components/panel-shift-dropdown/` |

## Registration

`PanelShiftDropdown` is registered as a Filament plugin inside each panel provider's `->plugins([])` array. It is **not** auto-registered — it must be explicitly added.

```php
// app/Providers/Filament/CompanyPanelProvider.php
->plugins([
    PanelShiftDropdown::make()
        ->logoutItem()
        ->companySettings()
        ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
            return $builder->items(Account::getNavigationItems());
        }),
])
```

## Fluent Configuration API

| Method | Default | Description |
|---|---|---|
| `->logoutItem(bool $condition = true)` | `true` | Show/hide the logout button in the main panel |
| `->companySettings(bool $condition = true)` | `true` | Show/hide the Company Settings submenu (with company switcher) |
| `->displayAndAccessibility(bool $condition = true)` | `true` | Show/hide the Display & Accessibility submenu |
| `->navigation(Closure $builder)` | — | Supply a `NavigationBuilder` callback to populate the main panel with items |
| `->renderHook(string $hook)` | `PanelsRenderHook::USER_MENU_BEFORE` | Change which Filament render hook the dropdown is injected at |

## How the Navigation Hierarchy Works

`PanelShiftDropdown` processes items from the `NavigationBuilder` into a nested panel hierarchy. Each "panel" in the dropdown is a slide-in sub-menu:

```
Main panel
├── NavigationItem (standalone link)
├── NavigationGroup (labeled) → sub-panel
│   ├── NavigationItem
│   └── NavigationItem
├── Company Settings → sub-panel
│   └── Company Switcher → sub-panel
└── Display & Accessibility → sub-panel
```

Item type dispatch (in `processItem()`):

- **Group with a label** → becomes a new sub-panel (`processGroupItem`)
- **Group without a label** → its children are inlined into the current panel
- **NavigationItem with child items** → becomes a new sub-panel (`processNavigationItem`)
- **Standalone NavigationItem** → rendered as a direct link (`addStandaloneItem`)

## Making a Plugin's Cluster Appear in Top Navigation

The `PanelShiftDropdown` itself only shows items that are explicitly passed to its `navigation()` builder. Cluster navigation (e.g. Bengkel, Inventory, Human Resources) appears in the **top navigation bar** — not inside the dropdown — based on `topNavigation()` being enabled in `CompanyPanelProvider`.

### Required: `getNavigationGroup()` on the Cluster

For a cluster to appear under the correct top-nav group, it **must** declare a `getNavigationGroup()` method that returns a string matching one of the `NavigationGroup` labels defined in `CompanyPanelProvider::navigationGroups()`.

```php
// plugins/zrm/bengkel/src/Filament/Clusters/Bengkel.php
public static function getNavigationGroup(): ?string
{
    return __('Bengkel');
}
```

Without this, the cluster is ungrouped and may not appear at all in the top navigation.

### Navigation Groups Defined in `CompanyPanelProvider`

```
Dashboard
Bengkel
Sales
Purchases
Inventory
Human Resources
Accounting
Banking
Settings
```

Each plugin cluster must return one of these strings from `getNavigationGroup()` to be placed correctly.

## Existing Cluster Examples

| Plugin | Cluster class | `getNavigationGroup()` return |
|---|---|---|
| HR | `Zrm\Hr\Filament\Company\Clusters\HumanResources` | `'Human Resources'` |
| Inventory | `Zrm\Inventory\Filament\Clusters\Operations` | translation → `'Inventory'` |
| Bengkel | `Zrm\Bengkel\Filament\Clusters\Bengkel` | `'Bengkel'` |

## Plugin Auto-Registration Pattern

For a plugin's cluster to be discovered by Filament, the plugin class must call `->discoverClusters()` (and related methods) inside `register()`, scoped to the correct panel:

```php
// plugins/zrm/bengkel/src/BengkelPlugin.php
public function register(Panel $panel): void
{
    $panel->when($panel->getId() === 'company', function (Panel $panel): void {
        $panel
            ->discoverClusters(
                in: __DIR__ . '/Filament/Clusters',
                for: 'Zrm\\Bengkel\\Filament\\Clusters'
            )
            ->discoverResources(...)
            ->discoverPages(...)
            ->discoverWidgets(...);
    });
}
```

The plugin itself must be registered with the panel via `Panel::configureUsing()` in the service provider's `register()` method:

```php
// plugins/zrm/bengkel/src/BengkelServiceProvider.php
public function register(): void
{
    Panel::configureUsing(function (Panel $panel): void {
        $panel->plugin(BengkelPlugin::make());
    });
}
```

This auto-attaches the plugin to all panels; the `$panel->getId() === 'company'` check inside `BengkelPlugin::register()` limits actual discovery to the company panel.

## Common Pitfalls

| Symptom | Cause | Fix |
|---|---|---|
| Plugin not visible anywhere | Service provider `register()` doesn't call `Panel::configureUsing()` | Add `Panel::configureUsing(fn(Panel $p) => $p->plugin(MyPlugin::make()))` in `register()` |
| Cluster not in top-nav group | Cluster class missing `getNavigationGroup()` | Add `getNavigationGroup()` returning the exact navigation group label string |
| Routes not registered | `boot()` has a feature-flag guard that returns early | Remove the guard or ensure the config value is set |
| Plugin visible but wrong group | `getNavigationGroup()` string doesn't match any group in `CompanyPanelProvider` | Use the exact label string from the `->navigationGroups([])` array |
