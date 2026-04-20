<?php

namespace Erpsaas\Core\Filament\Company\Resources\Common\OfferingResource\Pages;

use Erpsaas\Core\Filament\Company\Resources\Common\OfferingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListOfferings extends ListRecords
{
    protected static string $resource = OfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return 'max-w-full';
    }
}
