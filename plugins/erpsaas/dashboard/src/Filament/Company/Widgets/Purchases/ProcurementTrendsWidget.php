<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets\Purchases;

use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Core\Enums\Accounting\BillStatus;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Utilities\Currency\CurrencyConverter;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ProcurementTrendsWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Procurement Trends';

    protected static ?string $maxHeight = '300px';

    protected static ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        // Anchor to filter end date, show rolling 6 months
        $endMonth = Carbon::parse($this->filters['endDate'] ?? now())->endOfMonth();
        $startMonth = $endMonth->copy()->startOfMonth()->subMonths(5);

        $bills = Bill::query()
            ->whereBetween('date', [$startMonth, $endMonth])
            ->where('status', '!=', BillStatus::Void)
            ->get();

        $labels = [];
        $spendData = [];
        $countData = [];

        for ($i = 0; $i < 6; $i++) {
            $month = $startMonth->copy()->addMonths($i);
            $labels[] = $month->format('M Y');

            $monthlyBills = $bills->filter(function (Model $bill) use ($month): bool {
                return $bill->date !== null && $bill->date->isSameMonth($month);
            });

            $spendData[] = $this->convertToDefaultCurrency($monthlyBills, 'total', $defaultCurrency) / 100;
            $countData[] = $monthlyBills->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Spend',
                    'data' => $spendData,
                    'borderColor' => '#f87171',
                    'backgroundColor' => 'rgba(248, 113, 113, 0.15)',
                    'fill' => true,
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Invoice Count',
                    'data' => $countData,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                    'fill' => false,
                    'tension' => 0.4,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'top'],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'position' => 'left',
                    'grid' => ['drawOnChartArea' => true],
                ],
                'y1' => [
                    'beginAtZero' => true,
                    'position' => 'right',
                    'grid' => ['drawOnChartArea' => false],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
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
