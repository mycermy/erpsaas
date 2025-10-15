<?php

namespace App\Filament\Company\Resources\Inventory;

use App\Enums\Inventory\AdjustmentStatus;
use App\Enums\Inventory\MovementType;
use App\Filament\Company\Resources\Inventory\InventoryAdjustmentResource\Pages;
use App\Models\Inventory\InventoryAdjustment;
use App\Services\Inventory\InventoryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class InventoryAdjustmentResource extends Resource
{
    protected static ?string $model = InventoryAdjustment::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Adjustments';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Adjustment Information')
                    ->schema([
                        Forms\Components\TextInput::make('adjustment_number')
                            ->required()
                            ->label('Reference Number')
                            ->default(fn () => 'ADJ-' . date('ymd') . '-' . rand(100, 999))
                            ->maxLength(100)
                            ->disabled(fn (?string $operation) => $operation === 'edit'),

                        Forms\Components\Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(
                                fn (?string $operation, ?InventoryAdjustment $record) => $operation === 'edit' && $record?->status !== AdjustmentStatus::Draft
                            ),

                        Forms\Components\DatePicker::make('adjustment_date')
                            ->required()
                            ->default(now())
                            ->disabled(
                                fn (?string $operation, ?InventoryAdjustment $record) => $operation === 'edit' && $record?->status !== AdjustmentStatus::Draft
                            ),

                        Forms\Components\Select::make('status')
                            ->options(AdjustmentStatus::class)
                            ->default(AdjustmentStatus::Draft)
                            ->required()
                            ->disabled(fn (?string $operation) => $operation === 'create'),

                        Forms\Components\Textarea::make('reason')
                            ->columnSpanFull()
                            ->rows(3)
                            ->helperText('Explain why this adjustment is needed'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Adjustment Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\Select::make('inventory_item_id')
                                    ->label('Item')
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->getSearchResultsUsing(function (?string $search) {
                                        $companyId = session('current_company_id') ?? (Auth::user()?->current_company_id ?? null);

                                        $query = \App\Models\Inventory\InventoryItem::with('offering')
                                            ->when($companyId, fn ($q) => $q->where('company_id', $companyId));

                                        if ($search) {
                                            $query->where(function ($q) use ($search) {
                                                $q->whereHas('offering', function ($q2) use ($search) {
                                                    $q2->where('name', 'like', "%{$search}%");
                                                })
                                                    ->orWhere('sku', 'like', "%{$search}%");
                                            });
                                        }

                                        return $query->limit(50)->orderByDesc('id')->get()->mapWithKeys(function ($i) {
                                            return [$i->id => $i->offering->name ?? $i->sku];
                                        })->toArray();
                                    })
                                    ->getOptionLabelUsing(function (?int $value): ?string {
                                        if (! $value) {
                                            return null;
                                        }

                                        $companyId = session('current_company_id') ?? (Auth::user()?->current_company_id ?? null);

                                        $item = \App\Models\Inventory\InventoryItem::with('offering')
                                            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                                            ->find($value);

                                        return $item?->offering->name ?? $item?->sku;
                                    })
                                    ->afterStateUpdated(function (Forms\Set $set, ?int $state, Get $get) {
                                        if (! $state) {
                                            return;
                                        }

                                        $warehouseId = $get('../../warehouse_id');
                                        if (! $warehouseId) {
                                            return;
                                        }

                                        $stockLevel = \App\Models\Inventory\InventoryStockLevel::where('inventory_item_id', $state)
                                            ->where('warehouse_id', $warehouseId)
                                            ->first();

                                        $set('quantity_before', $stockLevel?->quantity_on_hand ?? 0);
                                        $set('unit_cost', $stockLevel?->average_unit_cost?->getAmount() ?? 0);
                                    })
                                    ->disabled(
                                        fn (?string $operation, Get $get) => $operation === 'edit' && $get('../../status') !== AdjustmentStatus::Draft->value
                                    ),

                                Forms\Components\TextInput::make('quantity_before')
                                    ->label('Current Qty')
                                    ->numeric()
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(),

                                Forms\Components\TextInput::make('quantity_after')
                                    ->label('New Qty')
                                    ->numeric()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, ?float $state, Get $get) {
                                        $before = (float) ($get('quantity_before') ?? 0);
                                        $set('quantity_adjusted', $state - $before);
                                    })
                                    ->disabled(
                                        fn (?string $operation, Get $get) => $operation === 'edit' && $get('../../status') !== AdjustmentStatus::Draft->value
                                    ),

                                Forms\Components\TextInput::make('quantity_adjusted')
                                    ->label('Adjustment')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('Positive = increase, Negative = decrease'),

                                Forms\Components\TextInput::make('unit_cost')
                                    ->label('Unit Cost')
                                    ->numeric()
                                    ->prefix('$')
                                    ->disabled()
                                    ->dehydrated(),

                                Forms\Components\Textarea::make('reason')
                                    ->rows(2)
                                    ->columnSpanFull()
                                    ->disabled(
                                        fn (?string $operation, Get $get) => $operation === 'edit' && $get('../../status') !== AdjustmentStatus::Draft->value
                                    ),
                            ])
                            ->columns(5)
                            ->defaultItems(1)
                            ->addActionLabel('Add Item')
                            ->reorderable(false)
                            ->collapsible()
                            ->disabled(
                                fn (?string $operation, ?InventoryAdjustment $record) => $operation === 'edit' && $record?->status !== AdjustmentStatus::Draft
                            ),
                    ])
                    ->visible(fn (?string $operation) => $operation !== 'view'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('adjustment_number')
                    ->label('Reference Number')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('adjustment_date')
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

                Tables\Columns\TextColumn::make('approved_by_user.name')
                    ->label('Approved By')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('approved_at')
                    ->dateTime()
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
                    ->options(AdjustmentStatus::class)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (InventoryAdjustment $record) => $record->status === AdjustmentStatus::Draft)
                    ->action(function (InventoryAdjustment $record) {
                        $inventoryService = app(InventoryService::class);

                        foreach ($record->items as $item) {
                            if ($item->quantity_adjusted == 0) {
                                continue;
                            }

                            $inventoryService->recordMovement(
                                item: $item->inventoryItem,
                                warehouse: $record->warehouse,
                                quantity: $item->quantity_adjusted,
                                movementType: MovementType::Adjustment,
                                unitCost: $item->unit_cost,
                                referenceType: InventoryAdjustment::class,
                                referenceId: $record->id,
                                notes: $item->reason,
                                movementDate: $record->adjustment_date
                            );
                        }

                        $record->update([
                            'status' => AdjustmentStatus::Approved,
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                        ]);
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (InventoryAdjustment $record) => $record->status === AdjustmentStatus::Draft)
                    ->action(function (InventoryAdjustment $record) {
                        $record->update(['status' => AdjustmentStatus::Cancelled]);
                    }),

                Tables\Actions\EditAction::make()
                    ->visible(fn (InventoryAdjustment $record) => $record->status === AdjustmentStatus::Draft),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                if ($record->status === AdjustmentStatus::Draft) {
                                    $record->delete();
                                }
                            });
                        }),
                ]),
            ])
            ->defaultSort('adjustment_date', 'desc');
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
            'index' => Pages\ListInventoryAdjustments::route('/'),
            'create' => Pages\CreateInventoryAdjustment::route('/create'),
            'edit' => Pages\EditInventoryAdjustment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['warehouse', 'items.inventoryItem']);
    }
}
