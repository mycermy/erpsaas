<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets;

use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Accounts\Models\Accounting\Invoice;
use Erpsaas\Core\Enums\Accounting\BillStatus;
use Erpsaas\Core\Enums\Accounting\InvoiceStatus;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Utilities\Currency\CurrencyConverter;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

class NetIncomeComparisonWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static string $view = 'erpsaas-dashboard::filament.company.widgets.net-income-comparison';

    protected int | string | array $columnSpan = 1;

    protected function getViewData(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $currentYearStart = now()->startOfYear();
        $currentYearEnd   = now()->endOfYear();
        $prevYearStart    = now()->subYear()->startOfYear();
        $prevYearEnd      = now()->subYear()->endOfYear();

        $currentInvoices = Invoice::query()
            ->whereBetween('date', [$currentYearStart, $currentYearEnd])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->get();

        $currentBills = Bill::query()
            ->whereBetween('date', [$currentYearStart, $currentYearEnd])
            ->where('status', '!=', BillStatus::Void)
            ->get();

        $prevInvoices = Invoice::query()
            ->whereBetween('date', [$prevYearStart, $prevYearEnd])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->get();

        $prevBills = Bill::query()
            ->whereBetween('date', [$prevYearStart, $prevYearEnd])
            ->where('status', '!=', BillStatus::Void)
            ->get();

        $currentIncome  = $currentInvoices->sumMoneyInDefaultCurrency('total');
        $currentExpense = $currentBills->sumMoneyInDefaultCurrency('total');
        $currentNet     = $currentIncome - $currentExpense;

        $prevIncome     = $prevInvoices->sumMoneyInDefaultCurrency('total');
        $prevExpense    = $prevBills->sumMoneyInDefaultCurrency('total');
        $prevNet        = $prevIncome - $prevExpense;

        $fmt = fn(int $v) => CurrencyConverter::formatCentsToMoney($v, $defaultCurrency);

        return [
            'previousYear' => now()->subYear()->year,
            'currentYear'  => now()->year,
            'rows'         => [
                [
                    'label'    => __('Income'),
                    'previous' => $fmt($prevIncome),
                    'current'  => $fmt($currentIncome),
                    'is_total' => false,
                ],
                [
                    'label'    => __('Expense'),
                    'previous' => $fmt($prevExpense),
                    'current'  => $fmt($currentExpense),
                    'is_total' => false,
                ],
                [
                    'label'    => __('Net Income'),
                    'previous' => $fmt($prevNet),
                    'current'  => $fmt($currentNet),
                    'is_total' => true,
                ],
            ],
        ];
    }
}
