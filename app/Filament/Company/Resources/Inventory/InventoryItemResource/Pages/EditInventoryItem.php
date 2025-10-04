<?php

namespace App\Filament\Company\Resources\Inventory\InventoryItemResource\Pages;

use App\Filament\Company\Resources\Inventory\InventoryItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInventoryItem extends EditRecord
{
    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
