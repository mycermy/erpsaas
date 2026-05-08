<?php

namespace Zrm\Hr\Filament\Company\Resources\Hr\SalaryStructureResource\Pages;

use Zrm\Hr\Filament\Company\Resources\Hr\SalaryStructureResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalaryStructure extends EditRecord
{
    protected static string $resource = SalaryStructureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
