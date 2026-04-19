<?php

namespace Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\AdjustmentResource\Pages;

use Erpsaas\Core\Concerns\HandlePageRedirect;
use Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\AdjustmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdjustment extends CreateRecord
{
    use HandlePageRedirect;

    protected static string $resource = AdjustmentResource::class;
}
