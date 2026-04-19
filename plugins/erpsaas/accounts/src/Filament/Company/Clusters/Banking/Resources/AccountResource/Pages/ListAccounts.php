<?php

namespace Erpsaas\Accounts\Filament\Company\Clusters\Banking\Resources\AccountResource\Pages;

use Erpsaas\Accounts\Filament\Company\Clusters\Banking\Resources\AccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return 'max-w-full';
    }
}
