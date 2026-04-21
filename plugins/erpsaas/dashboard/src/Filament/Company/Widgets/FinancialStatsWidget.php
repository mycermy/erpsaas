<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets;

use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Accounts\Models\Accounting\Invoice;
use Erpsaas\Core\Enums\Accounting\BillStatus;
use Erpsaas\Core\Enums\Accounting\InvoiceStatus;
use Erpsaas\Core\Filament\Company\Widgets\EnhancedStatsOverviewWidget;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Utilities\Currency\CurrencyConverter;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class FinancialStatsWidget extends EnhancedStatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $thisMonth = $this->monthRange(now());
        $lastMonth = $this->monthRange(now()->subMonth());

        $invoicesThisMonth = Invoice::query()
            ->whereBetween('date', [$thisMonth['start'], $thisMonth['end']])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->get();

        $invoicesLastMonth = Invoice::query()
            ->whereBetween('date', [$lastMonth['start'], $lastMonth['end']])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->get();

        $collectionsThisMonth = Invoice::query()
            ->whereBetween('paid_at', [$thisMonth['start'], $thisMonth['end']])
            ->get();

        $paymentsThisMonth = Bill::query()
            ->whereBetween('paid_at', [$thisMonth['start'], $thisMonth['end']])
            ->get();

        $openReceivables = Invoice::query()->unpaid()->get();
        $openPayables = Bill::query()->unpaid()->get();

        $revenue = $invoicesThisMonth->sumMoneyInDefaultCurrency('total');
        $revenueLastMonth = $invoicesLastMonth->sumMoneyInDefaultCurrency('total');
        $cashIn = $collectionsThisMonth->sumMoneyInDefaultCurrency('amount_paid');
        $cashOut = $paymentsThisMonth->sumMoneyInDefaultCurrency('amount_paid');
        $receivables = $openReceivables->sumMoneyInDefaultCurrency('amount_due');
        $payables = $openPayables->sumMoneyInDefaultCurrency('amount_due');
        $netCashFlow = $cashIn - $cashOut;

        return [
            EnhancedStatsOverviewWidget\EnhancedStat::make('Revenue this month', CurrencyConverter::formatCentsToMoney($revenue, $defaultCurrency))
                ->description($this->getChangeDescription($revenue, $revenueLastMonth))
                ->descriptionIcon($revenue >= $revenueLastMonth ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenue >= $revenueLastMonth ? 'success' : 'warning')
                ->chart($this->generateMiniChart($invoicesThisMonth, 'total', 7)),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Collected', CurrencyConverter::formatCentsToMoney($cashIn, $defaultCurrency))
                ->description(Number::format($collectionsThisMonth->count()) . ' invoices paid')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart($this->generateMiniChart($collectionsThisMonth, 'amount_paid', 7)),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Open receivables', CurrencyConverter::formatCentsToMoney($receivables, $defaultCurrency))
                ->description(Number::format($openReceivables->count()) . ' unpaid invoices')
                ->descriptionIcon('heroicon-m-wallet')
                ->color('warning'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Open payables', CurrencyConverter::formatCentsToMoney($payables, $defaultCurrency))
                ->description(Number::format($openPayables->count()) . ' bills to settle')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('danger'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Net cash flow', CurrencyConverter::formatCentsToMoney($netCashFlow, $defaultCurrency))
                ->description('In ' . CurrencyConverter::formatCentsToMoney($cashIn, $defaultCurrency) . ' · Out ' . CurrencyConverter::formatCentsToMoney($cashOut, $defaultCurrency))
                ->descriptionIcon($netCashFlow >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($netCashFlow >= 0 ? 'success' : 'danger'),
        ];
    }

    protected function monthRange(Carbon $date): array
    {
        return [
            'start' => $date->copy()->startOfMonth(),
            'end' => $date->copy()->endOfMonth(),
        ];
    }

    protected function getChangeDescription(int $current, int $previous): string
    {
        if ($previous === 0) {
            return 'First month baseline';
        }

        $difference = $current - $previous;
        $percentage = abs(($difference / $previous) * 100);
        $direction = $difference >= 0 ? 'up' : 'down';

        return Number::format($percentage, maxPrecision: 1) . '% ' . $direction;
    }

    protected function generateMiniChart($collection, string $column, int $days): array
    {
        $data = [];
        $startDate = now()->subDays($days - 1)->startOfDay();

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dayTotal = $collection->filter(function ($item) use ($date) {
                $itemDate = $item->paid_at ?? $item->date ?? null;

                return $itemDate && $itemDate->isSameDay($date);
            })->sumMoneyInDefaultCurrency($column);

            $data[] = $dayTotal / 100; // Convert cents to units for chart
        }

        return $data;
    }
}
