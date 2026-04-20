<?php

namespace Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\AdjustmentResource\Pages;

use Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\AdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListAdjustments extends ListRecords
{
    protected static string $resource = AdjustmentResource::class;

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
