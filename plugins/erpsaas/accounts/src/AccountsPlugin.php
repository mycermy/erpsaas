<?php

namespace Erpsaas\Accounts;

use Filament\Contracts\Plugin;
use Filament\Panel;

class AccountsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'erpsaas-accounts';
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
                        for: 'Erpsaas\\Accounts\\Filament\\Company\\Clusters'
                    )
                    ->discoverWidgets(
                        in: __DIR__ . '/Filament/Company/Widgets',
                        for: 'Erpsaas\\Accounts\\Filament\\Company\\Widgets'
                    );
            });
    }

    public function boot(Panel $panel): void {}
}
