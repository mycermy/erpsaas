<?php

namespace App\Filament\Company\Widgets\Inventory;

use App\Models\Inventory\InventoryBatch;
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

        // Total inventory value - Calculate from actual batch costs
        // This ensures FIFO/LIFO items use actual costs instead of averaged costs
        $totalValue = InventoryBatch::whereHas('inventoryItem', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })
            ->where('quantity_remaining', '>', 0)
            ->get()
            ->sum(function ($batch) {
                return $batch->quantity_remaining * $batch->unit_cost;
            });

        // Total items
        $totalItems = InventoryItem::where('company_id', $companyId)
            ->where('active', true)
            ->count();

        // Low stock items count (join stock levels so we can compare against reorder_level)
        $lowStockCount = InventoryItem::where('inventory_items.company_id', $companyId)
            ->where('inventory_items.active', true)
            ->join('inventory_stock_levels', 'inventory_items.id', '=', 'inventory_stock_levels.inventory_item_id')
            ->whereColumn('inventory_stock_levels.quantity_available', '<=', 'inventory_items.reorder_level')
            ->where('inventory_stock_levels.quantity_available', '>', 0)
            ->distinct()
            ->count('inventory_items.id');

        // Out of stock items count
        $outOfStockCount = InventoryItem::where('company_id', $companyId)
            ->where('inventory_items.active', true)
            ->whereHas('stockLevels', function ($query) {
                $query->where('quantity_available', '<=', 0);
            })
            ->count();

        return [
            Stat::make('Total Inventory Value', money($totalValue, 'MYR'))
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
                    'tableFilters' => ['low_stock' => ['value' => true]],
                ])),

            Stat::make('Out of Stock', $outOfStockCount)
                ->description('Items with no available quantity')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($outOfStockCount > 0 ? 'danger' : 'success'),
        ];
    }
}
