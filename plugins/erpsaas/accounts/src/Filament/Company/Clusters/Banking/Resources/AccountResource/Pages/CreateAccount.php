<?php

namespace Erpsaas\Accounts\Filament\Company\Clusters\Banking\Resources\AccountResource\Pages;

use Erpsaas\Core\Concerns\HandlePageRedirect;
use Erpsaas\Accounts\Filament\Company\Clusters\Banking\Resources\AccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAccount extends CreateRecord
{
    use HandlePageRedirect;

    protected static string $resource = AccountResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['enabled'] = (bool) ($data['enabled'] ?? false);

        return $data;
    }
}
