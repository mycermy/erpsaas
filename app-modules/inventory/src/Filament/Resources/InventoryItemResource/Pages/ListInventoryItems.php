<?php

namespace Modules\Inventory\Filament\Resources\InventoryItemResource\Pages;

use Modules\Inventory\Filament\Resources\InventoryItemResource;
use Modules\Inventory\Filament\Widgets\InventoryStatsWidget;
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
