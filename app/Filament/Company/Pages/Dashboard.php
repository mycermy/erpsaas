<?php

namespace App\Filament\Company\Pages;

use App\Filament\Company\Widgets\Inventory\InventoryStatsWidget;
use App\Filament\Company\Widgets\Inventory\LowStockAlertWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament.company.pages.dashboard';

    protected static ?int $navigationSort = -2;

    public function getWidgets(): array
    {
        return [
            InventoryStatsWidget::class,
            LowStockAlertWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return 1;
    }
}
