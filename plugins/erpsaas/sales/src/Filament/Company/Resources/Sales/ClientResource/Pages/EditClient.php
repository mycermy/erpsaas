<?php

namespace Erpsaas\Sales\Filament\Company\Resources\Sales\ClientResource\Pages;

use Erpsaas\Core\Concerns\HandlePageRedirect;
use Erpsaas\Sales\Filament\Company\Resources\Sales\ClientResource;
use Erpsaas\Core\Models\Common\Client;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class EditClient extends EditRecord
{
    use HandlePageRedirect;

    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::FiveExtraLarge;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Client $record */
        $record->updateWithRelations($data);

        return $record;
    }
}
