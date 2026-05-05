<?php

namespace Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeAdvanceResource\Pages;

use Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeAdvanceResource;
use Erpsaas\Hr\Models\EmployeeAdvance;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEmployeeAdvance extends CreateRecord
{
    protected static string $resource = EmployeeAdvanceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return EmployeeAdvance::create($data);
    }
}
