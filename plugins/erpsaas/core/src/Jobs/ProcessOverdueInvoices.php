<?php

namespace Erpsaas\Core\Jobs;

use Erpsaas\Core\Enums\Accounting\InvoiceStatus;
use Erpsaas\Accounts\Models\Accounting\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessOverdueInvoices implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Invoice::query()
            ->whereIn('status', InvoiceStatus::canBeOverdue())
            ->where('due_date', '<', today())
            ->update(['status' => InvoiceStatus::Overdue]);
    }
}
