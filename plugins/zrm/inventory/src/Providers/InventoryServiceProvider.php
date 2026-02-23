<?php

namespace Zrm\Inventory\Providers;

use Filament\Panel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Zrm\Inventory\InventoryPlugin;
use Illuminate\Support\Facades\Blade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Zrm\Inventory\Filament\Pages\InventoryDashboard;

class InventoryServiceProvider extends ServiceProvider
{
    protected string $name = 'Inventory';

    protected string $nameLower = 'inventory';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
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
        \Illuminate\Support\Facades\Route::matched(function ($event) {
            try {
                $route = $event->route;
                $tenantParam = $route?->parameter('tenant') ?? $route?->parameter('company');

                if ($tenantParam && ! filament()->getTenant()) {
                    $company = $tenantParam instanceof \App\Models\Company ? $tenantParam : \App\Models\Company::find($tenantParam);

                    if ($company) {
                        \Filament\Facades\Filament::setTenant($company);
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

            // Ensure the company panel exposes an Inventory group (label + icon)
            // Resources already declare `protected static ?string $navigationGroup = 'Inventory'` —
            // this merely ensures the group label/icon exist without eagerly resolving pages.
            $panel->when(fn (Panel $panel) => $panel->getId() === 'company', function (Panel $panel): void {
                $panel
                ->navigation(function (\Filament\Navigation\NavigationBuilder $builder): \Filament\Navigation\NavigationBuilder {
                    return $builder->group(
                        \Filament\Navigation\NavigationGroup::make('Inventory')
                            ->label('Inventory')
                            ->icon('heroicon-o-cube')
                    );
                });

                // Add Inventory navigation items from the plugin (done here so the plugin
                // can fully control its navigation without modifying the core CompanyPanelProvider).
                // Use closures for `url` and `shouldShow` so evaluation is deferred until render-time
                // (after tenant binding). Defensive try/catch prevents a thrown URL generation
                // error from breaking the whole sidebar.
                $panel->navigation(function (\Filament\Navigation\NavigationBuilder $builder): \Filament\Navigation\NavigationBuilder {
                    return $builder->group(
                        \Filament\Navigation\NavigationGroup::make('Inventory')
                            ->label('Inventory')
                            ->icon('heroicon-o-cube')
                            ->items([
                                \Filament\Navigation\NavigationItem::make('inventory-dashboard')
                                    ->label('Inventory Dashboard')
                                    ->icon('heroicon-o-collection')
                                    ->url(function () {
                                        try {
                                            return \Zrm\Inventory\Filament\Pages\InventoryDashboard::getUrl();
                                        } catch (\Throwable $e) {
                                            logger()->debug('Inventory nav: dashboard url failed', ['err' => $e->getMessage()]);
                                            return null;
                                        }
                                    }),

                                \Filament\Navigation\NavigationItem::make('inventory-reports')
                                    ->label('Inventory Reports')
                                    ->icon('heroicon-o-chart-bar')
                                    ->url(function () {
                                        try {
                                            return \Zrm\Inventory\Filament\Pages\InventoryReports::getUrl();
                                        } catch (\Throwable $e) {
                                            logger()->debug('Inventory nav: reports url failed', ['err' => $e->getMessage()]);
                                            return null;
                                        }
                                    }),

                                \Filament\Navigation\NavigationItem::make('inventory-items')
                                    ->label('Inventory Items')
                                    ->icon('heroicon-o-cube')
                                    ->url(function () {
                                        try {
                                            return \Zrm\Inventory\Filament\Resources\InventoryItemResource::getUrl('index');
                                        } catch (\Throwable $e) {
                                            logger()->debug('Inventory nav: items url failed', ['err' => $e->getMessage()]);
                                            return null;
                                        }
                                    })
                                    ->shouldShow(function () {
                                        try {
                                            return \Zrm\Inventory\Filament\Resources\InventoryItemResource::canViewAny(Auth::user());
                                        } catch (\Throwable $e) {
                                            return false;
                                        }
                                    }),

                                \Filament\Navigation\NavigationItem::make('inventory-warehouses')
                                    ->label('Warehouses')
                                    ->icon('heroicon-o-building')
                                    ->url(function () {
                                        try {
                                            return \Zrm\Inventory\Filament\Resources\WarehouseResource::getUrl('index');
                                        } catch (\Throwable $e) {
                                            logger()->debug('Inventory nav: warehouses url failed', ['err' => $e->getMessage()]);
                                            return null;
                                        }
                                    })
                                    ->shouldShow(function () {
                                        try {
                                            return \Zrm\Inventory\Filament\Resources\WarehouseResource::canViewAny(Auth::user());
                                        } catch (\Throwable $e) {
                                            return false;
                                        }
                                    }),

                                \Filament\Navigation\NavigationItem::make('inventory-adjustments')
                                    ->label('Adjustments')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->url(function () {
                                        try {
                                            return \Zrm\Inventory\Filament\Resources\InventoryAdjustmentResource::getUrl('index');
                                        } catch (\Throwable $e) {
                                            logger()->debug('Inventory nav: adjustments url failed', ['err' => $e->getMessage()]);
                                            return null;
                                        }
                                    })
                                    ->shouldShow(function () {
                                        try {
                                            return \Zrm\Inventory\Filament\Resources\InventoryAdjustmentResource::canViewAny(Auth::user());
                                        } catch (\Throwable $e) {
                                            return false;
                                        }
                                    }),

                                \Filament\Navigation\NavigationItem::make('inventory-transfers')
                                    ->label('Transfers')
                                    ->icon('heroicon-o-arrow-right-left')
                                    ->url(function () {
                                        try {
                                            return \Zrm\Inventory\Filament\Resources\InventoryTransferResource::getUrl('index');
                                        } catch (\Throwable $e) {
                                            logger()->debug('Inventory nav: transfers url failed', ['err' => $e->getMessage()]);
                                            return null;
                                        }
                                    })
                                    ->shouldShow(function () {
                                        try {
                                            return \Zrm\Inventory\Filament\Resources\InventoryTransferResource::canViewAny(Auth::user());
                                        } catch (\Throwable $e) {
                                            return false;
                                        }
                                    }),
                            ])
                    );
                });

                // Runtime safeguard: Filament's application providers (app/Providers/Filament/*)
                // may register the `company` panel *after* package registration — which can
                // overwrite earlier navigation callbacks. To guarantee the Inventory group
                // and its items are present in the final rendered sidebar, merge our
                // navigation during the Filament "serving" event (request-time).
                // This is intentionally idempotent and defensive — safe for repeated calls.
                \Filament\Facades\Filament::serving(function ($event): void {
                    try {
                        $panel = $event->panel ?? null;

                        if (! $panel || $panel->getId() !== 'company') {
                            return;
                        }

                        // If the Inventory group already exists, ensure our items are present
                        // (we register the same deferred closures as above). Otherwise add
                        // the whole group. Using ->navigation() here appends a render-time
                        // builder so it cannot be overwritten by earlier provider configuration.
                        $panel->navigation(function (\Filament\Navigation\NavigationBuilder $builder): \Filament\Navigation\NavigationBuilder {
                            return $builder->group(
                                \Filament\Navigation\NavigationGroup::make('Inventory')
                                    ->label('Inventory')
                                    ->icon('heroicon-o-cube')
                                    ->items([
                                        \Filament\Navigation\NavigationItem::make('inventory-dashboard')
                                            ->label('Inventory Dashboard')
                                            ->icon('heroicon-o-collection')
                                            ->url(function () {
                                                try {
                                                    return \Zrm\Inventory\Filament\Pages\InventoryDashboard::getUrl();
                                                } catch (\Throwable $e) {
                                                    logger()->debug('Inventory nav: dashboard url failed (serving)', ['err' => $e->getMessage()]);
                                                    return null;
                                                }
                                            }),

                                        \Filament\Navigation\NavigationItem::make('inventory-reports')
                                            ->label('Inventory Reports')
                                            ->icon('heroicon-o-chart-bar')
                                            ->url(function () {
                                                try {
                                                    return \Zrm\Inventory\Filament\Pages\InventoryReports::getUrl();
                                                } catch (\Throwable $e) {
                                                    logger()->debug('Inventory nav: reports url failed (serving)', ['err' => $e->getMessage()]);
                                                    return null;
                                                }
                                            }),

                                        \Filament\Navigation\NavigationItem::make('inventory-items')
                                            ->label('Inventory Items')
                                            ->icon('heroicon-o-cube')
                                            ->url(function () {
                                                try {
                                                    return \Zrm\Inventory\Filament\Resources\InventoryItemResource::getUrl('index');
                                                } catch (\Throwable $e) {
                                                    logger()->debug('Inventory nav: items url failed (serving)', ['err' => $e->getMessage()]);
                                                    return null;
                                                }
                                            })
                                            ->shouldShow(function () {
                                                try {
                                                    return \Zrm\Inventory\Filament\Resources\InventoryItemResource::canViewAny(Auth::user());
                                                } catch (\Throwable $e) {
                                                    return false;
                                                }
                                            }),

                                        \Filament\Navigation\NavigationItem::make('inventory-warehouses')
                                            ->label('Warehouses')
                                            ->icon('heroicon-o-building')
                                            ->url(function () {
                                                try {
                                                    return \Zrm\Inventory\Filament\Resources\WarehouseResource::getUrl('index');
                                                } catch (\Throwable $e) {
                                                    logger()->debug('Inventory nav: warehouses url failed (serving)', ['err' => $e->getMessage()]);
                                                    return null;
                                                }
                                            })
                                            ->shouldShow(function () {
                                                try {
                                                    return \Zrm\Inventory\Filament\Resources\WarehouseResource::canViewAny(Auth::user());
                                                } catch (\Throwable $e) {
                                                    return false;
                                                }
                                            }),

                                        \Filament\Navigation\NavigationItem::make('inventory-adjustments')
                                            ->label('Adjustments')
                                            ->icon('heroicon-o-adjustments-horizontal')
                                            ->url(function () {
                                                try {
                                                    return \Zrm\Inventory\Filament\Resources\InventoryAdjustmentResource::getUrl('index');
                                                } catch (\Throwable $e) {
                                                    logger()->debug('Inventory nav: adjustments url failed (serving)', ['err' => $e->getMessage()]);
                                                    return null;
                                                }
                                            })
                                            ->shouldShow(function () {
                                                try {
                                                    return \Zrm\Inventory\Filament\Resources\InventoryAdjustmentResource::canViewAny(Auth::user());
                                                } catch (\Throwable $e) {
                                                    return false;
                                                }
                                            }),

                                        \Filament\Navigation\NavigationItem::make('inventory-transfers')
                                            ->label('Transfers')
                                            ->icon('heroicon-o-arrow-right-left')
                                            ->url(function () {
                                                try {
                                                    return \Zrm\Inventory\Filament\Resources\InventoryTransferResource::getUrl('index');
                                                } catch (\Throwable $e) {
                                                    logger()->debug('Inventory nav: transfers url failed (serving)', ['err' => $e->getMessage()]);
                                                    return null;
                                                }
                                            })
                                            ->shouldShow(function () {
                                                try {
                                                    return \Zrm\Inventory\Filament\Resources\InventoryTransferResource::canViewAny(Auth::user());
                                                } catch (\Throwable $e) {
                                                    return false;
                                                }
                                            }),
                                    ])
                            );
                        });
                    } catch (\Throwable $e) {
                        logger()->warning('Inventory plugin: failed to merge navigation during Filament serving', ['err' => $e->getMessage()]);
                    }
                });

                // DEBUG HOOK — temporary. Set INVENTORY_DUMP_PANEL=1 to `dd($panel)` when the
                // company panel is configured. Safe by default (only runs when env var is set).
                if ((bool) env('INVENTORY_DUMP_PANEL', false)) {
                    // Dump the entire Panel object so you can inspect navigation, pages, etc.
                    dd($panel);
                }

                // Safer alternative that writes a compact summary to the log when enabled.
                if ((bool) env('INVENTORY_LOG_PANEL', false)) {
                    try {
                        $ro = new \ReflectionObject($panel);
                        $props = [];

                        foreach ($ro->getProperties() as $p) {
                            $p->setAccessible(true);
                            $val = $p->getValue($panel);

                            $props[$p->getName()] = is_object($val) ? get_class($val) : $val;
                        }

                        logger()->debug('Inventory plugin — company Panel snapshot', ['panel_props' => $props]);
                    } catch (\Throwable $e) {
                        logger()->warning('Failed to log Inventory panel snapshot', ['err' => $e->getMessage()]);
                    }
                }
            });
        });
    }
}
