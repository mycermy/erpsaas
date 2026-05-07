<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets;

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

class SalesMetricsWidget extends EnhancedStatsOverviewWidget
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

        $invoicesThisMonth = Invoice::query()
            ->where('company_id', $company instanceof Company ? $company->getKey() : null)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->get();

        $estimatesThisMonth = Estimate::query()
            ->where('company_id', $company instanceof Company ? $company->getKey() : null)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $unpaidInvoices = Invoice::query()
            ->where('company_id', $company instanceof Company ? $company->getKey() : null)
            ->unpaid()
            ->get();

        $dueSoon = $unpaidInvoices->filter(function (Invoice $invoice): bool {
            return $invoice->due_date !== null
                && $invoice->due_date->betweenIncluded(today(), today()->copy()->addDays(14));
        });

        $activeEstimateStatuses = [EstimateStatus::Unsent, EstimateStatus::Sent, EstimateStatus::Viewed];
        $activeEstimates = Estimate::query()
            ->where('company_id', $company instanceof Company ? $company->getKey() : null)
            ->whereIn('status', $activeEstimateStatuses)
            ->get();

        return [
            EnhancedStatsOverviewWidget\EnhancedStat::make('Invoices issued', Number::abbreviate($invoicesThisMonth->count(), maxPrecision: 1))
                ->description(CurrencyConverter::formatCentsToMoneyAbbreviated($invoicesThisMonth->sumMoneyInDefaultCurrency('total'), $defaultCurrency) . ' booked')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Active pipeline', CurrencyConverter::formatCentsToMoneyAbbreviated($activeEstimates->sumMoneyInDefaultCurrency('total'), $defaultCurrency))
                ->description(Number::abbreviate($activeEstimates->count(), maxPrecision: 1) . ' live estimates')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('warning'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Due in 14 days', CurrencyConverter::formatCentsToMoneyAbbreviated($dueSoon->sumMoneyInDefaultCurrency('amount_due'), $defaultCurrency))
                ->description(Number::abbreviate($dueSoon->count(), maxPrecision: 1) . ' invoices need attention')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('New estimates', Number::abbreviate($estimatesThisMonth->count(), maxPrecision: 1))
                ->description(CurrencyConverter::formatCentsToMoneyAbbreviated($estimatesThisMonth->sumMoneyInDefaultCurrency('total'), $defaultCurrency) . ' potential')
                ->descriptionIcon('heroicon-m-document-plus')
                ->color('success'),
        ];
    }
}
