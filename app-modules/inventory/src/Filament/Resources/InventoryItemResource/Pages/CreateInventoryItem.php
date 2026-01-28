<?php

namespace Modules\Inventory\Filament\Resources\InventoryItemResource\Pages;

use Modules\Inventory\Filament\Resources\InventoryItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryItem extends CreateRecord
{
    protected static string $resource = InventoryItemResource::class;
}
