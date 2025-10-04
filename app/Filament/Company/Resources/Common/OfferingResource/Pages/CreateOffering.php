<?php

namespace App\Filament\Company\Resources\Common\OfferingResource\Pages;

use App\Concerns\HandlePageRedirect;
use App\Enums\Common\OfferingType;
use App\Filament\Company\Resources\Common\OfferingResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOffering extends CreateRecord
{
    use HandlePageRedirect;

    protected static string $resource = OfferingResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $attributes = array_flip($data['attributes'] ?? []);

        $data['sellable'] = isset($attributes['Sellable']);
        $data['purchasable'] = isset($attributes['Purchasable']);

        unset($data['attributes']);

        $offering = parent::handleRecordCreation($data);

        // Notify if inventory item was auto-created
        if ($offering->type === OfferingType::Product && $offering->inventoryItem) {
            Notification::make()
                ->success()
                ->title('Inventory Item Created')
                ->body("An inventory item has been automatically created with SKU: {$offering->inventoryItem->sku}")
                ->icon('heroicon-o-cube')
                ->send();
        }

        return $offering;
    }
}
