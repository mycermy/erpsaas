<?php

namespace Modules\Inventory\Filament\Resources;

use Modules\Inventory\Enums\TransferStatus;
use Modules\Inventory\Filament\Resources\InventoryTransferResource\Pages;
use Modules\Inventory\Models\InventoryTransfer;
use Modules\Inventory\Services\InventoryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryTransferResource extends Resource
{
    protected static ?string $model = InventoryTransfer::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Transfers';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transfer Information')
                    ->schema([
                        Forms\Components\TextInput::make('reference_number')
                            ->required()
                            ->default(fn () => 'TRF-' . date('YmdHis'))
                            ->maxLength(100)
                            ->disabled(fn (?string $operation) => $operation === 'edit'),

                        Forms\Components\Select::make('from_warehouse_id')
                            ->label('From Warehouse')
                            ->relationship('fromWarehouse', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(fn (?string $operation, ?InventoryTransfer $record) => 
                                $operation === 'edit' && $record?->status !== TransferStatus::Pending
                            ),

                        Forms\Components\Select::make('to_warehouse_id')
                            ->label('To Warehouse')
                            ->relationship('toWarehouse', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->different('from_warehouse_id')
                            ->disabled(fn (?string $operation, ?InventoryTransfer $record) => 
                                $operation === 'edit' && $record?->status !== TransferStatus::Pending
                            ),

                        Forms\Components\DatePicker::make('transfer_date')
                            ->required()
                            ->default(now())
                            ->disabled(fn (?string $operation, ?InventoryTransfer $record) => 
                                $operation === 'edit' && $record?->status !== TransferStatus::Pending
                            ),

                        Forms\Components\Select::make('status')
                            ->options(TransferStatus::class)
                            ->default(TransferStatus::Pending)
                            ->required()
                            ->disabled(fn (?string $operation) => $operation === 'create'),

                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull()
                            ->rows(3),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Transfer Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\Select::make('inventory_item_id')
                                    ->label('Item')
                                    ->relationship('inventoryItem.offering', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, ?int $state, Get $get) {
                                        if (! $state) {
                                            return;
                                        }

                                        $fromWarehouseId = $get('../../from_warehouse_id');
                                        if (! $fromWarehouseId) {
                                            return;
                                        }

                                        $stockLevel = \Modules\Inventory\Models\InventoryStockLevel::where('inventory_item_id', $state)
                                            ->where('warehouse_id', $fromWarehouseId)
                                            ->first();

                                        $set('available_quantity', $stockLevel?->quantity_available ?? 0);
                                    })
                                    ->disabled(fn (?string $operation, ?InventoryTransfer $record) => 
                                        $operation === 'edit' && $record?->status !== TransferStatus::Pending
                                    ),

                                Forms\Components\TextInput::make('available_quantity')
                                    ->label('Available')
                                    ->numeric()
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.01)
                                    ->disabled(fn (?string $operation, ?InventoryTransfer $record) => 
                                        $operation === 'edit' && $record?->status !== TransferStatus::Pending
                                    ),

                                Forms\Components\Textarea::make('notes')
                                    ->rows(2)
                                    ->columnSpanFull()
                                    ->disabled(fn (?string $operation, ?InventoryTransfer $record) => 
                                        $operation === 'edit' && $record?->status !== TransferStatus::Pending
                                    ),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('Add Item')
                            ->reorderable(false)
                            ->collapsible()
                            ->disabled(fn (?string $operation, ?InventoryTransfer $record) => 
                                $operation === 'edit' && $record?->status !== TransferStatus::Pending
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fromWarehouse.name')
                    ->label('From')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('toWarehouse.name')
                    ->label('To')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('transfer_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_by_user.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(TransferStatus::class)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('from_warehouse_id')
                    ->relationship('fromWarehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->label('From Warehouse'),

                Tables\Filters\SelectFilter::make('to_warehouse_id')
                    ->relationship('toWarehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->label('To Warehouse'),
            ])
            ->actions([
                Tables\Actions\Action::make('ship')
                    ->label('Ship')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (InventoryTransfer $record) => $record->status === TransferStatus::Pending)
                    ->action(function (InventoryTransfer $record) {
                        $record->update(['status' => TransferStatus::InTransit]);
                    }),

                Tables\Actions\Action::make('receive')
                    ->label('Receive')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (InventoryTransfer $record) => $record->status === TransferStatus::InTransit)
                    ->action(function (InventoryTransfer $record) {
                        $inventoryService = app(InventoryService::class);

                        foreach ($record->items as $item) {
                            // Record outbound from source warehouse
                            $inventoryService->recordMovement(
                                item: $item->inventoryItem,
                                warehouse: $record->fromWarehouse,
                                quantity: -$item->quantity,
                                movementType: \Modules\Inventory\Enums\MovementType::TransferOut,
                                unitCost: 0,
                                referenceType: InventoryTransfer::class,
                                referenceId: $record->id,
                                movementDate: $record->transfer_date
                            );

                            // Record inbound to destination warehouse
                            $inventoryService->recordMovement(
                                item: $item->inventoryItem,
                                warehouse: $record->toWarehouse,
                                quantity: $item->quantity,
                                movementType: \Modules\Inventory\Enums\MovementType::TransferIn,
                                unitCost: 0,
                                referenceType: InventoryTransfer::class,
                                referenceId: $record->id,
                                movementDate: $record->transfer_date
                            );
                        }

                        $record->update(['status' => TransferStatus::Received]);
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (InventoryTransfer $record) => in_array($record->status, [TransferStatus::Pending, TransferStatus::InTransit]))
                    ->action(function (InventoryTransfer $record) {
                        $record->update(['status' => TransferStatus::Cancelled]);
                    }),

                Tables\Actions\EditAction::make()
                    ->visible(fn (InventoryTransfer $record) => $record->status === TransferStatus::Pending),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                if ($record->status === TransferStatus::Pending) {
                                    $record->delete();
                                }
                            });
                        }),
                ]),
            ])
            ->defaultSort('transfer_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryTransfers::route('/'),
            'create' => Pages\CreateInventoryTransfer::route('/create'),
            'edit' => Pages\EditInventoryTransfer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['fromWarehouse', 'toWarehouse', 'items.inventoryItem']);
    }
}
