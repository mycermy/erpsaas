<?php

namespace Erpsaas\Dashboard\Filament\Company\Pages;

use Erpsaas\Core\Filament\Forms\Components\DateRangeSelect;
use Erpsaas\Dashboard\Filament\Company\Clusters\DashboardCluster;
use Erpsaas\Dashboard\Filament\Company\Widgets\Sales\ConversionFunnelWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\Sales\DealStageChartWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\Sales\SalesForecastChartWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\Sales\SalesPipelineWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\Sales\SalesTeamPerformanceWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\Sales\SalesTeamPerformanceWidgetSimple;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;

class SalesDashboard extends Page
{
    use HasFiltersForm;

    protected static ?int $navigationSort = 1;

    protected static ?string $cluster = DashboardCluster::class;

    protected static string $view = 'erpsaas-dashboard::filament.company.pages.dashboard';

    public static function getNavigationLabel(): string
    {
        return __('Sales Dashboard');
    }

    public function getTitle(): string
    {
        return __('Sales Performance');
    }

    public function getSubheading(): ?string
    {
        return __('Pipeline health, forecasting, and team performance');
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
                ->visible(fn (Get $get) => $get('dateRange') === 'Custom'),
            DatePicker::make('endDate')
                ->label('End Date')
                ->default(now()->endOfMonth()->toDateString())
                ->visible(fn (Get $get) => $get('dateRange') === 'Custom'),
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
            SalesPipelineWidget::class,
        ];
    }

    public function getFooterWidgets(): array
    {
        return [
            SalesForecastChartWidget::class,
            ConversionFunnelWidget::class,
            DealStageChartWidget::class,
            SalesTeamPerformanceWidget::class,
            SalesTeamPerformanceWidgetSimple::class,
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
