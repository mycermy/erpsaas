<?php

namespace Erpsaas\Core\Observers;

use Erpsaas\Core\Enums\Accounting\BillStatus;
use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Accounts\Models\Accounting\DocumentLineItem;
use Erpsaas\Accounts\Models\Accounting\Transaction;
use Illuminate\Support\Facades\DB;

class BillObserver
{
    public function created(Bill $bill): void
    {
        // $bill->createInitialTransaction();
    }

    public function saving(Bill $bill): void
    {
        if ($bill->isDirty('due_date') && $bill->status === BillStatus::Overdue && ! $bill->shouldBeOverdue() && ! $bill->hasPayments()) {
            $bill->status = BillStatus::Open;

            return;
        }

        if ($bill->shouldBeOverdue()) {
            $bill->status = BillStatus::Overdue;
        }
    }

    /**
     * Handle the Bill "deleted" event.
     */
    public function deleted(Bill $bill): void
    {
        DB::transaction(function () use ($bill) {
            $bill->lineItems()->each(function (DocumentLineItem $lineItem) {
                $lineItem->delete();
            });

            $bill->transactions()->each(function (Transaction $transaction) {
                $transaction->delete();
            });
        });
    }
}
