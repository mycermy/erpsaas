<?php

namespace Erpsaas\Accounts\Filament\Company\Clusters\Banking\Resources\AccountResource\Pages;

use Erpsaas\Core\Concerns\HandlePageRedirect;
use Erpsaas\Accounts\Filament\Company\Clusters\Banking\Resources\AccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccount extends EditRecord
{
    use HandlePageRedirect;

    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['enabled'] = (bool) ($data['enabled'] ?? false);

        return $data;
    }
}
