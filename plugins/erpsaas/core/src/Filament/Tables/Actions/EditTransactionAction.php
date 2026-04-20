<?php

namespace Erpsaas\Core\Filament\Tables\Actions;

use Erpsaas\Core\Concerns\HasTransactionAction;
use Erpsaas\Core\Enums\Accounting\TransactionType;
use Erpsaas\Accounts\Models\Accounting\Transaction;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Actions\EditAction;

class EditTransactionAction extends EditAction
{
    use HasTransactionAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type(static function (Transaction $record) {
            return $record->type;
        });

        $this->label(function () {
            return match ($this->getTransactionType()) {
                TransactionType::Journal => 'Edit journal entry',
                default => 'Edit transaction',
            };
        });

        $this->slideOver();

        $this->modalWidth(function (): Width {
            return match ($this->getTransactionType()) {
                TransactionType::Journal => Width::Screen,
                default => Width::ThreeExtraLarge,
            };
        });

        $this->extraModalWindowAttributes(function (): array {
            if ($this->getTransactionType() === TransactionType::Journal) {
                return ['class' => 'journal-transaction-modal'];
            }

            return [];
        });

        $this->form(function (Schema $form) {
            return match ($this->getTransactionType()) {
                TransactionType::Transfer => $this->transferForm($form),
                TransactionType::Journal => $this->journalTransactionForm($form),
                default => $this->transactionForm($form),
            };
        });

        $this->afterFormFilled(function (Transaction $record) {
            if ($this->getTransactionType() === TransactionType::Journal) {
                $debitAmounts = $record->journalEntries->sumDebits()->getAmount();
                $creditAmounts = $record->journalEntries->sumCredits()->getAmount();

                $this->setDebitAmount($debitAmounts);
                $this->setCreditAmount($creditAmounts);
            }
        });

        $this->modalSubmitAction(function (Action $action) {
            if ($this->getTransactionType() === TransactionType::Journal) {
                $action->disabled(! $this->isJournalEntryBalanced());
            }

            return $action;
        });

        $this->after(function (Transaction $transaction) {
            if ($this->getTransactionType() === TransactionType::Journal) {
                $transaction->updateAmountIfBalanced();
            }
        });
    }
}
