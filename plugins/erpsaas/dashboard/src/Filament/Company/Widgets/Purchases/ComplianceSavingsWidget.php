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

class ComplianceSavingsWidget extends EnhancedStatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected int | string | array $columnSpan = 1;

    protected ?string $heading = 'Compliance & Savings';

    protected function getStats(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($this->filters['endDate'] ?? now()->endOfMonth());

        $bills = Bill::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', '!=', BillStatus::Void)
            ->get();

        // Cost savings = discounts applied on bills
        $savingsRealized = $bills->sum('discount_total');

        // Maverick spend = bills with no vendor (uncontracted)
        $totalSpend = $bills->sum('total');
        $maverickSpend = Bill::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', '!=', BillStatus::Void)
            ->whereNull('vendor_id')
            ->sum('total');

        $maverickPct = $totalSpend > 0 ? ($maverickSpend / $totalSpend) * 100 : 0;

        $overdueCount = Bill::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', BillStatus::Overdue)
            ->count();

        return [
            EnhancedStatsOverviewWidget\EnhancedStat::make(
                'Cost Savings',
                CurrencyConverter::formatCentsToMoney($savingsRealized, $defaultCurrency)
            )
                ->description('Discounts & negotiated savings')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('success'),

            EnhancedStatsOverviewWidget\EnhancedStat::make(
                'Maverick Spend',
                Number::format($maverickPct, maxPrecision: 1) . '%'
            )
                ->description('Bills with no contracted vendor')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($maverickPct > 10 ? 'danger' : 'warning'),

            EnhancedStatsOverviewWidget\EnhancedStat::make(
                'Overdue Bills',
                Number::format($overdueCount)
            )
                ->description('Require immediate attention')
                ->descriptionIcon('heroicon-m-clock')
                ->color($overdueCount > 0 ? 'danger' : 'success'),
        ];
    }
}
