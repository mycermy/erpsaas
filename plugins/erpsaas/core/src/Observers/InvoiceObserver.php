<?php

namespace Erpsaas\Core\Observers;

use Erpsaas\Core\Enums\Accounting\InvoiceStatus;
use Erpsaas\Accounts\Models\Accounting\DocumentLineItem;
use Erpsaas\Accounts\Models\Accounting\Invoice;
use Erpsaas\Accounts\Models\Accounting\Transaction;
use Illuminate\Support\Facades\DB;

class InvoiceObserver
{
    public function saving(Invoice $invoice): void
    {
        if (! $invoice->wasApproved()) {
            return;
        }

        if ($invoice->isDirty('due_date') && $invoice->status === InvoiceStatus::Overdue && ! $invoice->shouldBeOverdue() && ! $invoice->hasPayments()) {
            $invoice->status = $invoice->hasBeenSent() ? InvoiceStatus::Sent : InvoiceStatus::Unsent;

            return;
        }

        if ($invoice->shouldBeOverdue()) {
            $invoice->status = InvoiceStatus::Overdue;
        }
    }

    public function deleted(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice->lineItems()->each(function (DocumentLineItem $lineItem) {
                $lineItem->delete();
            });

            $invoice->transactions()->each(function (Transaction $transaction) {
                $transaction->delete();
            });
        });
    }
}
