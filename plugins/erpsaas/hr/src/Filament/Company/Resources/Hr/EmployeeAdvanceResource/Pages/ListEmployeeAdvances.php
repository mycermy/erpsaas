<?php

namespace Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeAdvanceResource\Pages;

use Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeAdvanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeAdvances extends ListRecords
{
    protected static string $resource = EmployeeAdvanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
