<?php

namespace Erpsaas\Sales;

use Filament\Panel;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SalesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('erpsaas-sales')
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations([]);
    }

    public function packageRegistered(): void
    {
        Panel::configureUsing(function (Panel $panel): void {
            $panel->plugin(SalesPlugin::make());
        });
    }

    public function packageBooted(): void {}
}
