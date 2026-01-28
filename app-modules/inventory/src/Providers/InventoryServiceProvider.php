<?php

namespace Modules\Inventory\Providers;

use Filament\Panel;
use Illuminate\Support\ServiceProvider;
use Modules\Inventory\InventoryPlugin;
use Illuminate\Support\Facades\Blade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class InventoryServiceProvider extends ServiceProvider
{
    protected string $name = 'Inventory';

    protected string $nameLower = 'inventory';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->mergeConfigFrom(__DIR__ . '/../../config/config.php', 'inventory');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'inventory');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'inventory');
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
