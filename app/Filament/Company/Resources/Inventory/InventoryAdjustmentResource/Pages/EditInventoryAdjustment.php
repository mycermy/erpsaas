<?php

namespace App\Filament\Company\Resources\Inventory\InventoryAdjustmentResource\Pages;

use App\Filament\Company\Resources\Inventory\InventoryAdjustmentResource;
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
