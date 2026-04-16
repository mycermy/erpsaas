<?php

namespace Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryTransferResource\Pages;

use Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryTransferResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInventoryTransfer extends EditRecord
{
    protected static string $resource = InventoryTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
