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
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class SalesMetricsWidget extends EnhancedStatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $thisMonth = $this->monthRange(now());

        $invoicesThisMonth = Invoice::query()
            ->whereBetween('date', [$thisMonth['start'], $thisMonth['end']])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->get();

        $estimatesThisMonth = Estimate::query()
            ->whereBetween('date', [$thisMonth['start'], $thisMonth['end']])
            ->get();

        $unpaidInvoices = Invoice::query()->unpaid()->get();

        $dueSoon = $unpaidInvoices->filter(function (Invoice $invoice): bool {
            return $invoice->due_date !== null
                && $invoice->due_date->betweenIncluded(today(), today()->copy()->addDays(14));
        });

        $activeEstimateStatuses = [EstimateStatus::Unsent, EstimateStatus::Sent, EstimateStatus::Viewed];
        $activeEstimates = Estimate::query()
            ->whereIn('status', $activeEstimateStatuses)
            ->get();

        return [
            EnhancedStatsOverviewWidget\EnhancedStat::make('Invoices issued', Number::format($invoicesThisMonth->count()))
                ->description(CurrencyConverter::formatCentsToMoney($invoicesThisMonth->sumMoneyInDefaultCurrency('total'), $defaultCurrency) . ' booked')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Active pipeline', CurrencyConverter::formatCentsToMoney($activeEstimates->sumMoneyInDefaultCurrency('total'), $defaultCurrency))
                ->description(Number::format($activeEstimates->count()) . ' live estimates')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('warning'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Due in 14 days', CurrencyConverter::formatCentsToMoney($dueSoon->sumMoneyInDefaultCurrency('amount_due'), $defaultCurrency))
                ->description(Number::format($dueSoon->count()) . ' invoices need attention')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('New estimates', Number::format($estimatesThisMonth->count()))
                ->description(CurrencyConverter::formatCentsToMoney($estimatesThisMonth->sumMoneyInDefaultCurrency('total'), $defaultCurrency) . ' potential')
                ->descriptionIcon('heroicon-m-document-plus')
                ->color('success'),
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
