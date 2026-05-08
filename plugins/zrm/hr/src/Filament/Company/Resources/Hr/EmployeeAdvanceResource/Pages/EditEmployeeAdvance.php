<?php

namespace Zrm\Hr\Filament\Company\Resources\Hr\EmployeeAdvanceResource\Pages;

use Zrm\Hr\Filament\Company\Resources\Hr\EmployeeAdvanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeAdvance extends EditRecord
{
    protected static string $resource = EmployeeAdvanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(fn() => $this->record->isRecovered()),
        ];
    }
}
