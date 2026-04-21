<?php

namespace Erpsaas\Dashboard\Filament\Company\Pages;

use Erpsaas\Dashboard\Filament\Company\Clusters\DashboardCluster;
use Erpsaas\Dashboard\Filament\Company\Widgets\BillStatusChartWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\FinancialStatsWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\InvoiceStatusChartWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\PurchaseMetricsWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\RevenueSpendChartWidget;
use Erpsaas\Dashboard\Filament\Company\Widgets\SalesMetricsWidget;
use Filament\Pages\Page;

class Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?int $navigationSort = 0;

    protected static ?string $cluster = DashboardCluster::class;

    protected static string $view = 'erpsaas-dashboard::filament.company.pages.dashboard';

    public static function getNavigationLabel(): string
    {
        return __('Dashboard');
    }

    public function getTitle(): string
    {
        return __('Dashboard');
    }

    public function getSubheading(): ?string
    {
        return __('Financial insights, sales, and purchases analytics');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FinancialStatsWidget::class,
            RevenueSpendChartWidget::class,
        ];
    }

    public function getFooterWidgets(): array
    {
        return [
            SalesMetricsWidget::class,
            InvoiceStatusChartWidget::class,
            PurchaseMetricsWidget::class,
            BillStatusChartWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return 1;
    }
}
