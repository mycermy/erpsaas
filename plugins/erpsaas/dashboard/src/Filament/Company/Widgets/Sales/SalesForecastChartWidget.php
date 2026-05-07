<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets\Sales;

use Erpsaas\Accounts\Models\Accounting\Invoice;
use Erpsaas\Core\Enums\Accounting\InvoiceStatus;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Utilities\Currency\CurrencyConverter;
use Filament\Facades\Filament;
use Filament\Support\RawJs;
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
            ->where('company_id', $company instanceof Company ? $company->getKey() : null)
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

        // Calculate trend-based forecast using 6 months of historical data
        // Use average of all 6 months for more stable baseline
        $recentAvg = array_sum($actualData) / count($actualData);

        // Calculate growth rate over the full 6-month period
        $growthRate = 0;
        if (count($actualData) >= 2) {
            $firstMonth = $actualData[0];
            $lastMonth = $actualData[count($actualData) - 1];

            if ($firstMonth > 0) {
                // Calculate total growth rate over 6 months
                $totalGrowth = ($lastMonth - $firstMonth) / $firstMonth;
                // Convert to monthly growth rate
                $growthRate = $totalGrowth / 5; // 5 intervals across 6 months
            }
        }

        // Cap growth rate: don't go below -20% or above 50% monthly
        $growthRate = max(-0.2, min(0.5, $growthRate));

        // Use last actual month as starting point for forecast
        $lastActualValue = end($actualData);
        $forecastBase = $lastActualValue > 0 ? $lastActualValue : $recentAvg;

        // Initialize forecast arrays - start from the last actual month for smooth connection
        $forecastDataValues = array_fill(0, 5, null); // First 5 months are null
        $upperBoundValues = array_fill(0, 5, null);
        $lowerBoundValues = array_fill(0, 5, null);

        // Add the connection point (last actual value)
        $forecastDataValues[] = $lastActualValue;
        $upperBoundValues[] = $lastActualValue * 1.2; // +20% confidence
        $lowerBoundValues[] = $lastActualValue * 0.8; // -20% confidence

        // Forecast next 3 months with confidence bounds
        for ($i = 1; $i <= 3; $i++) {
            $forecastMonth = $endDate->copy()->addMonths($i);
            $labels[] = $forecastMonth->format('M Y');
            $actualData[] = null; // No actual data yet

            $forecastValue = $forecastBase * (1 + ($growthRate * $i));
            $forecastDataValues[] = $forecastValue;
            $upperBoundValues[] = $forecastValue * 1.2; // +20% confidence
            $lowerBoundValues[] = $forecastValue * 0.8; // -20% confidence
        }

        $forecastData = $forecastDataValues;
        $upperBound = $upperBoundValues;
        $lowerBound = $lowerBoundValues;

        return [
            'datasets' => [
                [
                    'label' => 'Actual Revenue',
                    'data' => $actualData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.2)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 2,
                    'fill' => false,
                    'tension' => 0.4,
                    'pointRadius' => 3,
                ],
                [
                    'label' => 'Forecast',
                    'data' => $forecastData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 2,
                    'borderDash' => [5, 5],
                    'fill' => false,
                    'tension' => 0.4,
                    'pointRadius' => 3,
                ],
                [
                    'label' => 'Confidence Range',
                    'data' => $lowerBound,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.05)',
                    'borderColor' => 'rgba(59, 130, 246, 0.2)',
                    'borderWidth' => 0.5,
                    'borderDash' => [2, 2],
                    'fill' => false,
                    'tension' => 0.4,
                    'pointRadius' => 0,
                ],
                [
                    'label' => 'Upper Bound',
                    'data' => $upperBound,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                    'borderColor' => 'rgba(59, 130, 246, 0.2)',
                    'borderWidth' => 0.5,
                    'borderDash' => [2, 2],
                    'fill' => '-1',
                    'tension' => 0.4,
                    'pointRadius' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            filter: function(legendItem, chartData) {
                                // Hide the confidence bound datasets from legend
                                return legendItem.text !== 'Confidence Range' && legendItem.text !== 'Upper Bound';
                            }
                        }
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => {
                                if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
                                if (value >= 1000) return (value / 1000).toFixed(1) + 'K';
                                return value;
                            },
                        },
                    },
                },
            }
        JS);
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
