<?php

namespace Modules\Inventory;

use Filament\Contracts\Plugin;
use Filament\Panel;

class InventoryPlugin implements Plugin
{
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
            ->discoverResources(
                in: __DIR__ . '/Filament/Resources',
                for: 'Modules\\Inventory\\Filament\\Resources',
            )
            ->discoverPages(
                in: __DIR__ . '/Filament/Pages',
                for: 'Modules\\Inventory\\Filament\\Pages',
            )
            ->discoverWidgets(
                in: __DIR__ . '/Filament/Widgets',
                for: 'Modules\\Inventory\\Filament\\Widgets',
            );
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
