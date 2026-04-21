<?php

namespace Erpsaas\Dashboard\Filament\Company\Pages;

use Erpsaas\Dashboard\Filament\Company\Clusters\DashboardCluster;
use Erpsaas\Dashboard\Filament\Company\Widgets\Purchases\ComplianceSavingsWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\Purchases\OrderStatusWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\Purchases\ProcurementTrendsWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\Purchases\ProductCategoryAnalysisWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\Purchases\PurchasesKpiSummaryWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\Purchases\SupplierPerformanceWidget;
use Erpsaas\Core\Filament\Forms\Components\DateRangeSelect;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;

class PurchasesDashboard extends Page
{
    use HasFiltersForm;

    protected static ?int $navigationSort = 2;

    protected static ?string $cluster = DashboardCluster::class;

    protected static string $view = 'erpsaas-dashboard::filament.company.pages.dashboard';

    public static function getNavigationLabel(): string
    {
        return __('Purchases Dashboard');
    }

    public function getTitle(): string
    {
        return __('Purchases Performance');
    }

    public function getSubheading(): ?string
    {
        return __('Monitor supplier performance, manage purchase orders, and track savings');
    }

    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            DateRangeSelect::make('dateRange')
                ->label('Period')
                ->startDateField('startDate')
                ->endDateField('endDate')
                ->default('M-' . now()->format('Y-m'))
                ->selectablePlaceholder(false),
            DatePicker::make('startDate')
                ->label('Start Date')
                ->default(now()->startOfMonth()->toDateString())
                ->visible(fn(Get $get) => $get('dateRange') === 'Custom'),
            DatePicker::make('endDate')
                ->label('End Date')
                ->default(now()->endOfMonth()->toDateString())
                ->visible(fn(Get $get) => $get('dateRange') === 'Custom'),
        ]);
    }

    public function getWidgetData(): array
    {
        return [
            'filters' => $this->filters,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PurchasesKpiSummaryWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            ProcurementTrendsWidget::class,
            SupplierPerformanceWidget::class,
            OrderStatusWidget::class,
            ComplianceSavingsWidget::class,
            ProductCategoryAnalysisWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,
            'md' => 2,
        ];
    }
}
