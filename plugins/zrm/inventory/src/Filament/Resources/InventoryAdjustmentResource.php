<?php

namespace Zrm\Inventory\Filament\Resources;

use Zrm\Inventory\Enums\AdjustmentStatus;
use Zrm\Inventory\Enums\AdjustmentType;
use Zrm\Inventory\Filament\Resources\InventoryAdjustmentResource\Pages;
use Zrm\Inventory\Models\InventoryAdjustment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class InventoryAdjustmentResource extends Resource
{
    protected static ?string $model = InventoryAdjustment::class;

    protected static ?string $panel = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'inventory/adjustments';

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
                            ->default(fn() => 'ADJ-' . date('ymd') . '-' . rand(100, 999))
                            ->maxLength(100)
                            ->disabled(fn(?string $operation) => $operation === 'edit'),

                        Forms\Components\Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(
                                fn(?string $operation, ?InventoryAdjustment $record) => $operation === 'edit' && $record?->status !== AdjustmentStatus::Draft
                            ),

                        Forms\Components\DatePicker::make('adjustment_date')
                            ->required()
                            ->default(now())
                            ->disabled(
                                fn(?string $operation, ?InventoryAdjustment $record) => $operation === 'edit' && $record?->status !== AdjustmentStatus::Draft
                            ),

                        Forms\Components\Select::make('status')
                            ->options(AdjustmentStatus::class)
                            ->default(AdjustmentStatus::Draft)
                            ->required()
                            ->disabled(fn(?string $operation) => $operation === 'create'),

                        Forms\Components\Select::make('adjustment_type')
                            ->label('Adjustment Type')
                            ->options(AdjustmentType::class)
                            ->default(AdjustmentType::Stocktake)
                            ->required()
                            ->live()
                            ->helperText(function ($state) {
                                if ($state instanceof AdjustmentType) {
                                    return $state->description();
                                }
                                return $state ? AdjustmentType::from($state)->description() : null;
                            })
                            ->disabled(
                                fn(?string $operation, ?InventoryAdjustment $record) => $operation === 'edit' && $record?->status !== AdjustmentStatus::Draft
                            ),

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

                                        $query = \Zrm\Inventory\Models\InventoryItem::with('offering')
                                            ->when($companyId, fn($q) => $q->where('company_id', $companyId));

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

                                        $item = \Zrm\Inventory\Models\InventoryItem::with('offering')
                                            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
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

                                        $stockLevel = \Zrm\Inventory\Models\InventoryStockLevel::where('inventory_item_id', $state)
                                            ->where('warehouse_id', $warehouseId)
                                            ->first();

                                        $set('quantity_before', $stockLevel?->quantity_on_hand ?? 0);
                                        $set('unit_cost', $stockLevel?->average_unit_cost?->getAmount() ?? 0);
                                    })
                                    ->disabled(
                                        fn(?string $operation, Get $get) => $operation === 'edit' && $get('../../status') !== AdjustmentStatus::Draft->value
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
                                        $quantityAdjusted = $state - $before;
                                        $set('quantity_adjusted', $quantityAdjusted);
                                        $set('quantity', abs($quantityAdjusted));
                                    })
                                    ->disabled(
                                        fn(?string $operation, Get $get) => $operation === 'edit' && $get('../../status') !== AdjustmentStatus::Draft->value
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

                                // Batch allocation section - only visible for damage adjustments with outbound quantities
                                Forms\Components\Repeater::make('batch_allocations')
                                    ->label('Batch Allocations')
                                    ->relationship('batchAllocations')
                                    ->schema([
                                        Forms\Components\Select::make('inventory_batch_id')
                                            ->label('Batch')
                                            ->options(function (Get $get) {
                                                $itemId = $get('../../inventory_item_id');
                                                $warehouseId = $get('../../../../warehouse_id');

                                                if (! $itemId || ! $warehouseId) {
                                                    return [];
                                                }

                                                return \Zrm\Inventory\Models\InventoryBatch::where('inventory_item_id', $itemId)
                                                    ->where('warehouse_id', $warehouseId)
                                                    ->where('quantity_remaining', '>', 0)
                                                    ->get()
                                                    ->mapWithKeys(function ($batch) {
                                                        return [$batch->id => $batch->batch_number . ' (Available: ' . $batch->quantity_remaining . ' @ $' . number_format($batch->unit_cost / 100, 2) . ')'];
                                                    })
                                                    ->toArray();
                                            })
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (Forms\Set $set, ?int $state, Get $get) {
                                                if (! $state) {
                                                    return;
                                                }

                                                $batch = \Zrm\Inventory\Models\InventoryBatch::find($state);
                                                if ($batch) {
                                                    $set('unit_cost', $batch->unit_cost);
                                                    // Recalculate total_cost when unit_cost changes
                                                    $quantity = $get('quantity') ?? 0;
                                                    $set('total_cost', $quantity * $batch->unit_cost);
                                                }
                                            }),

                                        Forms\Components\TextInput::make('quantity')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->required()
                                            ->minValue(1)
                                            ->maxValue(function (Get $get) {
                                                $batchId = $get('inventory_batch_id');
                                                $adjustmentType = $get('../../../../adjustment_type');
                                                $quantityAdjusted = $get('../../quantity_adjusted') ?? 0;
                                                $currentAllocations = $get('batch_allocations') ?? [];

                                                if (! $batchId) {
                                                    return null;
                                                }

                                                $batch = \Zrm\Inventory\Models\InventoryBatch::find($batchId);
                                                if (! $batch) {
                                                    return null;
                                                }

                                                $maxFromBatch = $batch->quantity_remaining;

                                                // For damage adjustments, also limit by remaining needed quantity
                                                if ($adjustmentType === AdjustmentType::Damage->value && $quantityAdjusted < 0) {
                                                    $requiredTotal = abs($quantityAdjusted);
                                                    $currentTotal = collect($currentAllocations)->sum('quantity');
                                                    $remainingNeeded = $requiredTotal - $currentTotal;

                                                    return min($maxFromBatch, $remainingNeeded);
                                                }

                                                return $maxFromBatch;
                                            })
                                            ->rules([
                                                function (Get $get) {
                                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                        if ($value < 1) {
                                                            $fail('Quantity must be at least 1.');

                                                            return;
                                                        }

                                                        $batchId = $get('inventory_batch_id');
                                                        $adjustmentType = $get('../../../../adjustment_type');
                                                        $quantityAdjusted = $get('../../quantity_adjusted') ?? 0;
                                                        $currentAllocations = $get('batch_allocations') ?? [];

                                                        if (! $batchId) {
                                                            return;
                                                        }

                                                        $batch = \Zrm\Inventory\Models\InventoryBatch::find($batchId);
                                                        if (! $batch) {
                                                            return;
                                                        }

                                                        $maxFromBatch = $batch->quantity_remaining;

                                                        // For damage adjustments, also limit by remaining needed quantity
                                                        if ($adjustmentType === AdjustmentType::Damage->value && $quantityAdjusted < 0) {
                                                            $requiredTotal = abs($quantityAdjusted);
                                                            $currentTotal = collect($currentAllocations)->sum('quantity');
                                                            $remainingNeeded = $requiredTotal - $currentTotal;

                                                            $maxAllowed = min($maxFromBatch, $remainingNeeded);
                                                        } else {
                                                            $maxAllowed = $maxFromBatch;
                                                        }

                                                        if ($value > $maxAllowed) {
                                                            $fail("Quantity cannot exceed {$maxAllowed} (batch available: {$maxFromBatch}).");
                                                        }
                                                    };
                                                },
                                            ])
                                            ->live()

                                            ->afterStateUpdated(function (Forms\Set $set, ?int $state, Get $get) {
                                                $unitCost = $get('unit_cost') ?? 0;
                                                $set('total_cost', $state * $unitCost);
                                            }),

                                        Forms\Components\TextInput::make('unit_cost')
                                            ->label('Unit Cost')
                                            ->numeric()
                                            ->prefix('$')
                                            ->disabled()
                                            ->dehydrated(),

                                        Forms\Components\TextInput::make('total_cost')
                                            ->label('Total Cost')
                                            ->numeric()
                                            ->prefix('$')
                                            ->disabled()
                                            ->dehydrated()
                                            ->live()
                                            ->afterStateUpdated(function (Forms\Set $set, ?int $state, Get $get) {
                                                $batchId = $get('inventory_batch_id');
                                                $adjustmentType = $get('../../../../adjustment_type');
                                                $quantityAdjusted = $get('../../quantity_adjusted') ?? 0;
                                                $currentAllocations = $get('batch_allocations') ?? [];

                                                $text = 'Adjustment Type: ' . ($adjustmentType ? AdjustmentType::from($adjustmentType)->label() : 'N/A') . '. ';
                                                $text .= 'Quantity Adjusted: ' . abs($quantityAdjusted) . '. ';
                                                $totalAllocated = collect($currentAllocations)->sum('quantity');
                                                $text .= 'Total Allocated: ' . $totalAllocated . '. ';
                                                $remaining = max(0, abs($quantityAdjusted) - $totalAllocated);
                                                $text .= 'Remaining to Allocate: ' . $remaining . '. ';

                                                $batch = \Zrm\Inventory\Models\InventoryBatch::find($batchId);
                                                if ($batch) {
                                                    $text .= 'Max available in batch: ' . $batch->quantity_remaining . '. ';
                                                }

                                                $maxFromBatch = $batch->quantity_remaining ?? 0;

                                                $requiredTotal = abs($quantityAdjusted);
                                                $currentTotal = collect($currentAllocations)->sum('quantity');
                                                $remainingNeeded = $requiredTotal - $currentTotal;
                                                $maxAllowed = min($maxFromBatch, $remainingNeeded);

                                                $text .= 'Max allowed to allocate: ' . $maxAllowed . '.';

                                                Notification::make()
                                                    ->title($text)
                                                    ->icon('heroicon-o-document-text')
                                                    ->iconColor('success')
                                                    ->send();
                                            }),
                                    ])
                                    // ->columnSpanFull()
                                    ->addActionLabel('Add Batch')
                                    ->visible(fn(Get $get) => $get('../../adjustment_type') === AdjustmentType::Damage->value && ($get('quantity_adjusted') ?? 0) < 0)
                                    ->helperText('Select which batches to consume for this damage adjustment. Total allocated quantity must equal the adjustment amount.')
                                    ->rules([
                                        fn(Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            $adjustmentType = $get('../../adjustment_type');
                                            $quantityAdjusted = $get('quantity_adjusted') ?? 0;

                                            if ($adjustmentType === AdjustmentType::Damage->value && $quantityAdjusted < 0) {
                                                $totalAllocated = collect($value ?? [])->sum('quantity');
                                                $requiredQuantity = abs($quantityAdjusted);

                                                if ($totalAllocated !== $requiredQuantity) {
                                                    $fail("Total allocated quantity ({$totalAllocated}) must equal the adjustment amount ({$requiredQuantity}).");
                                                }
                                            }
                                        },
                                    ]),

                                Forms\Components\Textarea::make('reason')
                                    ->rows(2)
                                    ->columnSpanFull()
                                    ->disabled(
                                        fn(?string $operation, Get $get) => $operation === 'edit' && $get('../../status') !== AdjustmentStatus::Draft->value
                                    ),
                            ])
                            ->columns(5)
                            ->defaultItems(1)
                            ->addActionLabel('Add Item')
                            ->reorderable(false)
                            ->collapsible()
                            ->disabled(
                                fn(?string $operation, ?InventoryAdjustment $record) => $operation === 'edit' && $record?->status !== AdjustmentStatus::Draft
                            ),
                    ])
                    ->visible(fn(?string $operation) => $operation !== 'view'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('company_id', filament()->getTenant()->id))
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

                Tables\Columns\TextColumn::make('adjustment_type')
                    ->badge()
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

                Tables\Filters\SelectFilter::make('adjustment_type')
                    ->options(AdjustmentType::class)
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
                    ->visible(fn(InventoryAdjustment $record) => $record->status === AdjustmentStatus::Draft)
                    ->action(function (InventoryAdjustment $record) {
                        $record->approve(Auth::id());
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Adjustment')
                    ->modalDescription(fn(InventoryAdjustment $record) => $record->status === AdjustmentStatus::Approved
                        ? 'This adjustment has been approved and inventory movements have been created. Cancelling will reverse all inventory movements. Are you sure?'
                        : 'Are you sure you want to cancel this adjustment?')
                    ->visible(fn(InventoryAdjustment $record) => in_array($record->status, [AdjustmentStatus::Draft, AdjustmentStatus::Approved]))
                    ->action(function (InventoryAdjustment $record) {
                        $record->cancel();
                    }),

                Tables\Actions\EditAction::make()
                    ->visible(fn(InventoryAdjustment $record) => $record->status === AdjustmentStatus::Draft)
                    ->url(fn(InventoryAdjustment $record) => Pages\EditInventoryAdjustment::getUrl(['record' => $record])),

                Tables\Actions\ViewAction::make()
                    ->url(fn(InventoryAdjustment $record) => Pages\ViewInventoryAdjustment::getUrl(['record' => $record])),
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
            'view' => Pages\ViewInventoryAdjustment::route('/{record}'),
            'edit' => Pages\EditInventoryAdjustment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['warehouse', 'items.inventoryItem', 'items.batchAllocations.inventoryBatch']);
    }
}
