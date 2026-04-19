<?php

namespace Erpsaas\Purchases;

use Filament\Contracts\Plugin;
use Filament\Panel;

class PurchasesPlugin implements Plugin
{
    public function getId(): string
    {
        return 'erpsaas-purchases';
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
                        for: 'Erpsaas\\Purchases\\Filament\\Company\\Clusters'
                    )
                    ->discoverResources(
                        in: __DIR__ . '/Filament/Company/Resources/Purchases',
                        for: 'Erpsaas\\Purchases\\Filament\\Company\\Resources\\Purchases'
                    )
                    ->discoverWidgets(
                        in: __DIR__ . '/Filament/Company/Widgets',
                        for: 'Erpsaas\\Purchases\\Filament\\Company\\Widgets'
                    );
            });
    }

    public function boot(Panel $panel): void {}
}
