<?php

namespace Modules\Inventory\Filament\Resources\InventoryTransferResource\Pages;

use Modules\Inventory\Filament\Resources\InventoryTransferResource;
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
