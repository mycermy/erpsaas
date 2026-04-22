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

class OverdueInvoicesBillsWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static string $view = 'erpsaas-dashboard::filament.company.widgets.overdue-invoices-bills';

    protected int | string | array $columnSpan = 1;

    protected function getViewData(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $overdueInvoiceRecords = Invoice::query()
            ->where('status', InvoiceStatus::Overdue)
            ->with('client')
            ->orderBy('due_date')
            ->get();

        $overdueBillRecords = Bill::query()
            ->where('status', BillStatus::Overdue)
            ->with('vendor')
            ->orderBy('due_date')
            ->get();

        $overdueInvoices = $overdueInvoiceRecords->map(fn(Invoice $invoice) => [
            'name'         => $invoice->client?->name ?? __('Unknown client'),
            'overdue_text' => $invoice->due_date
                ? $invoice->due_date->diffForHumans(null, true) . ' ' . __('ago')
                : __('No due date'),
            'amount'       => CurrencyConverter::formatCentsToMoney(
                (int) $invoice->getRawOriginal('amount_due'),
                $defaultCurrency
            ),
        ]);

        $overdueBills = $overdueBillRecords->map(fn(Bill $bill) => [
            'name'         => $bill->vendor?->name ?? __('Unknown vendor'),
            'overdue_text' => $bill->due_date
                ? $bill->due_date->diffForHumans(null, true) . ' ' . __('ago')
                : __('No due date'),
            'amount'       => CurrencyConverter::formatCentsToMoney(
                (int) $bill->getRawOriginal('amount_due'),
                $defaultCurrency
            ),
        ]);

        return [
            'overdueInvoices'      => $overdueInvoices,
            'overdueInvoiceCount'  => $overdueInvoiceRecords->count(),
            'overdueBills'         => $overdueBills,
            'overdueBillCount'     => $overdueBillRecords->count(),
        ];
    }
}
