<?php

namespace Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\DepartmentResource\Pages;

use Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\DepartmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDepartment extends CreateRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
