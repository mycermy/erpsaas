<?php

namespace Erpsaas\Core;

use Filament\Contracts\Plugin;
use Filament\Panel;

class CorePlugin implements Plugin
{
    public function getId(): string
    {
        return 'erpsaas-core';
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
                    ->discoverResources(
                        in: __DIR__ . '/Filament/Company/Resources/Core',
                        for: 'Erpsaas\\Core\\Filament\\Company\\Resources\\Core'
                    )
                    ->discoverResources(
                        in: __DIR__ . '/Filament/Company/Resources/Common',
                        for: 'Erpsaas\\Core\\Filament\\Company\\Resources\\Common'
                    )
                    ->discoverPages(
                        in: __DIR__ . '/Filament/Company/Pages',
                        for: 'Erpsaas\\Core\\Filament\\Company\\Pages'
                    )
                    ->discoverClusters(
                        in: __DIR__ . '/Filament/Company/Clusters',
                        for: 'Erpsaas\\Core\\Filament\\Company\\Clusters'
                    )
                    ->discoverWidgets(
                        in: __DIR__ . '/Filament/Company/Widgets',
                        for: 'Erpsaas\\Core\\Filament\\Company\\Widgets'
                    );
            })
            ->when($panel->getId() === 'user', function (Panel $panel): void {
                $panel
                    ->discoverClusters(
                        in: __DIR__ . '/Filament/User/Clusters',
                        for: 'Erpsaas\\Core\\Filament\\User\\Clusters'
                    );
            });
    }

    public function boot(Panel $panel): void {}
}
