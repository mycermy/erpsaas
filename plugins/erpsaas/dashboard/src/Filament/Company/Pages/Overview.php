<?php

namespace Erpsaas\Dashboard\Filament\Company\Pages;

use Erpsaas\Dashboard\Filament\Company\Clusters\DashboardCluster;
use Erpsaas\Dashboard\Filament\Company\Widgets\AgedReceivablesPayablesWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\CashFlowChartWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\ExpensesBreakdownChartWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\FinancialStatsWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\InvoiceStatusChartWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\NetIncomeComparisonWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\OverdueInvoicesBillsWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\RevenueSpendChartWidget;
use Erpsaas\Core\Filament\Forms\Components\DateRangeSelect;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;

class Overview extends Page
{
    use HasFiltersForm;

    // protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?int $navigationSort = 0;

    protected static ?string $cluster = DashboardCluster::class;

    protected static string $view = 'erpsaas-dashboard::filament.company.pages.dashboard';

    public static function getNavigationLabel(): string
    {
        return __('Overview');
    }

    public function getTitle(): string
    {
        return __('Overview');
    }

    public function getSubheading(): ?string
    {
        return __('Financial insights and accounting overview');
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
            FinancialStatsWidget::class,
        ];
    }

    public function getFooterWidgets(): array
    {
        return [
            // Row 1: Overdue list (1 col) | Cash flow chart (1 col)
            OverdueInvoicesBillsWidget::class,
            CashFlowChartWidget::class,
            // Row 2: Profit & Loss bar chart (full width)
            RevenueSpendChartWidget::class,
            // Row 3: Expenses breakdown (1 col) | Invoice status (1 col)
            ExpensesBreakdownChartWidget::class,
            InvoiceStatusChartWidget::class,
            // Row 4: Net income comparison (1 col) | Aged receivables & payables (1 col)
            NetIncomeComparisonWidget::class,
            AgedReceivablesPayablesWidget::class,
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
