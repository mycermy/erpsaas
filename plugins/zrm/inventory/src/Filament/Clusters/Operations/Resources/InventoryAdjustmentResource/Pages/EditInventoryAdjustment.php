<?php

namespace Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryAdjustmentResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryAdjustmentResource;

class EditInventoryAdjustment extends EditRecord
{
    protected static string $resource = InventoryAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
