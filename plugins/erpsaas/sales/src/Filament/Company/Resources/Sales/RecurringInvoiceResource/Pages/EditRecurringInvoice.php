<?php

namespace Erpsaas\Sales\Filament\Company\Resources\Sales\RecurringInvoiceResource\Pages;

use Erpsaas\Core\Concerns\HandlePageRedirect;
use Erpsaas\Core\Concerns\ManagesLineItems;
use Erpsaas\Sales\Filament\Company\Resources\Sales\RecurringInvoiceResource;
use Erpsaas\Accounts\Models\Accounting\Estimate;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class EditRecurringInvoice extends EditRecord
{
    use HandlePageRedirect;
    use ManagesLineItems;

    protected static string $resource = RecurringInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Estimate $record */
        $lineItems = collect($data['lineItems'] ?? []);

        $this->deleteRemovedLineItems($record, $lineItems);

        $this->handleLineItems($record, $lineItems);

        $totals = $this->updateDocumentTotals($record, $data);

        $data = array_merge($data, $totals);

        return parent::handleRecordUpdate($record, $data);
    }
}
