<?php

namespace Zrm\Inventory;

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Panel\Concerns\HasNavigation;
use Filament\View\PanelsRenderHook;
use ReflectionClass;

class InventoryPlugin implements Plugin
{
    // use HasNavigation;

    // protected string $renderHook = PanelsRenderHook::TOPBAR_AFTER;

    public function getId(): string
    {
        return 'inventory';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function register(Panel $panel): void
    {
        $panel
            ->when($panel->getId() == 'company', function (Panel $panel) {
                $panel
                    ->discoverResources(
                        in: $this->getPluginBasePath('/Filament/Resources'),
                        for: 'Zrm\\Inventory\\Filament\\Resources'
                    )
                    ->discoverPages(
                        in: $this->getPluginBasePath('/Filament/Pages'),
                        for: 'Zrm\\Inventory\\Filament\\Pages'
                    )
                    ->discoverClusters(
                        in: $this->getPluginBasePath('/Filament/Clusters'),
                        for: 'Zrm\\Inventory\\Filament\\Clusters'
                    )
                    ->discoverWidgets(
                        in: $this->getPluginBasePath('/Filament/Widgets'),
                        for: 'Zrm\\Inventory\\Filament\\Widgets'
                    )

                    // ->navigationItems([
                    //     \Filament\Navigation\NavigationItem::make('settings')
                    //         ->label(fn() => __('accounting::app.navigation.settings.label'))
                    //         ->url(function () {
                    //             try {
                    //                 return \Zrm\Inventory\Filament\Pages\InventoryDashboard::getUrl();
                    //             } catch (\Throwable $e) {
                    //                 logger()->debug('Inventory nav: dashboard url failed', ['err' => $e->getMessage()]);
                    //                 return null;
                    //             }
                    //         })

                    //         ->group(fn() => __('accounting::app.navigation.settings.group'))
                    //         ->sort(7)
                    //         ->visible(fn() => true),
                    // ])

                    // ->navigationItems([
                    //     \Filament\Navigation\NavigationItem::make('inventory-dashboard')
                    //         ->label('Inventory Dashboard')
                    //         ->icon('heroicon-o-collection')
                    //         ->url(function () {
                    //             try {
                    //                 return \Zrm\Inventory\Filament\Pages\InventoryDashboard::getUrl();
                    //             } catch (\Throwable $e) {
                    //                 logger()->debug('Inventory nav: dashboard url failed', ['err' => $e->getMessage()]);
                    //                 return null;
                    //             }
                    //         }),

                    //     \Filament\Navigation\NavigationItem::make('inventory-reports')
                    //         ->label('Inventory Reports')
                    //         ->icon('heroicon-o-chart-bar')
                    //         ->url(function () {
                    //             try {
                    //                 return \Zrm\Inventory\Filament\Pages\InventoryReports::getUrl();
                    //             } catch (\Throwable $e) {
                    //                 logger()->debug('Inventory nav: reports url failed', ['err' => $e->getMessage()]);
                    //                 return null;
                    //             }
                    //         }),

                    //     \Filament\Navigation\NavigationItem::make('inventory-items')
                    //         ->label('Inventory Items')
                    //         ->icon('heroicon-o-cube')
                    //         ->url(function () {
                    //             try {
                    //                 return \Zrm\Inventory\Filament\Resources\InventoryItemResource::getUrl('index');
                    //             } catch (\Throwable $e) {
                    //                 logger()->debug('Inventory nav: items url failed', ['err' => $e->getMessage()]);
                    //                 return null;
                    //             }
                    //         }),

                    //     \Filament\Navigation\NavigationItem::make('inventory-warehouses')
                    //         ->label('Warehouses')
                    //         ->icon('heroicon-o-building')
                    //         ->url(function () {
                    //             try {
                    //                 return \Zrm\Inventory\Filament\Resources\WarehouseResource::getUrl('index');
                    //             } catch (\Throwable $e) {
                    //                 logger()->debug('Inventory nav: warehouses url failed', ['err' => $e->getMessage()]);
                    //                 return null;
                    //             }
                    //         }),

                    //     \Filament\Navigation\NavigationItem::make('inventory-adjustments')
                    //         ->label('Adjustments')
                    //         ->icon('heroicon-o-adjustments-horizontal')
                    //         ->url(function () {
                    //             try {
                    //                 return \Zrm\Inventory\Filament\Resources\InventoryAdjustmentResource::getUrl('index');
                    //             } catch (\Throwable $e) {
                    //                 logger()->debug('Inventory nav: adjustments url failed', ['err' => $e->getMessage()]);
                    //                 return null;
                    //             }
                    //         }),

                    //     \Filament\Navigation\NavigationItem::make('inventory-transfers')
                    //         ->label('Transfers')
                    //         ->icon('heroicon-o-arrow-right-left')
                    //         ->url(function () {
                    //             try {
                    //                 return \Zrm\Inventory\Filament\Resources\InventoryTransferResource::getUrl('index');
                    //             } catch (\Throwable $e) {
                    //                 logger()->debug('Inventory nav: transfers url failed', ['err' => $e->getMessage()]);
                    //                 return null;
                    //             }
                    //         }),
                    // ])
                ;
            });
    }

    public function boot(Panel $panel): void
    {
        //
    }

    protected function getPluginBasePath($path = null): string
    {
        $reflector = new ReflectionClass(get_class($this));

        return dirname($reflector->getFileName()) . ($path ?? '');
    }
}
