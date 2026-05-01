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
    public function register(): void
    {
        foreach ($this->discoverLocalPluginProviders() as $provider) {
            if (! class_exists($provider)) {
                continue;
            }

            if ($this->app instanceof \Illuminate\Foundation\Application) {
                if ($this->app->getProvider($provider) !== null) {
                    continue;
                }
            }

            $this->app->register($provider);
        }
    }

    public function boot(): void {}

    /**
     * Discover local plugin service providers from plugins/<vendor>/<package>/composer.json.
     */
    protected function discoverLocalPluginProviders(): array
    {
        $providers = [];

        foreach (glob(base_path('plugins/*/*/composer.json')) as $composerJsonPath) {
            $contents = file_get_contents($composerJsonPath);

            if (! is_string($contents)) {
                continue;
            }

            $composerData = json_decode($contents, true);

            if (! is_array($composerData)) {
                continue;
            }

            foreach ((array) data_get($composerData, 'extra.laravel.providers', []) as $provider) {
                if (! is_string($provider) || in_array($provider, $providers, true)) {
                    continue;
                }

                $providers[] = $provider;
            }
        }

        return $providers;
    }
}
