<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets\Sales;

use Erpsaas\Accounts\Models\Accounting\Invoice;
use Erpsaas\Core\Enums\Accounting\InvoiceStatus;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Utilities\Currency\CurrencyConverter;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SalesForecastChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Sales Forecast';

    protected static ?string $maxHeight = '300px';

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $endDate = Carbon::parse($this->filters['endDate'] ?? now()->endOfMonth());

        // Historical data (last 6 months from filter date)
        $startMonth = $endDate->copy()->startOfMonth()->subMonths(5);
        $endMonth = $endDate->copy()->endOfMonth();

        $invoices = Invoice::query()
            ->whereBetween('date', [$startMonth, $endMonth])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->get();

        $labels = [];
        $actualData = [];
        $forecastData = [];

        // Calculate historical data
        for ($i = 0; $i < 6; $i++) {
            $month = $startMonth->copy()->addMonths($i);
            $labels[] = $month->format('M Y');

            $monthlyInvoices = $invoices->filter(function (Model $doc) use ($month): bool {
                return $doc->date !== null && $doc->date->isSameMonth($month);
            });

            $monthlyRevenue = $this->convertToDefaultCurrency($monthlyInvoices, 'total', $defaultCurrency);
            $actualData[] = $monthlyRevenue / 100;
        }

        // Calculate simple trend-based forecast for next 3 months
        $recentAvg = array_sum(array_slice($actualData, -3)) / 3;
        $growthRate = count($actualData) >= 2
            ? ($actualData[count($actualData) - 1] - $actualData[count($actualData) - 2]) / max(1, $actualData[count($actualData) - 2])
            : 0.05;

        // Forecast next 3 months
        for ($i = 1; $i <= 3; $i++) {
            $forecastMonth = $endDate->copy()->addMonths($i);
            $labels[] = $forecastMonth->format('M Y');
            $actualData[] = null; // No actual data yet
            $forecastData[] = $recentAvg * (1 + ($growthRate * $i));
        }

        // Fill forecast line with nulls for historical months
        $forecastData = array_pad([], count($actualData) - 3, null) + $forecastData;

        return [
            'datasets' => [
                [
                    'label' => 'Actual Revenue',
                    'data' => $actualData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.2)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Forecast',
                    'data' => $forecastData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderDash' => [5, 5],
                    'fill' => false,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { return "$" + value.toLocaleString(); }',
                    ],
                ],
            ],
        ];
    }

    protected function convertToDefaultCurrency(iterable $documents, string $column, string $defaultCurrency): int
    {
        $total = 0;

        foreach ($documents as $document) {
            $amount = (int) $document->getRawOriginal($column);
            $currency = $document->currency_code ?? $defaultCurrency;

            if ($currency === $defaultCurrency) {
                $total += $amount;
            } else {
                $total += CurrencyConverter::convertBalance($amount, $currency, $defaultCurrency);
            }
        }

        return $total;
    }
}
