<?php

namespace Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryItemResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryItemResource;
use Zrm\Inventory\Filament\Widgets\InventoryStatsWidget;

class ListInventoryItems extends ListRecords
{
    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            InventoryStatsWidget::class,
        ];
    }
}
