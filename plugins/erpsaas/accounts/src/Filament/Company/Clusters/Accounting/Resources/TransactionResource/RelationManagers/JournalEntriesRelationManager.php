<?php

namespace Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Resources\TransactionResource\RelationManagers;

use Erpsaas\Core\Utilities\Currency\CurrencyAccessor;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class JournalEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'journalEntries';

    protected $listeners = [
        'refresh' => '$refresh',
    ];

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Type'),
                Tables\Columns\TextColumn::make('account.name')
                    ->label('Account')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('account.category')
                    ->label('Category')
                    ->badge(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->weight(FontWeight::SemiBold)
                    ->sortable()
                    ->currency(CurrencyAccessor::getDefaultCurrency()),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }
}
