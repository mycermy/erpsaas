<?php

namespace Erpsaas\Hr\Filament\Company\Resources\Hr\PayrollEntryResource\Pages;

use Erpsaas\Hr\Filament\Company\Resources\Hr\PayrollEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayrollEntries extends ListRecords
{
    protected static string $resource = PayrollEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
