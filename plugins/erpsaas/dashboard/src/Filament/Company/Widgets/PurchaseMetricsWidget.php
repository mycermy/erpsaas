<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets;

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

class PurchaseMetricsWidget extends EnhancedStatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($this->filters['endDate'] ?? now()->endOfMonth());

        $billsThisMonth = Bill::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', '!=', BillStatus::Void)
            ->get();

        $unpaidBills = Bill::query()->unpaid()->get();

        $dueSoon = $unpaidBills->filter(function (Bill $bill): bool {
            return $bill->due_date !== null
                && $bill->due_date->betweenIncluded(today(), today()->copy()->addDays(7));
        });

        $overdueBills = $unpaidBills->filter(fn (Bill $bill): bool => $bill->status === BillStatus::Overdue);

        return [
            EnhancedStatsOverviewWidget\EnhancedStat::make('Bills logged', Number::abbreviate($billsThisMonth->count(), maxPrecision: 1))
                ->description(CurrencyConverter::formatCentsToMoneyAbbreviated($billsThisMonth->sumMoneyInDefaultCurrency('total'), $defaultCurrency) . ' committed')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Total unpaid', CurrencyConverter::formatCentsToMoneyAbbreviated($unpaidBills->sumMoneyInDefaultCurrency('amount_due'), $defaultCurrency))
                ->description(Number::abbreviate($unpaidBills->count(), maxPrecision: 1) . ' bills outstanding')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Due this week', CurrencyConverter::formatCentsToMoneyAbbreviated($dueSoon->sumMoneyInDefaultCurrency('amount_due'), $defaultCurrency))
                ->description(Number::abbreviate($dueSoon->count(), maxPrecision: 1) . ' bills due in 7 days')
                ->descriptionIcon('heroicon-m-clock')
                ->color('danger'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Overdue', CurrencyConverter::formatCentsToMoneyAbbreviated($overdueBills->sumMoneyInDefaultCurrency('amount_due'), $defaultCurrency))
                ->description(Number::abbreviate($overdueBills->count(), maxPrecision: 1) . ' vendor payments late')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
