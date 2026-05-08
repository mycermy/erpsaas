<?php

namespace Zrm\Hr\Filament\Company\Resources\Hr\EmployeeResource\Pages;

use Zrm\Hr\Filament\Company\Resources\Hr\EmployeeResource;
use Zrm\Hr\Models\Employee;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return Employee::createWithRelations($data);
    }
}
