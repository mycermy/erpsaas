<?php

namespace Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\AdjustmentResource\Pages;

use Erpsaas\Core\Concerns\HandlePageRedirect;
use Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\AdjustmentResource;
use Filament\Resources\Pages\EditRecord;

class EditAdjustment extends EditRecord
{
    use HandlePageRedirect;

    protected static string $resource = AdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
