<?php

namespace Erpsaas\Purchases\Filament\Company\Resources\Purchases\VendorResource\Pages;

use Erpsaas\Core\Concerns\HandlePageRedirect;
use Erpsaas\Purchases\Filament\Company\Resources\Purchases\VendorResource;
use Erpsaas\Core\Models\Common\Vendor;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class CreateVendor extends CreateRecord
{
    use HandlePageRedirect;

    protected static string $resource = VendorResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return Vendor::createWithRelations($data);
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::FiveExtraLarge;
    }
}
