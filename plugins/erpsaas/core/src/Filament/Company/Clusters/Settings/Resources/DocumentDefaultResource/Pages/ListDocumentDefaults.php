<?php

namespace Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\DocumentDefaultResource\Pages;

use Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\DocumentDefaultResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListDocumentDefaults extends ListRecords
{
    protected static string $resource = DocumentDefaultResource::class;

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::ScreenTwoExtraLarge;
    }
}
