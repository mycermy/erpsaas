<?php

namespace Modules\Inventory\Filament\Resources\InventoryAdjustmentResource\Pages;

use Modules\Inventory\Filament\Resources\InventoryAdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

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
