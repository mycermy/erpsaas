<?php

namespace Zrm\Inventory\Filament\Clusters\Operations\Resources\WarehouseResource\Pages;

use Zrm\Inventory\Filament\Clusters\Operations\Resources\WarehouseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWarehouses extends ListRecords
{
    protected static string $resource = WarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
