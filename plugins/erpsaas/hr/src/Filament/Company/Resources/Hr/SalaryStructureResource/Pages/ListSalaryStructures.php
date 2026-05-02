<?php

namespace Erpsaas\Hr\Filament\Company\Resources\Hr\SalaryStructureResource\Pages;

use Erpsaas\Hr\Filament\Company\Resources\Hr\SalaryStructureResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalaryStructures extends ListRecords
{
    protected static string $resource = SalaryStructureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
