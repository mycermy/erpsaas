<?php

namespace Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryItemResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StockLevelsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockLevels';

    protected static ?string $title = 'Stock Levels by Warehouse';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->required()
                    ->disabled(fn (?string $operation) => $operation === 'edit'),

                Forms\Components\TextInput::make('quantity_on_hand')
                    ->numeric()
                    ->disabled()
                    ->helperText('Updated automatically by inventory movements'),

                Forms\Components\TextInput::make('quantity_reserved')
                    ->numeric()
                    ->disabled()
                    ->helperText('Reserved for pending orders'),

                Forms\Components\TextInput::make('quantity_available')
                    ->numeric()
                    ->disabled()
                    ->helperText('On Hand - Reserved'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_on_hand')
                    ->label('On Hand')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_reserved')
                    ->label('Reserved')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_available')
                    ->label('Available')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($record) => $record->quantity_available <= $record->inventoryItem->reorder_level ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('average_cost')
                    ->label('Avg Unit Cost')
                    ->currencyWithConversion('MYR')
                    ->getStateUsing(function ($record) {
                        if (! $record->average_cost) {
                            return null;
                        }

                        return is_object($record->average_cost)
                            ? $record->average_cost->getAmount()
                            : $record->average_cost;
                    })
                    ->default('–'),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Total Value')
                    ->currencyWithConversion('MYR')
                    ->getStateUsing(function ($record) {
                        if (! $record->average_cost) {
                            return 0;
                        }
                        $cost = is_object($record->average_cost)
                            ? $record->average_cost->getAmount()
                            : $record->average_cost;

                        return $record->quantity_on_hand * $cost;
                    }),

                Tables\Columns\TextColumn::make('last_movement_at')
                    ->label('Last Movement')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->relationship('warehouse', 'name'),
            ])
            ->headerActions([
                // Stock levels are created automatically by movements
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions for stock levels
            ]);
    }
}
