<?php

namespace App\Filament\Company\Pages\Inventory;

use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryStockLevel;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\DB;

class InventoryReports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string $view = 'filament.company.pages.inventory.inventory-reports';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Inventory Reports';

    public ?array $data = [];

    public ?string $selectedReport = 'valuation';

    public ?array $reportData = null;

    public function mount(): void
    {
        $this->form->fill([
            'report_type' => 'valuation',
            'start_date' => now()->startOfMonth(),
            'end_date' => now(),
        ]);
        
        // Generate initial report
        $this->generateReport();
    }

    public function generateReport(): void
    {
        $this->selectedReport = $this->data['report_type'] ?? 'valuation';
        $this->reportData = $this->getReportData();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Report Parameters')
                    ->schema([
                        Select::make('report_type')
                            ->label('Report Type')
                            ->options([
                                'valuation' => 'Inventory Valuation',
                                'movements' => 'Stock Movements',
                                'low_stock' => 'Low Stock Items',
                                'turnover' => 'Inventory Turnover',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->selectedReport = $this->data['report_type'] ?? 'valuation'),

                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->default(now()->startOfMonth())
                            ->visible(fn ($get) => in_array($get('report_type'), ['movements', 'turnover'])),

                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->default(now())
                            ->visible(fn ($get) => in_array($get('report_type'), ['movements', 'turnover'])),

                        Select::make('warehouse_id')
                            ->label('Warehouse (Optional)')
                            ->options(function () {
                                return \App\Models\Inventory\Warehouse::where('company_id', filament()->getTenant()->id)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->placeholder('All Warehouses'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function getInventoryValuation(): array
    {
        $query = InventoryStockLevel::with(['inventoryItem.offering', 'warehouse'])
            ->whereHas('inventoryItem', function ($q) {
                $q->where('company_id', filament()->getTenant()->id);
            })
            ->where('quantity_on_hand', '>', 0); // Only show items with stock

        if (!empty($this->data['warehouse_id'])) {
            $query->where('warehouse_id', $this->data['warehouse_id']);
        }

        $stockLevels = $query->get();

        $totalValue = 0;
        $items = [];

        foreach ($stockLevels as $level) {
            if (!$level->average_cost) {
                continue;
            }

            // Handle both Money object and integer (cents)
            $averageCost = is_object($level->average_cost) 
                ? $level->average_cost->getAmount() 
                : $level->average_cost;
            
            $value = $level->quantity_on_hand * $averageCost;
            $totalValue += $value;

            $items[] = [
                'item' => $level->inventoryItem->offering->name ?? 'N/A',
                'sku' => $level->inventoryItem->sku ?? 'N/A',
                'warehouse' => $level->warehouse->name ?? 'N/A',
                'quantity' => $level->quantity_on_hand,
                'unit_cost' => $averageCost / 100,
                'total_value' => $value / 100,
            ];
        }

        return [
            'items' => collect($items)->sortByDesc('total_value')->values()->all(),
            'total_value' => $totalValue / 100,
            'total_items' => count($items),
        ];
    }

    public function getStockMovements(): array
    {
        $query = InventoryMovement::with(['inventoryItem.offering', 'warehouse'])
            ->whereHas('inventoryItem', function ($q) {
                $q->where('company_id', filament()->getTenant()->id);
            })
            ->whereBetween('movement_date', [
                $this->data['start_date'] ?? now()->startOfMonth(),
                $this->data['end_date'] ?? now(),
            ]);

        if (!empty($this->data['warehouse_id'])) {
            $query->where('warehouse_id', $this->data['warehouse_id']);
        }

        $movements = $query->orderBy('movement_date', 'desc')->limit(100)->get();

        return [
            'movements' => $movements->map(function ($movement) {
                return [
                    'date' => $movement->movement_date->format('Y-m-d H:i'),
                    'item' => $movement->inventoryItem->offering->name ?? 'N/A',
                    'warehouse' => $movement->warehouse->name ?? 'N/A',
                    'type' => $movement->movement_type->getLabel(),
                    'quantity' => $movement->quantity,
                    'unit_cost' => $movement->unit_cost && is_object($movement->unit_cost) 
                        ? $movement->unit_cost->getAmount() / 100 
                        : ($movement->unit_cost ? $movement->unit_cost / 100 : 0),
                    'notes' => $movement->notes ?? '',
                ];
            })->all(),
        ];
    }

    public function getLowStockItems(): array
    {
        // Join stock levels so we can safely compare quantity_available against the item's reorder_level
        $query = InventoryItem::with(['offering', 'stockLevels.warehouse'])
            ->where('inventory_items.company_id', filament()->getTenant()->id)
            ->where('inventory_items.active', true)
            ->join('inventory_stock_levels', 'inventory_items.id', '=', 'inventory_stock_levels.inventory_item_id')
            ->whereColumn('inventory_stock_levels.quantity_available', '<=', 'inventory_items.reorder_level')
            ->where('inventory_stock_levels.quantity_available', '>', 0)
            ->select('inventory_items.*')
            ->distinct();

        $items = $query->get();

        return [
            'items' => $items->map(function ($item) {
                return [
                    'item' => $item->offering->name ?? 'N/A',
                    'sku' => $item->sku ?? 'N/A',
                    'current_stock' => $item->stockLevels->sum('quantity_available'),
                    'reorder_level' => $item->reorder_level,
                    'reorder_quantity' => $item->reorder_quantity,
                    'warehouses' => $item->stockLevels
                        ->where('quantity_available', '<=', $item->reorder_level)
                        ->pluck('warehouse.name')
                        ->join(', '),
                ];
            })->all(),
        ];
    }

    public function getReportData(): array
    {
        return match ($this->selectedReport) {
            'valuation' => $this->getInventoryValuation(),
            'movements' => $this->getStockMovements(),
            'low_stock' => $this->getLowStockItems(),
            default => [],
        };
    }
}
