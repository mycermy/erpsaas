<?php

namespace Erpsaas\Purchases\Filament\Company\Resources\Purchases\VendorResource\Pages;

use Erpsaas\Purchases\Filament\Company\Resources\Purchases\VendorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListVendors extends ListRecords
{
    protected static string $resource = VendorResource::class;

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
