<?php

namespace Erpsaas\Core;

use Erpsaas\Core\Contracts\CurrencyHandler;
use Erpsaas\Core\Http\Responses\LoginRedirectResponse;
use Erpsaas\Core\Livewire\UpdatePassword;
use Erpsaas\Core\Livewire\UpdateProfileInformation;
use Erpsaas\Core\Models\Export;
use Erpsaas\Core\Models\Import;
use Erpsaas\Core\Models\Notification;
use Erpsaas\Core\Services\CurrencyService;
use Erpsaas\Core\Services\DateRangeService;
use Filament\Actions\Exports\Models\Export as BaseExport;
use Filament\Actions\Imports\Models\Import as BaseImport;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Livewire\Notifications;
use Filament\Panel;
use Filament\Support\Assets\Js;
use Filament\Support\Enums\Alignment;
use Filament\Support\Facades\FilamentAsset;
use GuzzleHttp\Client;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CoreServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('erpsaas-core')
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations([]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(DateRangeService::class);
        $this->app->singleton(LoginResponse::class, LoginRedirectResponse::class);

        $this->app->bind(CurrencyHandler::class, function (Application $app) {
            $apiKey = config('services.currency_api.key');
            $baseUrl = config('services.currency_api.base_url');
            $client = $app->make(Client::class);

            return new CurrencyService($apiKey, $baseUrl, $client);
        });

        Panel::configureUsing(function (Panel $panel): void {
            $panel->plugin(CorePlugin::make());
        });
    }

    public function packageBooted(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Keep legacy un-namespaced view() calls working after moving views into plugin.
        View::addLocation(__DIR__ . '/../resources/views');

        // Provide package view overrides from within the plugin for portability.
        // Prepend ensures these overrides win over package defaults.
        View::prependNamespace('filament-panels', __DIR__ . '/../resources/views/vendor/filament-panels');
        View::prependNamespace('filament-clusters', __DIR__ . '/../resources/views/vendor/filament-clusters');
        View::prependNamespace('radio-deck', __DIR__ . '/../resources/views/vendor/radio-deck');

        // Bind custom Import and Export models
        $this->app->bind(BaseImport::class, Import::class);
        $this->app->bind(BaseExport::class, Export::class);

        // Bind custom Notification model
        $this->app->bind(DatabaseNotification::class, Notification::class);

        Notifications::alignment(Alignment::Center);

        FilamentAsset::register([
            Js::make('top-navigation', base_path('resources/js/top-navigation.js')),
            Js::make('history-fix', base_path('resources/js/history-fix.js')),
            Js::make('custom-print', base_path('resources/js/custom-print.js')),
        ], 'erpsaas-core');

        Livewire::component('erpsaas.core.livewire.update-profile-information', UpdateProfileInformation::class);
        Livewire::component('erpsaas.core.livewire.update-password', UpdatePassword::class);
    }
}
