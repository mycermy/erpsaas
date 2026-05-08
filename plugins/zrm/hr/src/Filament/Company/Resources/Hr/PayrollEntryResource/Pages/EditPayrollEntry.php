<?php

namespace Zrm\Hr\Filament\Company\Resources\Hr\PayrollEntryResource\Pages;

use Zrm\Hr\Filament\Company\Resources\Hr\PayrollEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPayrollEntry extends EditRecord
{
    protected static string $resource = PayrollEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Ensure bill relationship is loaded
        $this->record->loadMissing('bill');

        return $data;
    }
}
