<?php

namespace Zrm\Inventory\Filament\Clusters\Operations\Resources\WarehouseResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Zrm\Inventory\Filament\Clusters\Operations\Resources\WarehouseResource;

class EditWarehouse extends EditRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
