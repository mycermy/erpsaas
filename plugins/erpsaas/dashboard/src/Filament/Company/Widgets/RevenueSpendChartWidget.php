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
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

class RevenueSpendChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue vs Spend Trend';

    protected static ?string $maxHeight = '300px';

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $startMonth = now()->copy()->startOfMonth()->subMonths(5);
        $endMonth = now()->copy()->endOfMonth();

        $invoices = Invoice::query()
            ->whereBetween('date', [$startMonth, $endMonth])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->get();

        $bills = Bill::query()
            ->whereBetween('date', [$startMonth, $endMonth])
            ->where('status', '!=', BillStatus::Void)
            ->get();

        $labels = [];
        $revenueData = [];
        $spendData = [];

        for ($i = 0; $i < 6; $i++) {
            $month = $startMonth->copy()->addMonths($i);
            $labels[] = $month->format('M Y');

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
                    'label' => 'Revenue',
                    'data' => $revenueData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.2)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Spend',
                    'data' => $spendData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.2)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'fill' => true,
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
