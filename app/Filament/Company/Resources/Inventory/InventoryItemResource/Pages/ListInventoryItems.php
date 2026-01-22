<?php

namespace App\Filament\Company\Resources\Inventory\InventoryItemResource\Pages;

use App\Filament\Company\Resources\Inventory\InventoryItemResource;
use App\Filament\Company\Widgets\Inventory\InventoryStatsWidget;
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
