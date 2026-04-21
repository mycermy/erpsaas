<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets;

use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Core\Enums\Accounting\BillStatus;
use Erpsaas\Core\Filament\Company\Widgets\EnhancedStatsOverviewWidget;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Utilities\Currency\CurrencyConverter;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class PurchaseMetricsWidget extends EnhancedStatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $thisMonth = $this->monthRange(now());

        $billsThisMonth = Bill::query()
            ->whereBetween('date', [$thisMonth['start'], $thisMonth['end']])
            ->where('status', '!=', BillStatus::Void)
            ->get();

        $unpaidBills = Bill::query()->unpaid()->get();

        $dueSoon = $unpaidBills->filter(function (Bill $bill): bool {
            return $bill->due_date !== null
                && $bill->due_date->betweenIncluded(today(), today()->copy()->addDays(7));
        });

        $overdueBills = $unpaidBills->filter(fn(Bill $bill): bool => $bill->status === BillStatus::Overdue);

        return [
            EnhancedStatsOverviewWidget\EnhancedStat::make('Bills logged', Number::format($billsThisMonth->count()))
                ->description(CurrencyConverter::formatCentsToMoney($billsThisMonth->sumMoneyInDefaultCurrency('total'), $defaultCurrency) . ' committed')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Total unpaid', CurrencyConverter::formatCentsToMoney($unpaidBills->sumMoneyInDefaultCurrency('amount_due'), $defaultCurrency))
                ->description(Number::format($unpaidBills->count()) . ' bills outstanding')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Due this week', CurrencyConverter::formatCentsToMoney($dueSoon->sumMoneyInDefaultCurrency('amount_due'), $defaultCurrency))
                ->description(Number::format($dueSoon->count()) . ' bills due in 7 days')
                ->descriptionIcon('heroicon-m-clock')
                ->color('danger'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Overdue', CurrencyConverter::formatCentsToMoney($overdueBills->sumMoneyInDefaultCurrency('amount_due'), $defaultCurrency))
                ->description(Number::format($overdueBills->count()) . ' vendor payments late')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }

    protected function monthRange(Carbon $date): array
    {
        return [
            'start' => $date->copy()->startOfMonth(),
            'end' => $date->copy()->endOfMonth(),
        ];
    }
}
