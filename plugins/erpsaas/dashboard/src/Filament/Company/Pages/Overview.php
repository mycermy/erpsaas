<?php

namespace Erpsaas\Dashboard\Filament\Company\Pages;

use Erpsaas\Dashboard\Filament\Company\Clusters\DashboardCluster;
use Erpsaas\Dashboard\Filament\Company\Widgets\BillStatusChartWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\FinancialStatsWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\InvoiceStatusChartWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\PurchaseMetricsWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\RevenueSpendChartWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\SalesMetricsWidget;
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
        return __('Financial insights, sales, and purchases analytics');
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
            RevenueSpendChartWidget::class,
            SalesMetricsWidget::class,
            InvoiceStatusChartWidget::class,
            PurchaseMetricsWidget::class,
            BillStatusChartWidget::class,
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
