<?php

namespace Zrm\Inventory\Providers;

use App\Models\Company;
use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Accounts\Models\Accounting\Invoice;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use Zrm\Inventory\InventoryPlugin;

class InventoryServiceProvider extends ServiceProvider
{
    protected string $name = 'Inventory';

    protected string $nameLower = 'inventory';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        Relation::morphMap([
            Invoice::class => Invoice::class,
            'App\\Models\\Accounting\\Invoice' => Invoice::class,
            Bill::class => Bill::class,
            'App\\Models\\Accounting\\Bill' => Bill::class,
        ], true);

        $reflector = new ReflectionClass($this);
        $basePath = dirname($reflector->getFileName(), 3);

        $this->loadMigrationsFrom($basePath . '/database/migrations');
        $this->mergeConfigFrom($basePath . '/config/config.php', 'inventory');

        // Register views both the Laravel-recommended way and directly on the view finder
        // (defensive: some packages rebind the finder later). This is idempotent.
        $this->loadViewsFrom($basePath . '/resources/views', 'inventory');
        $this->app['view.finder']->addNamespace('inventory', $basePath . '/resources/views');

        // Register translations similarly and log a helpful warning if the paths are missing.
        if (is_dir($basePath . '/resources/lang')) {
            $this->loadTranslationsFrom($basePath . '/resources/lang', 'inventory');
            $this->app['translator']->addNamespace('inventory', $basePath . '/resources/lang');
        } else {
            logger()->warning('Inventory plugin: translations directory not found', ['path' => $basePath . '/resources/lang']);
        }

        // Plugin-level early tenant binding: when a route with a `company`/`tenant`
        // parameter is matched, ensure Filament's tenant is set so resource
        // navigation URL generation does not fail during panel rendering.
        Route::matched(function ($event) {
            try {
                $route = $event->route;
                $tenantParam = $route?->parameter('tenant') ?? $route?->parameter('company');

                if ($tenantParam && ! filament()->getTenant()) {
                    $company = $tenantParam instanceof Company ? $tenantParam : Company::find($tenantParam);

                    if ($company) {
                        Filament::setTenant($company);
                    }
                }
            } catch (\Throwable $e) {
                // Non-fatal — log for diagnostics in dev only.
                logger()->debug('Inventory plugin early-tenant bind failed', ['err' => $e->getMessage()]);
            }
        });
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);

        $this->packageRegistered();
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }

    public function packageRegistered(): void
    {
        Panel::configureUsing(function (Panel $panel): void {
            $panel->plugin(InventoryPlugin::make());
        });
    }
}
