<?php

namespace App\Providers;

use Erpsaas\Core\Models\Locale\Country;
use Erpsaas\Core\Models\Locale\State;
use Illuminate\Support\ServiceProvider;
use Squire\Repository;

class SquireServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Repository::registerSource(Country::class, 'en', resource_path('data/countries.csv'));
        Repository::registerSource(State::class, 'en', resource_path('data/states.csv'));
    }
}
