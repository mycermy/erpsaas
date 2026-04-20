<?php

namespace Erpsaas\Sales\Filament\Company\Resources\Sales\ClientResource\Pages;

use Erpsaas\Sales\Filament\Company\Resources\Sales\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return 'max-w-full';
    }
}
