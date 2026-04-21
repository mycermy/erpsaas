<?php

namespace Erpsaas\Dashboard;

use Filament\Contracts\Plugin;
use Filament\Panel;

class DashboardPlugin implements Plugin
{
    public function getId(): string
    {
        return 'erpsaas-dashboard';
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
                        for: 'Erpsaas\\Dashboard\\Filament\\Company\\Clusters'
                    )
                    ->discoverPages(
                        in: __DIR__ . '/Filament/Company/Pages',
                        for: 'Erpsaas\\Dashboard\\Filament\\Company\\Pages'
                    )
                    ->discoverWidgets(
                        in: __DIR__ . '/Filament/Company/Widgets',
                        for: 'Erpsaas\\Dashboard\\Filament\\Company\\Widgets'
                    );
            });
    }

    public function boot(Panel $panel): void {}
}
