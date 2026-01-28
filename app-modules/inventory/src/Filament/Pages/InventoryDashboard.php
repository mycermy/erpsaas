<?php

namespace Modules\Inventory\Filament\Pages;

use Modules\Inventory\Filament\Widgets\InventoryStatsWidget;
use Modules\Inventory\Filament\Widgets\LowStockAlertWidget;
use Filament\Pages\Page;

class InventoryDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'inventory::filament.pages.dashboard';

    protected static ?string $slug = 'inventory';

    protected static ?int $navigationSort = -2;
}
