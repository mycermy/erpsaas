<?php

namespace App\Filament\Company\Resources\Inventory\WarehouseResource\Pages;

use App\Filament\Company\Resources\Inventory\WarehouseResource;
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
