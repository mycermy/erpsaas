<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets;

use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Accounts\Models\Accounting\Invoice;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Utilities\Currency\CurrencyConverter;
use Filament\Facades\Filament;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class CashFlowChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Cash Flow';

    protected static ?string $description = 'Always displays cash basis (paid)';

    protected static ?string $maxHeight = '280px';

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $endDate = now()->endOfMonth();
        $startDate = $endDate->copy()->subMonths(11)->startOfMonth();

        $invoices = Invoice::query()
            ->where('company_id', $company->getKey())
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get();

        $bills = Bill::query()
            ->where('company_id', $company->getKey())
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get();

        $labels = [];
        $inflowData = [];
        $outflowData = [];
        $netData = [];

        for ($i = 0; $i < 12; $i++) {
            $month = $startDate->copy()->addMonths($i);
            $labels[] = $month->format("M'y");

            $monthlyInflow = $invoices
                ->filter(fn($inv) => $inv->paid_at?->isSameMonth($month))
                ->sum(fn($inv) => $this->toDefaultCurrency($inv, 'amount_paid', $defaultCurrency));

            $monthlyOutflow = $bills
                ->filter(fn($bill) => $bill->paid_at?->isSameMonth($month))
                ->sum(fn($bill) => $this->toDefaultCurrency($bill, 'amount_paid', $defaultCurrency));

            $inflow = round($monthlyInflow / 100, 2);
            $outflow = round($monthlyOutflow / 100, 2);

            $inflowData[] = $inflow;
            $outflowData[] = $outflow;
            $netData[] = round($inflow - $outflow, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => __('Inflow'),
                    'data' => $inflowData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.15)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => __('Outflow'),
                    'data' => $outflowData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.15)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => __('Net change'),
                    'data' => $netData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'fill' => false,
                    'tension' => 0.4,
                    'borderDash' => [5, 5],
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
                    },
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: (value) => {
                                const absValue = Math.abs(value);
                                const sign = value < 0 ? '-' : '';

                                if (absValue >= 1000000) return sign + (absValue / 1000000).toFixed(1) + 'M';
                                if (absValue >= 1000) return sign + (absValue / 1000).toFixed(1) + 'K';
                                return value;
                            },
                        },
                    },
                },
            }
        JS);
    }

    protected function toDefaultCurrency($document, string $column, string $defaultCurrency): int
    {
        $amount = (int) $document->getRawOriginal($column);
        $currency = $document->currency_code ?? $defaultCurrency;

        if ($currency === $defaultCurrency) {
            return $amount;
        }

        return CurrencyConverter::convertBalance($amount, $currency, $defaultCurrency);
    }
}
