<?php

namespace Erpsaas\Purchases;

use Filament\Panel;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PurchasesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('erpsaas-purchases')
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations([]);
    }

    public function packageRegistered(): void
    {
        Panel::configureUsing(function (Panel $panel): void {
            $panel->plugin(PurchasesPlugin::make());
        });
    }

    public function packageBooted(): void {}
}
