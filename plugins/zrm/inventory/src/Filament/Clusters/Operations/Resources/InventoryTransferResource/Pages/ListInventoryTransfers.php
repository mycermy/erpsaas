<?php

namespace Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryTransferResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryTransferResource;

class ListInventoryTransfers extends ListRecords
{
    protected static string $resource = InventoryTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
