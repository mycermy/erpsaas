<?php

namespace Erpsaas\Hr;

use Filament\Contracts\Plugin;
use Filament\Panel;

class HrPlugin implements Plugin
{
    public function getId(): string
    {
        return 'erpsaas-hr';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function register(Panel $panel): void
    {
        $panel->when($panel->getId() === 'company', function (Panel $panel): void {
            $panel
                ->discoverClusters(
                    in: __DIR__ . '/Filament/Company/Clusters',
                    for: 'Erpsaas\\Hr\\Filament\\Company\\Clusters'
                )
                ->discoverResources(
                    in: __DIR__ . '/Filament/Company/Resources/Hr',
                    for: 'Erpsaas\\Hr\\Filament\\Company\\Resources\\Hr'
                );
        });
    }

    public function boot(Panel $panel): void {}
}
