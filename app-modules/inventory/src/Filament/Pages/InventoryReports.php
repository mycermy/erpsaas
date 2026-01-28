<?php

namespace Modules\Inventory\Filament\Pages;

use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryBatch;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\InventoryStockLevel;
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

    protected static string $view = 'inventory::filament.pages.reports';

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
                            ->afterStateUpdated(fn() => $this->selectedReport = $this->data['report_type'] ?? 'valuation'),

                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->default(now()->startOfMonth())
                            ->visible(fn($get) => in_array($get('report_type'), ['movements', 'turnover'])),

                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->default(now())
                            ->visible(fn($get) => in_array($get('report_type'), ['movements', 'turnover'])),

                        Select::make('warehouse_id')
                            ->label('Warehouse (Optional)')
                            ->options(function () {
                                return Warehouse::where('company_id', filament()->getTenant()->id)
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
        $companyId = filament()->getTenant()->id;

        // Get warehouse filter if specified
        $warehouseId = $this->data['warehouse_id'] ?? null;

        // Calculate inventory value from actual batches (accurate for FIFO/LIFO/Average)
        $batchQuery = InventoryBatch::with(['inventoryItem.offering', 'warehouse'])
            ->whereHas('inventoryItem', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->where('quantity_remaining', '>', 0);

        if ($warehouseId) {
            $batchQuery->where('warehouse_id', $warehouseId);
        }

        $batches = $batchQuery->get();

        $totalValue = 0;
        $itemGroups = [];

        // Group batches by item + warehouse for display
        foreach ($batches as $batch) {
            $key = $batch->inventory_item_id . '-' . $batch->warehouse_id;

            if (!isset($itemGroups[$key])) {
                $itemGroups[$key] = [
                    'item' => $batch->inventoryItem->offering->name ?? 'N/A',
                    'sku' => $batch->inventoryItem->sku ?? 'N/A',
                    'warehouse' => $batch->warehouse->name ?? 'N/A',
                    'quantity' => 0,
                    'total_cost' => 0,
                ];
            }

            $batchValue = $batch->quantity_remaining * $batch->unit_cost;
            $itemGroups[$key]['quantity'] += $batch->quantity_remaining;
            $itemGroups[$key]['total_cost'] += $batchValue;
            $totalValue += $batchValue;
        }

        // Calculate average cost per item group and format for display
        $items = [];
        foreach ($itemGroups as $group) {
            $avgCost = $group['quantity'] > 0 ? $group['total_cost'] / $group['quantity'] : 0;
            $items[] = [
                'item' => $group['item'],
                'sku' => $group['sku'],
                'warehouse' => $group['warehouse'],
                'quantity' => $group['quantity'],
                'unit_cost' => $avgCost / 100,
                'total_value' => $group['total_cost'] / 100,
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
