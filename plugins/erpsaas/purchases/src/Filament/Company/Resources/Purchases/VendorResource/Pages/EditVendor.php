<?php

namespace Erpsaas\Purchases\Filament\Company\Resources\Purchases\VendorResource\Pages;

use Erpsaas\Core\Concerns\HandlePageRedirect;
use Erpsaas\Purchases\Filament\Company\Resources\Purchases\VendorResource;
use Erpsaas\Core\Models\Common\Vendor;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class EditVendor extends EditRecord
{
    use HandlePageRedirect;

    protected static string $resource = VendorResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Vendor $record */
        $record->updateWithRelations($data);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::FiveExtraLarge;
    }
}
