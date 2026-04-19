<?php

namespace Erpsaas\Purchases\Filament\Company\Resources\Purchases\BillResource\Pages;

use Erpsaas\Core\Concerns\HandlePageRedirect;
use Erpsaas\Core\Concerns\ManagesLineItems;
use Erpsaas\Purchases\Filament\Company\Resources\Purchases\BillResource;
use Erpsaas\Accounts\Models\Accounting\Bill;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class EditBill extends EditRecord
{
    use HandlePageRedirect;
    use ManagesLineItems;

    protected static string $resource = BillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Bill $record */
        $lineItems = collect($data['lineItems'] ?? []);

        $this->deleteRemovedLineItems($record, $lineItems);

        $this->handleLineItems($record, $lineItems);

        $totals = $this->updateDocumentTotals($record, $data);

        $data = array_merge($data, $totals);

        $record = parent::handleRecordUpdate($record, $data);

        $record->updateInitialTransaction();

        return $record;
    }
}
