<?php

namespace Zrm\Inventory\Filament\Pages;

use Zrm\Inventory\Filament\Widgets\InventoryStatsWidget;
use Zrm\Inventory\Filament\Widgets\LowStockAlertWidget;
use Filament\Pages\Page;

class InventoryDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'inventory::filament.pages.dashboard';

    protected static ?string $slug = 'inventory/dashboard';

    protected static ?int $navigationSort = 0;

    public function getWidgets(): array
    {
        return [
            \Zrm\Inventory\Filament\Widgets\InventoryStatsWidget::class,
            \Zrm\Inventory\Filament\Widgets\LowStockAlertWidget::class,
        ];
    }

    // public function getBreadcrumbs(): array
    // {
    //     return [
    //         'Inventory' => static::getUrl(),
    //     ];
    // }
}
