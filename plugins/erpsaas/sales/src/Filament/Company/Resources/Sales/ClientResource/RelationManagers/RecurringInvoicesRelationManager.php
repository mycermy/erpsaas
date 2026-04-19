<?php

namespace Erpsaas\Sales\Filament\Company\Resources\Sales\ClientResource\RelationManagers;

use Erpsaas\Sales\Filament\Company\Resources\Sales\RecurringInvoiceResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RecurringInvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'recurringInvoices';

    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return RecurringInvoiceResource::table($table)
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->url(RecurringInvoiceResource\Pages\CreateRecurringInvoice::getUrl(['client' => $this->getOwnerRecord()->getKey()])),
            ]);
    }
}
