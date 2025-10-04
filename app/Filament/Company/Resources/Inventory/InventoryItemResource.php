<?php

namespace App\Filament\Company\Resources\Inventory;

use App\Enums\Inventory\TrackMethod;
use App\Filament\Company\Resources\Inventory\InventoryItemResource\Pages;
use App\Filament\Company\Resources\Inventory\InventoryItemResource\RelationManagers\BatchesRelationManager;
use App\Filament\Company\Resources\Inventory\InventoryItemResource\RelationManagers\StockLevelsRelationManager;
use App\Models\Inventory\InventoryItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'offering.name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Select::make('offering_id')
                            ->relationship('offering', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Select the product/service to track inventory for'),

                        Forms\Components\TextInput::make('sku')
                            ->label('SKU')
                            ->maxLength(255)
                            ->helperText('Stock Keeping Unit - unique identifier for this item'),

                        Forms\Components\Select::make('track_method')
                            ->options(TrackMethod::class)
                            ->default(TrackMethod::FIFO)
                            ->required()
                            ->helperText('Method used to calculate cost of goods sold'),

                        Forms\Components\Toggle::make('track_batches')
                            ->label('Track Batches/Lots')
                            ->default(true)
                            ->helperText('Enable batch/lot tracking for detailed cost tracking'),

                        Forms\Components\Toggle::make('active')
                            ->default(true)
                            ->helperText('Inactive items cannot be used in new transactions'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Inventory Settings')
                    ->schema([
                        Forms\Components\TextInput::make('reorder_level')
                            ->label('Reorder Level')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Alert when stock falls below this level'),

                        Forms\Components\TextInput::make('reorder_quantity')
                            ->label('Reorder Quantity')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Suggested quantity to order when restocking'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Account Mapping')
                    ->schema([
                        Forms\Components\Select::make('inventory_account_id')
                            ->label('Inventory Asset Account')
                            ->relationship('inventoryAccount', 'name', fn (Builder $query) => $query->where('category', 'asset'))
                            ->searchable()
                            ->preload()
                            ->helperText('Balance sheet account to track inventory value'),

                        Forms\Components\Select::make('cogs_account_id')
                            ->label('COGS Expense Account')
                            ->relationship('cogsAccount', 'name', fn (Builder $query) => $query->where('category', 'expense'))
                            ->searchable()
                            ->preload()
                            ->helperText('Income statement account for cost of goods sold'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('offering.name')
                    ->label('Item')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('track_method')
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_on_hand')
                    ->label('Total Stock')
                    ->getStateUsing(fn (InventoryItem $record) => $record->stockLevels()->sum('quantity_on_hand'))
                    ->numeric()
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->withSum('stockLevels', 'quantity_on_hand')
                        ->orderBy('stock_levels_sum_quantity_on_hand', $direction)
                    ),

                Tables\Columns\TextColumn::make('total_available')
                    ->label('Available')
                    ->getStateUsing(fn (InventoryItem $record) => $record->stockLevels()->sum('quantity_available'))
                    ->numeric()
                    ->color(fn (InventoryItem $record) => $record->isLowStock() ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('reorder_level')
                    ->numeric()
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                Tables\Columns\IconColumn::make('track_batches')
                    ->boolean()
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('track_method')
                    ->options(TrackMethod::class)
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('active')
                    ->default(true),

                Tables\Filters\TernaryFilter::make('low_stock')
                    ->label('Low Stock')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('stockLevels', function (Builder $query) {
                            $query->whereColumn('quantity_available', '<=', 'inventory_items.reorder_level');
                        }),
                        false: fn (Builder $query) => $query->whereHas('stockLevels', function (Builder $query) {
                            $query->whereColumn('quantity_available', '>', 'inventory_items.reorder_level');
                        }),
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('adjust_stock')
                    ->label('Adjust')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->url(fn (InventoryItem $record) => InventoryAdjustmentResource::getUrl('create', [
                        'tenant' => filament()->getTenant(),
                    ])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            StockLevelsRelationManager::class,
            BatchesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryItems::route('/'),
            'create' => Pages\CreateInventoryItem::route('/create'),
            'edit' => Pages\EditInventoryItem::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['offering', 'stockLevels']);
    }
}
