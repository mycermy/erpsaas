<?php

namespace Zrm\Inventory;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Panel\Concerns\HasNavigation;
use Filament\View\PanelsRenderHook;
use ReflectionClass;

class InventoryPlugin implements Plugin
{
    use HasNavigation;

    protected string $renderHook = PanelsRenderHook::TOPBAR_AFTER;

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
