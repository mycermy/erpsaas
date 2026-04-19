<?php

use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Accounts\Models\Accounting\Invoice;
use Erpsaas\Accounts\Models\Accounting\Transaction;
use Erpsaas\Core\Models\Common\Client;
use Erpsaas\Core\Models\Common\Vendor;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $transactions = Transaction::query()
            ->withoutGlobalScopes()
            ->whereHasMorph('transactionable', [Invoice::class, Bill::class])
            ->whereDoesntHaveMorph('payeeable', [Client::class, Vendor::class])
            ->get();

        foreach ($transactions as $transaction) {
            $document = $transaction->transactionable;

            if ($document instanceof Invoice) {
                $transaction->payeeable_id = $document->client_id;
                $transaction->payeeable_type = Client::class;

                $transaction->saveQuietly();
            } elseif ($document instanceof Bill) {
                $transaction->payeeable_id = $document->vendor_id;
                $transaction->payeeable_type = Vendor::class;

                $transaction->saveQuietly();
            }
        }
    }
};
