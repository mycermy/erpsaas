<?php

namespace App\Filament\Company\Widgets\Inventory;

use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryStockLevel;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InventoryStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $companyId = filament()->getTenant()->id;

        // Total inventory value
                    $totalValue = InventoryStockLevel::whereHas('inventoryItem', function ($q) {
                $q->where('company_id', filament()->getTenant()->id);
            })->get()->sum(function ($stockLevel) {
                if (!$stockLevel->average_cost) {
                    return 0;
                }
                $cost = is_object($stockLevel->average_cost) 
                    ? $stockLevel->average_cost->getAmount() 
                    : $stockLevel->average_cost;
                return $stockLevel->quantity_on_hand * $cost;
            }) / 100;

        // Total items
        $totalItems = InventoryItem::where('company_id', $companyId)
            ->where('active', true)
            ->count();

        // Low stock items count
        $lowStockCount = InventoryItem::where('company_id', $companyId)
            ->where('active', true)
            ->whereHas('stockLevels', function ($query) {
                $query->whereColumn('quantity_available', '<=', 'inventory_items.reorder_level')
                    ->where('quantity_available', '>', 0);
            })
            ->count();

        // Out of stock items count
        $outOfStockCount = InventoryItem::where('company_id', $companyId)
            ->where('active', true)
            ->whereHas('stockLevels', function ($query) {
                $query->where('quantity_available', '<=', 0);
            })
            ->count();

        return [
            Stat::make('Total Inventory Value', money($totalValue, 'USD'))
                ->description('Across all warehouses')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Active Items', $totalItems)
                ->description('Inventory-tracked items')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),

            Stat::make('Low Stock Alerts', $lowStockCount)
                ->description('Items below reorder level')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'warning' : 'success')
                ->url(\App\Filament\Company\Resources\Inventory\InventoryItemResource::getUrl('index', [
                    'tenant' => filament()->getTenant(),
                    'tableFilters' => ['low_stock' => ['isActive' => true]],
                ])),

            Stat::make('Out of Stock', $outOfStockCount)
                ->description('Items with no available quantity')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($outOfStockCount > 0 ? 'danger' : 'success'),
        ];
    }
}
