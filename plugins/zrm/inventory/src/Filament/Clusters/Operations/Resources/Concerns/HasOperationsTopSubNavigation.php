<?php

namespace Zrm\Inventory\Filament\Clusters\Operations\Resources\Concerns;

use Filament\Pages\SubNavigationPosition;
use Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryAdjustmentResource;
use Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryItemResource;
use Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryTransferResource;
use Zrm\Inventory\Filament\Clusters\Operations\Resources\WarehouseResource;

trait HasOperationsTopSubNavigation
{
    public function getSubNavigation(): array
    {
        return forward_static_call([static::class, 'generateNavigationItems'], static::getOperationsSubNavigationComponents());
    }

    public function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Top;
    }

    /**
     * @return array<class-string>
     */
    protected static function getOperationsSubNavigationComponents(): array
    {
        return [
            InventoryItemResource::class,
            WarehouseResource::class,
            InventoryAdjustmentResource::class,
            InventoryTransferResource::class,
        ];
    }
}
