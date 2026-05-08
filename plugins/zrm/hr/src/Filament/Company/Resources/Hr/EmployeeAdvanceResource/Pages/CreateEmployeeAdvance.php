<?php

namespace Zrm\Hr\Filament\Company\Resources\Hr\EmployeeAdvanceResource\Pages;

use Zrm\Hr\Filament\Company\Resources\Hr\EmployeeAdvanceResource;
use Zrm\Hr\Models\EmployeeAdvance;
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
