<?php

namespace Modules\Inventory\Filament\Resources\InventoryItemResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'batches';

    protected static ?string $title = 'Inventory Batches';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->required()
                    ->disabled(fn (?string $operation) => $operation === 'edit'),

                Forms\Components\TextInput::make('batch_number')
                    ->maxLength(100),

                Forms\Components\TextInput::make('lot_number')
                    ->maxLength(100),

                Forms\Components\TextInput::make('quantity_received')
                    ->numeric()
                    ->required()
                    ->disabled(fn (?string $operation) => $operation === 'edit'),

                Forms\Components\TextInput::make('quantity_remaining')
                    ->numeric()
                    ->disabled()
                    ->helperText('Updated automatically when items are sold'),

                Forms\Components\TextInput::make('unit_cost')
                    ->label('Unit Cost')
                    ->numeric()
                    ->prefix('$')
                    ->required()
                    ->disabled(fn (?string $operation) => $operation === 'edit'),

                Forms\Components\DatePicker::make('received_date')
                    ->required()
                    ->disabled(fn (?string $operation) => $operation === 'edit'),

                Forms\Components\DatePicker::make('expiry_date')
                    ->after('received_date'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('received_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->sortable(),

                Tables\Columns\TextColumn::make('batch_number')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('lot_number')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('received_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_received')
                    ->label('Received')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_remaining')
                    ->label('Remaining')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($record) => $record->quantity_remaining > 0 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->currencyWithConversion('MYR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expiry_date')
                    ->date()
                    ->sortable()
                    ->toggleable()
                    ->color(fn ($record) => $record->expiry_date && $record->expiry_date->isPast() ? 'danger' : null),

                Tables\Columns\TextColumn::make('bill.document_number')
                    ->label('Bill #')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->relationship('warehouse', 'name'),

                Tables\Filters\TernaryFilter::make('has_remaining')
                    ->label('Has Stock')
                    ->queries(
                        true: fn ($query) => $query->where('quantity_remaining', '>', 0),
                        false: fn ($query) => $query->where('quantity_remaining', '=', 0),
                    ),

                Tables\Filters\Filter::make('expired')
                    ->query(fn ($query) => $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<', now())),
            ])
            ->headerActions([
                // Batches are created automatically from purchases
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions for batches
            ]);
    }
}
