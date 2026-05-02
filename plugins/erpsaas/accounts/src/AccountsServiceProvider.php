<?php

namespace Erpsaas\Accounts;

use Filament\Panel;
use Illuminate\Support\Facades\View;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class AccountsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('erpsaas-accounts')
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations([]);
    }

    public function packageRegistered(): void
    {
        Panel::configureUsing(function (Panel $panel): void {
            $panel->plugin(AccountsPlugin::make());
        });
    }

    public function packageBooted(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        View::addLocation(__DIR__ . '/../resources/views');
    }
}
