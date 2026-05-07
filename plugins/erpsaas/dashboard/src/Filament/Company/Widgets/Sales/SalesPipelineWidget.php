<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets\Sales;

use Erpsaas\Accounts\Models\Accounting\Estimate;
use Erpsaas\Accounts\Models\Accounting\Invoice;
use Erpsaas\Core\Enums\Accounting\EstimateStatus;
use Erpsaas\Core\Enums\Accounting\InvoiceStatus;
use Erpsaas\Core\Filament\Company\Widgets\EnhancedStatsOverviewWidget;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Utilities\Currency\CurrencyConverter;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class SalesPipelineWidget extends EnhancedStatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Sales Pipeline Overview';

    protected function getStats(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($this->filters['endDate'] ?? now()->endOfMonth());

        $activeEstimateStatuses = [EstimateStatus::Unsent, EstimateStatus::Sent, EstimateStatus::Viewed];
        $activeEstimates = Estimate::query()
            ->where('company_id', $company instanceof Company ? $company->getKey() : null)
            ->whereIn('status', $activeEstimateStatuses)
            ->get();

        $allEstimates = Estimate::query()
            ->where('company_id', $company instanceof Company ? $company->getKey() : null)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $wonDeals = Invoice::query()
            ->where('company_id', $company instanceof Company ? $company->getKey() : null)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->get();

        $lostEstimates = Estimate::query()
            ->where('company_id', $company instanceof Company ? $company->getKey() : null)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', [EstimateStatus::Declined, EstimateStatus::Expired])
            ->get();

        $pipelineValue = $activeEstimates->sumMoneyInDefaultCurrency('total');
        $totalEstimateCount = $allEstimates->count();
        $wonCount = $wonDeals->count();
        $lostCount = $lostEstimates->count();

        $winRate = ($totalEstimateCount > 0)
            ? round(($wonCount / $totalEstimateCount) * 100, 1)
            : 0;

        $averageDealSize = $wonCount > 0
            ? $wonDeals->sumMoneyInDefaultCurrency('total') / $wonCount
            : 0;

        // Sales cycle calculation (days from estimate to invoice)
        $cycleData = Invoice::query()
            ->where('company_id', $company instanceof Company ? $company->getKey() : null)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->get()
            ->map(function ($invoice) {
                // Assuming invoice was created from estimate, calculate days difference
                return $invoice->created_at->diffInDays($invoice->date);
            });

        $avgSalesCycle = $cycleData->isNotEmpty() ? round($cycleData->average()) : 0;

        return [
            EnhancedStatsOverviewWidget\EnhancedStat::make('Pipeline Value', CurrencyConverter::formatCentsToMoneyAbbreviated($pipelineValue, $defaultCurrency))
                ->description(Number::abbreviate($activeEstimates->count(), maxPrecision: 1) . ' active opportunities')
                ->descriptionIcon('heroicon-m-funnel')
                ->color('info'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Win Rate', $winRate . '%')
                ->description($wonCount . ' won, ' . $lostCount . ' lost this month')
                ->descriptionIcon($winRate >= 30 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($winRate >= 30 ? 'success' : 'warning'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Avg Deal Size', CurrencyConverter::formatCentsToMoneyAbbreviated((int) $averageDealSize, $defaultCurrency))
                ->description('Based on ' . Number::abbreviate($wonCount, maxPrecision: 1) . ' closed deals')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Sales Cycle', $avgSalesCycle . ' days')
                ->description('Average time to close')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
        ];
    }
}
