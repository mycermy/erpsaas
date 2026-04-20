<?php

namespace Erpsaas\Sales\Filament\Company\Resources\Sales\InvoiceResource\Pages;

use Erpsaas\Core\Concerns\HandlePageRedirect;
use Erpsaas\Core\Concerns\ManagesLineItems;
use Erpsaas\Sales\Filament\Company\Resources\Sales\InvoiceResource;
use Erpsaas\Accounts\Models\Accounting\Invoice;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class EditInvoice extends EditRecord
{
    use HandlePageRedirect;
    use ManagesLineItems;

    protected static string $resource = InvoiceResource::class;

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
        /** @var Invoice $record */
        $lineItems = collect($data['lineItems'] ?? []);

        $this->deleteRemovedLineItems($record, $lineItems);

        $this->handleLineItems($record, $lineItems);

        $totals = $this->updateDocumentTotals($record, $data);

        $data = array_merge($data, $totals);

        $record = parent::handleRecordUpdate($record, $data);

        if ($record->approved_at && $record->approvalTransaction) {
            $record->updateApprovalTransaction();
        }

        return $record;
    }
}
