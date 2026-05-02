<?php

namespace Erpsaas\Hr\Filament\Company\Resources\Hr\PayrollEntryResource\Pages;

use Erpsaas\Hr\Filament\Company\Resources\Hr\PayrollEntryResource;
use Erpsaas\Hr\Models\PayrollEntry;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePayrollEntry extends CreateRecord
{
    protected static string $resource = PayrollEntryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return PayrollEntry::createWithTransaction($data);
    }
}
