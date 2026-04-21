<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets\Purchases;

use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Core\Enums\Accounting\BillStatus;
use Erpsaas\Core\Filament\Company\Widgets\EnhancedStatsOverviewWidget;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Utilities\Currency\CurrencyConverter;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class PurchasesKpiSummaryWidget extends EnhancedStatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Purchases Overview';

    protected function getStats(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($this->filters['endDate'] ?? now()->endOfMonth());

        // Previous period (same duration, immediately before)
        $periodDays = $startDate->diffInDays($endDate);
        $prevStart = $startDate->copy()->subDays($periodDays + 1);
        $prevEnd = $startDate->copy()->subDay();

        $bills = Bill::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', '!=', BillStatus::Void)
            ->get();

        $prevBills = Bill::query()
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->where('status', '!=', BillStatus::Void)
            ->get();

        $totalSpend = $bills->sum('total');
        $prevTotalSpend = $prevBills->sum('total');
        $invoiceCount = $bills->count();
        $prevInvoiceCount = $prevBills->count();
        $avgValue = $invoiceCount > 0 ? (int) ($totalSpend / $invoiceCount) : 0;
        $prevAvgValue = $prevInvoiceCount > 0 ? (int) ($prevTotalSpend / $prevInvoiceCount) : 0;
        $totalPaid = $bills->sum('amount_paid');
        $totalDue = $bills->sum('amount_due');

        return [
            EnhancedStatsOverviewWidget\EnhancedStat::make(
                'Total Spend',
                CurrencyConverter::formatCentsToMoney($totalSpend, $defaultCurrency)
            )
                ->description($this->getDeltaDescription($totalSpend, $prevTotalSpend))
                ->descriptionIcon($totalSpend <= $prevTotalSpend ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up')
                ->color($totalSpend <= $prevTotalSpend ? 'success' : 'danger')
                ->chart($this->buildSparkline($bills, 'total', $startDate, $endDate)),

            EnhancedStatsOverviewWidget\EnhancedStat::make(
                'Purchase Invoices',
                Number::format($invoiceCount)
            )
                ->description($this->getDeltaDescription($invoiceCount, $prevInvoiceCount, false))
                ->descriptionIcon($invoiceCount <= $prevInvoiceCount ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up')
                ->color($invoiceCount <= $prevInvoiceCount ? 'success' : 'warning'),

            EnhancedStatsOverviewWidget\EnhancedStat::make(
                'Avg Invoice Value',
                CurrencyConverter::formatCentsToMoney($avgValue, $defaultCurrency)
            )
                ->description($this->getDeltaDescription($avgValue, $prevAvgValue))
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),

            EnhancedStatsOverviewWidget\EnhancedStat::make(
                'Outstanding Payables',
                CurrencyConverter::formatCentsToMoney($totalDue, $defaultCurrency)
            )
                ->description(CurrencyConverter::formatCentsToMoney($totalPaid, $defaultCurrency) . ' paid of ' . CurrencyConverter::formatCentsToMoney($totalSpend, $defaultCurrency))
                ->descriptionIcon('heroicon-m-credit-card')
                ->color($totalDue > 0 ? 'warning' : 'success'),
        ];
    }

    protected function getDeltaDescription(int|float $current, int|float $previous, bool $lowerIsBetter = true): string
    {
        if ($previous == 0) {
            return 'No prior period data';
        }

        $pct = abs((($current - $previous) / $previous) * 100);
        $up = $current >= $previous;
        $direction = $up ? 'up' : 'down';
        $label = $lowerIsBetter
            ? ($up ? '↑ ' : '↓ ')
            : ($up ? '↑ ' : '↓ ');

        return $label . Number::format($pct, maxPrecision: 1) . '% vs prior period';
    }

    protected function buildSparkline($collection, string $column, Carbon $start, Carbon $end): array
    {
        $days = max(1, $start->diffInDays($end));
        $data = [];

        for ($i = 0; $i <= min($days, 6); $i++) {
            $date = $start->copy()->addDays((int) ($i * ($days / 6)));
            $data[] = $collection->filter(function ($item) use ($date) {
                return $item->date && $item->date->isSameDay($date);
            })->sum($column) / 100;
        }

        return $data;
    }
}
