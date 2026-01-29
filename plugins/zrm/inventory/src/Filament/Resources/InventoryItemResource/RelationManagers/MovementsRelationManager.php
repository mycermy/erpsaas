<?php

namespace Zrm\Inventory\Filament\Resources\InventoryItemResource\RelationManagers;

use Zrm\Inventory\Enums\MovementType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Stock Movements';

    protected static ?string $icon = 'heroicon-o-arrows-right-left';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('View Only')
                    ->content('Stock movements are automatically created by the system and cannot be manually edited.')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('movement_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('movement_date')
                    ->label('Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('movement_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (MovementType $state): string => match ($state) {
                        MovementType::Purchase, MovementType::Initial, MovementType::TransferIn, MovementType::Return => 'success',
                        MovementType::Sale, MovementType::TransferOut => 'danger',
                        MovementType::Adjustment => 'warning',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric(decimalPlaces: 2)
                    ->color(fn (string $state): string => $state >= 0 ? 'success' : 'danger')
                    ->weight(FontWeight::Bold)
                    ->formatStateUsing(fn (string $state): string => $state >= 0 ? "+{$state}" : $state),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->currencyWithConversion('MYR')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->currencyWithConversion('MYR')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('batch.batch_number')
                    ->label('Batch')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('N/A'),

                Tables\Columns\TextColumn::make('reference_type')
                    ->label('Reference')
                    ->formatStateUsing(function ($record) {
                        if (! $record->reference_type) {
                            return 'N/A';
                        }

                        $type = class_basename($record->reference_type);

                        // Try to get reference number from the relationship if available
                        try {
                            if ($record->reference) {
                                $refNumber = match ($type) {
                                    'Bill' => $record->reference->bill_number ?? null,
                                    'Invoice' => $record->reference->invoice_number ?? null,
                                    'InventoryAdjustment' => $record->reference->adjustment_number ?? null,
                                    'InventoryTransfer' => $record->reference->transfer_number ?? null,
                                    default => null,
                                };

                                if ($refNumber) {
                                    return "{$type} #{$refNumber}";
                                }
                            }
                        } catch (\Exception $e) {
                            // Class doesn't exist or relationship failed, just show type and ID
                        }

                        return "{$type} #{$record->reference_id}";
                    })
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->searchable()
                    ->wrap()
                    ->toggleable()
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('movement_type')
                    ->label('Movement Type')
                    ->options(MovementType::class)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'name')
                    ->multiple(),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('to')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('movement_date', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('movement_date', '<=', $date),
                            );
                    }),

                Tables\Filters\Filter::make('inbound')
                    ->label('Inbound Only')
                    ->query(fn (Builder $query): Builder => $query->where('quantity', '>', 0)),

                Tables\Filters\Filter::make('outbound')
                    ->label('Outbound Only')
                    ->query(fn (Builder $query): Builder => $query->where('quantity', '<', 0)),
            ])
            ->actions([
                Tables\Actions\Action::make('view_reference')
                    ->label('View Document')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('primary')
                    ->url(function ($record) {
                        if (! $record->reference_type || ! $record->reference_id) {
                            return null;
                        }

                        $type = class_basename($record->reference_type);

                        return match ($type) {
                            'Bill' => \App\Filament\Company\Resources\Purchases\BillResource::getUrl('view', [
                                'record' => $record->reference_id,
                                'tenant' => filament()->getTenant(),
                            ]),
                            'Invoice' => \App\Filament\Company\Resources\Sales\InvoiceResource::getUrl('view', [
                                'record' => $record->reference_id,
                                'tenant' => filament()->getTenant(),
                            ]),
                            'InventoryAdjustment' => \Zrm\Inventory\Filament\Resources\InventoryAdjustmentResource::getUrl('edit', [
                                'record' => $record->reference_id,
                                'tenant' => filament()->getTenant(),
                            ]),
                            'InventoryTransfer' => \Zrm\Inventory\Filament\Resources\InventoryTransferResource::getUrl('edit', [
                                'record' => $record->reference_id,
                                'tenant' => filament()->getTenant(),
                            ]),
                            default => null,
                        };
                    }, shouldOpenInNewTab: true)
                    ->visible(fn ($record) => $record->reference_type !== null)
                    ->tooltip(fn ($record) => 'Open ' . ($record->reference_type ? class_basename($record->reference_type) : 'document')),
            ])
            ->bulkActions([
                // No bulk actions for movements as they should not be deleted manually
            ])
            ->emptyStateHeading('No movements yet')
            ->emptyStateDescription('Stock movements will appear here when inventory transactions occur.')
            ->emptyStateIcon('heroicon-o-arrows-right-left')
            ->headerActions([
                // No create action - movements are auto-generated
            ]);
    }
}
