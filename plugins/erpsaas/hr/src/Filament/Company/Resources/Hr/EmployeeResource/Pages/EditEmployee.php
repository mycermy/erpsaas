<?php

namespace Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeResource\Pages;

use Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeResource;
use Erpsaas\Hr\Models\Employee;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Employee $record */
        $record->updateWithRelations($data);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
