<?php

namespace Zrm\Inventory\Filament\Widgets;

use Zrm\Inventory\Models\InventoryItem;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockAlertWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Low Stock Alerts';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Join inventory_stock_levels so we can safely compare quantity_available to the item's reorder_level
                InventoryItem::query()
                    ->where('inventory_items.company_id', filament()->getTenant()->id)
                    ->where('inventory_items.active', true)
                    ->join('inventory_stock_levels', 'inventory_items.id', '=', 'inventory_stock_levels.inventory_item_id')
                    ->whereColumn('inventory_stock_levels.quantity_available', '<=', 'inventory_items.reorder_level')
                    ->where('inventory_stock_levels.quantity_available', '>', 0)
                    ->with(['offering', 'stockLevels.warehouse'])
                    ->select('inventory_items.*')
                    ->distinct()
            )
            ->columns([
                Tables\Columns\TextColumn::make('offering.name')
                    ->label('Item')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Current Stock')
                    ->getStateUsing(fn (InventoryItem $record) => $record->stockLevels()->sum('quantity_available'))
                    ->numeric()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('reorder_level')
                    ->numeric(),

                Tables\Columns\TextColumn::make('reorder_quantity')
                    ->label('Suggested Order Qty')
                    ->numeric(),

                Tables\Columns\TextColumn::make('warehouses')
                    ->label('Affected Warehouses')
                    ->getStateUsing(function (InventoryItem $record) {
                        return $record->stockLevels()
                            ->with('warehouse')
                            ->where('quantity_available', '<=', $record->reorder_level)
                            ->get()
                            ->pluck('warehouse.name')
                            ->join(', ');
                    })
                    ->wrap(),
            ])
            ->actions([
                Tables\Actions\Action::make('restock')
                    ->label('Create PO')
                    ->icon('heroicon-o-shopping-cart')
                    ->url(fn (InventoryItem $record) => \App\Filament\Company\Resources\Purchases\BillResource::getUrl('create', [
                        'tenant' => filament()->getTenant(),
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
