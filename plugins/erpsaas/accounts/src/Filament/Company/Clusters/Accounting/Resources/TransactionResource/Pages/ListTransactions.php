<?php

namespace Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Resources\TransactionResource\Pages;

use Erpsaas\Core\Concerns\HasJournalEntryActions;
use Erpsaas\Core\Enums\Accounting\TransactionType;
use Erpsaas\Core\Filament\Actions\CreateTransactionAction;
use Erpsaas\Core\Filament\Company\Pages\Service\ConnectedAccount;
use Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Resources\TransactionResource;
use Erpsaas\Core\Services\PlaidService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\Width;

class ListTransactions extends ListRecords
{
    use HasJournalEntryActions;

    protected static string $resource = TransactionResource::class;

    public function getMaxContentWidth(): Width | string | null
    {
        return 'max-w-full';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                CreateTransactionAction::make('createDeposit')
                    ->label('Deposit')
                    ->type(TransactionType::Deposit),
                CreateTransactionAction::make('createWithdrawal')
                    ->label('Withdrawal')
                    ->type(TransactionType::Withdrawal),
                CreateTransactionAction::make('createTransfer')
                    ->label('Transfer')
                    ->type(TransactionType::Transfer),
                CreateTransactionAction::make('createJournalEntry')
                    ->label('Journal entry')
                    ->type(TransactionType::Journal),
            ])
                ->label('New transaction')
                ->button()
                ->dropdownPlacement('bottom-end')
                ->icon('heroicon-m-chevron-down')
                ->iconPosition(IconPosition::After),
            Actions\ActionGroup::make([
                Actions\Action::make('connectBank')
                    ->label('Connect your bank')
                    ->visible(app(PlaidService::class)->isEnabled())
                    ->url(ConnectedAccount::getUrl()),
            ])
                ->label('More')
                ->button()
                ->outlined()
                ->dropdownPlacement('bottom-end')
                ->icon('heroicon-m-chevron-down')
                ->iconPosition(IconPosition::After),
        ];
    }
}
