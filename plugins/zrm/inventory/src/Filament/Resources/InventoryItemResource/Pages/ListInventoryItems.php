<?php

namespace Zrm\Inventory\Filament\Resources\InventoryItemResource\Pages;

use Zrm\Inventory\Filament\Resources\InventoryItemResource;
use Zrm\Inventory\Filament\Widgets\InventoryStatsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

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
