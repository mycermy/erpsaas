<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     * Core bindings (DateRangeService, CurrencyHandler, Import/Export/Notification models,
     * Filament JS assets) are handled by Erpsaas\Core\CoreServiceProvider.
     */
    public function register(): void {}

    public function boot(): void {}
}
