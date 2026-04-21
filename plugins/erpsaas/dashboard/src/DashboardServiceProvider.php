<?php

namespace Erpsaas\Dashboard;

use Filament\Panel;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DashboardServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('erpsaas-dashboard')
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        Panel::configureUsing(function (Panel $panel): void {
            $panel->plugin(DashboardPlugin::make());
        });
    }

    public function packageBooted(): void {}
}
