<?php

namespace Zrm\Inventory\Filament\Pages;

use Filament\Pages\Page;
use Zrm\Inventory\Filament\Widgets\InventoryStatsWidget;
use Zrm\Inventory\Filament\Widgets\LowStockAlertWidget;

class InventoryDashboard extends Page
{
    // protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $slug = 'inventory/dashboard';

    protected static string $view = 'inventory::filament.pages.dashboard';

    // protected static ?int $navigationSort = 0;

    protected static function getPagePermission(): ?string
    {
        return 'page_inventory_overview';
    }

    public static function getNavigationLabel(): string
    {
        return __('inventory::filament/pages/overview.navigation.title');
    }

    public static function getNavigationGroup(): string
    {
        return __('inventory::filament/pages/overview.navigation.group');
    }

    public function getWidgets(): array
    {
        return [
            InventoryStatsWidget::class,
            LowStockAlertWidget::class,
        ];
    }

    // public function getBreadcrumbs(): array
    // {
    //     return [
    //         'Inventory' => static::getUrl(),
    //     ];
    // }
}
