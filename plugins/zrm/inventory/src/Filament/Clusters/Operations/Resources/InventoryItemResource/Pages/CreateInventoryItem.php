<?php

namespace Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryItemResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryItemResource;

class CreateInventoryItem extends CreateRecord
{
    protected static string $resource = InventoryItemResource::class;
}
