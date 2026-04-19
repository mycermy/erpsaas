<?php

namespace Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\CurrencyResource\Pages;

use Erpsaas\Core\Concerns\HandlePageRedirect;
use Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\CurrencyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCurrency extends CreateRecord
{
    use HandlePageRedirect;

    protected static string $resource = CurrencyResource::class;
}
