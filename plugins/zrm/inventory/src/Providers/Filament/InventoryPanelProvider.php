<?php

namespace Zrm\Inventory\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Zrm\Inventory\Filament\Resources\InventoryAdjustmentResource;
use Zrm\Inventory\Filament\Resources\InventoryItemResource;
use Zrm\Inventory\Filament\Resources\InventoryTransferResource;
use Zrm\Inventory\Filament\Resources\WarehouseResource;

class InventoryPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('inventory')
            ->path('inventory')
            ->brandName('Inventory Module')
            ->resources([
                InventoryItemResource::class,
                WarehouseResource::class,
                InventoryAdjustmentResource::class,
                InventoryTransferResource::class,
            ])
            ->pages([
                // Add your Filament pages here
            ])
            ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make('Inventory')
                    ->label('Inventory')
                    ->icon('heroicon-o-cube')
                    ->items([
                        ...InventoryItemResource::getNavigationItems(),
                        ...WarehouseResource::getNavigationItems(),
                        ...InventoryAdjustmentResource::getNavigationItems(),
                        ...InventoryTransferResource::getNavigationItems(),
                    ]),
            ]);
    }
}
