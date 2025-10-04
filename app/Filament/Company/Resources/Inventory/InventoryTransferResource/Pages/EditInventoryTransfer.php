<?php

namespace App\Filament\Company\Resources\Inventory\InventoryTransferResource\Pages;

use App\Filament\Company\Resources\Inventory\InventoryTransferResource;
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
