<?php

namespace Zrm\Inventory;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
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
        return 'zrm-inventory';
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
                    // // Custom navigation groups for better organization
                    // ->navigationGroups([
                    //     NavigationGroup::make('Dashboard')
                    //         ->label('Dashboard')
                    //         ->icon('heroicon-o-home-modern'),
                    //     NavigationGroup::make('Sales')
                    //         ->label('Sales')
                    //         ->icon('heroicon-o-shopping-cart'),
                    //     NavigationGroup::make('Purchases')
                    //         ->label('Purchases')
                    //         ->icon('heroicon-o-shopping-bag'),
                    //     NavigationGroup::make('Inventory')
                    //         ->label('Inventory')
                    //         ->icon('heroicon-o-cube'),
                    //     NavigationGroup::make('Accounting')
                    //         ->label('Accounting')
                    //         ->icon('heroicon-o-calculator'),
                    //     NavigationGroup::make('Banking')
                    //         ->label('Banking')
                    //         ->icon('heroicon-o-building-library'),
                    //     NavigationGroup::make('Settings')
                    //         ->label('Settings')
                    //         ->icon('heroicon-o-squares-2x2'),
                    // ])
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
                    );
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
