<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets;

use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Accounts\Models\Accounting\Invoice;
use Erpsaas\Core\Enums\Accounting\BillStatus;
use Erpsaas\Core\Enums\Accounting\InvoiceStatus;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Utilities\Currency\CurrencyConverter;
use Filament\Facades\Filament;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Model;

class RevenueSpendChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Profit and loss';

    protected static ?string $description = 'Accrual (paid & unpaid)';

    protected static ?string $maxHeight = '300px';

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $endDate = now()->endOfMonth();
        $startMonth = $endDate->copy()->subMonths(11)->startOfMonth();
        $endMonth = $endDate->copy()->endOfMonth();

        $invoices = Invoice::query()
            ->where('company_id', $company->getKey())
            ->whereBetween('date', [$startMonth, $endMonth])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->get();

        $bills = Bill::query()
            ->where('company_id', $company->getKey())
            ->whereBetween('date', [$startMonth, $endMonth])
            ->where('status', '!=', BillStatus::Void)
            ->get();

        $labels = [];
        $revenueData = [];
        $spendData = [];

        for ($i = 0; $i < 12; $i++) {
            $month = $startMonth->copy()->addMonths($i);
            $labels[] = $month->format("M'y");

            $monthlyInvoices = $invoices->filter(function (Model $doc) use ($month): bool {
                return $doc->date !== null && $doc->date->isSameMonth($month);
            });

            $monthlyBills = $bills->filter(function (Model $doc) use ($month): bool {
                return $doc->date !== null && $doc->date->isSameMonth($month);
            });

            $revenueData[] = $this->convertToDefaultCurrency($monthlyInvoices, 'total', $defaultCurrency) / 100;
            $spendData[] = $this->convertToDefaultCurrency($monthlyBills, 'total', $defaultCurrency) / 100;
        }

        return [
            'datasets' => [
                [
                    'label' => __('Income'),
                    'data' => $revenueData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.7)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 0,
                ],
                [
                    'label' => __('Expenses'),
                    'data' => $spendData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.7)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
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
