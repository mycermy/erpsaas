<?php

namespace Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryItemResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryItemResource;

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
