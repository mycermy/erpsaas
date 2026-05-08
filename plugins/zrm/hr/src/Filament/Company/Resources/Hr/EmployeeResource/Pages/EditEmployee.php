<?php

namespace Zrm\Hr\Filament\Company\Resources\Hr\EmployeeResource\Pages;

use Zrm\Hr\Filament\Company\Resources\Hr\EmployeeResource;
use Zrm\Hr\Models\Employee;
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
