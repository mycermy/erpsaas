<?php

namespace Erpsaas\Sales;

use Filament\Contracts\Plugin;
use Filament\Panel;

class SalesPlugin implements Plugin
{
    public function getId(): string
    {
        return 'erpsaas-sales';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function register(Panel $panel): void
    {
        $panel
            ->when($panel->getId() === 'company', function (Panel $panel): void {
                $panel
                    ->discoverClusters(
                        in: __DIR__ . '/Filament/Company/Clusters',
                        for: 'Erpsaas\\Sales\\Filament\\Company\\Clusters'
                    )
                    ->discoverResources(
                        in: __DIR__ . '/Filament/Company/Resources/Sales',
                        for: 'Erpsaas\\Sales\\Filament\\Company\\Resources\\Sales'
                    )
                    ->discoverWidgets(
                        in: __DIR__ . '/Filament/Company/Widgets',
                        for: 'Erpsaas\\Sales\\Filament\\Company\\Widgets'
                    );
            });
    }

    public function boot(Panel $panel): void {}
}
