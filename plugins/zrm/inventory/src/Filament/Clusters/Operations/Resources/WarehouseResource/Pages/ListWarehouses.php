<?php

namespace Zrm\Inventory\Filament\Clusters\Operations\Resources\WarehouseResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Zrm\Inventory\Filament\Clusters\Operations\Resources\Concerns\HasOperationsTopSubNavigation;
use Zrm\Inventory\Filament\Clusters\Operations\Resources\WarehouseResource;

class ListWarehouses extends ListRecords
{
    use HasOperationsTopSubNavigation;

    protected static string $resource = WarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
