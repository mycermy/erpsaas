<?php

namespace Erpsaas\Core\Observers;

use Erpsaas\Accounts\Models\Accounting\Transaction;
use Erpsaas\Accounts\Models\Banking\BankAccount;
use Illuminate\Support\Facades\DB;

class BankAccountObserver
{
    /**
     * Handle the BankAccount "deleting" event.
     */
    public function deleting(BankAccount $bankAccount): void
    {
        DB::transaction(function () use ($bankAccount) {
            $account = $bankAccount->account;
            $connectedBankAccount = $bankAccount->connectedBankAccount;

            if ($account) {
                $bankAccount->transactions()->each(fn (Transaction $transaction) => $transaction->delete());
                $account->delete();
            }

            if ($connectedBankAccount) {
                $connectedBankAccount->delete();
            }
        });
    }
}
