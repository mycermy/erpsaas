<?php

namespace Modules\Inventory;

use Filament\Contracts\Plugin;
use Filament\Panel;

class InventoryPlugin implements Plugin
{
    public function getId(): string
    {
        return 'inventory';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                \Modules\Inventory\Filament\Resources\InventoryItemResource::class,
                \Modules\Inventory\Filament\Resources\WarehouseResource::class,
                \Modules\Inventory\Filament\Resources\InventoryAdjustmentResource::class,
                \Modules\Inventory\Filament\Resources\InventoryTransferResource::class,
            ])
            ->pages([
                \Modules\Inventory\Filament\Pages\InventoryDashboard::class,
                \Modules\Inventory\Filament\Pages\InventoryReports::class,
            ])
            ->widgets([
                \Modules\Inventory\Filament\Widgets\InventoryStatsWidget::class,
                \Modules\Inventory\Filament\Widgets\LowStockAlertWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
