<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets;

use Erpsaas\Accounts\Models\Accounting\Account;
use Erpsaas\Accounts\Models\Accounting\Transaction;
use Erpsaas\Core\Enums\Accounting\AccountCategory;
use Erpsaas\Core\Enums\Accounting\TransactionType;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Utilities\Currency\CurrencyConverter;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class ExpensesBreakdownChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Expenses breakdown';

    protected static ?string $description = 'Accrual (paid & unpaid)';

    protected static ?string $maxHeight = '280px';

    protected int | string | array $columnSpan = 1;

    // Distinct palette matching Wave's coloring style
    private const COLORS = [
        'rgb(99, 102, 241)',   // indigo
        'rgb(34, 197, 94)',    // green
        'rgb(249, 115, 22)',   // orange
        'rgb(59, 130, 246)',   // blue
        'rgb(239, 68, 68)',    // red
        'rgb(168, 85, 247)',   // purple
        'rgb(20, 184, 166)',   // teal
        'rgb(234, 179, 8)',    // yellow
        'rgb(236, 72, 153)',   // pink
        'rgb(156, 163, 175)',  // gray (catch-all)
    ];

    protected function getData(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfYear());
        $endDate   = Carbon::parse($this->filters['endDate'] ?? now()->endOfYear());

        // Aggregate expense transactions by account
        $rows = Transaction::query()
            ->where('type', TransactionType::Withdrawal)
            ->whereBetween('posted_at', [$startDate, $endDate])
            ->whereHas('account', fn($q) => $q->where('category', AccountCategory::Expense))
            ->with('account:id,name,currency_code')
            ->get()
            ->groupBy('account_id')
            ->map(function ($group) use ($defaultCurrency) {
                $account = $group->first()->account;
                $total   = $group->sum(function ($tx) use ($defaultCurrency, $account) {
                    $amount   = (int) $tx->getRawOriginal('amount');
                    $currency = $account?->currency_code ?? $defaultCurrency;

                    return $currency === $defaultCurrency
                        ? $amount
                        : CurrencyConverter::convertBalance($amount, $currency, $defaultCurrency);
                });

                return [
                    'name'  => $account?->name ?? __('Unknown'),
                    'total' => $total,
                ];
            })
            ->sortByDesc('total')
            ->values();

        $grandTotal = $rows->sum('total');

        $labels = [];
        $data   = [];
        $colors = [];

        foreach ($rows as $index => $row) {
            $pct      = $grandTotal > 0 ? round($row['total'] / $grandTotal * 100) : 0;
            $labels[] = $pct . '% ' . $row['name'];
            $data[]   = round($row['total'] / 100, 2);
            $colors[] = self::COLORS[$index % count(self::COLORS)];
        }

        return [
            'datasets' => [
                [
                    'data'            => $data,
                    'backgroundColor' => $colors,
                    'hoverOffset'     => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display'  => true,
                    'position' => 'right',
                    'labels'   => [
                        'boxWidth' => 12,
                        'font'     => ['size' => 11],
                    ],
                ],
                'tooltip' => [
                    'callbacks' => [],
                ],
            ],
            'cutout' => '65%',
        ];
    }
}
