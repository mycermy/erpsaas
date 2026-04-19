<?php

namespace Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\DepartmentResource\Pages;

use Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\DepartmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDepartment extends EditRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
