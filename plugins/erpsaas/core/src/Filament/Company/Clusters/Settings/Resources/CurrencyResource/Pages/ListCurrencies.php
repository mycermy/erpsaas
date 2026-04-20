<?php

namespace Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\CurrencyResource\Pages;

use Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\CurrencyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListCurrencies extends ListRecords
{
    protected static string $resource = CurrencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::ScreenTwoExtraLarge;
    }
}
