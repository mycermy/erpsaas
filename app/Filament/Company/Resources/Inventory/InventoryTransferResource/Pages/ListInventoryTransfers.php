<?php

namespace App\Filament\Company\Resources\Inventory\InventoryTransferResource\Pages;

use App\Filament\Company\Resources\Inventory\InventoryTransferResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

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
