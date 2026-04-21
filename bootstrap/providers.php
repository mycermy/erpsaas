<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\CompanyPanelProvider::class,
    App\Providers\Filament\UserPanelProvider::class,
    App\Providers\Faker\FakerServiceProvider::class,
    App\Providers\MacroServiceProvider::class,
    App\Providers\SquireServiceProvider::class,
    App\Providers\TranslationServiceProvider::class,
    // CurrencyServiceProvider merged into Erpsaas\Core\CoreServiceProvider
    // Plugin ServiceProviders are auto-discovered via composer merge-plugin
    Erpsaas\Core\CoreServiceProvider::class,
    Erpsaas\Accounts\AccountsServiceProvider::class,
    Erpsaas\Dashboard\DashboardServiceProvider::class,
    Erpsaas\Purchases\PurchasesServiceProvider::class,
    Erpsaas\Sales\SalesServiceProvider::class,
];
