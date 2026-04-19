<?php

namespace Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\DocumentDefaultResource\Pages;

use Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\DocumentDefaultResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListDocumentDefaults extends ListRecords
{
    protected static string $resource = DocumentDefaultResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::ScreenTwoExtraLarge;
    }
}
