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
use Illuminate\Support\Carbon;

class AgedReceivablesPayablesWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static string $view = 'erpsaas-dashboard::filament.company.widgets.aged-receivables-payables';

    protected int | string | array $columnSpan = 1;

    protected function getViewData(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $today = Carbon::today();

        $unpaidInvoices = Invoice::query()
            ->whereNotIn('status', [InvoiceStatus::Paid, InvoiceStatus::Void, InvoiceStatus::Draft])
            ->get();

        $unpaidBills = Bill::query()
            ->whereNotIn('status', [BillStatus::Paid, BillStatus::Void])
            ->get();

        return [
            'receivables' => $this->buildAgingBuckets($unpaidInvoices, $today, $defaultCurrency),
            'payables'    => $this->buildAgingBuckets($unpaidBills, $today, $defaultCurrency),
        ];
    }

    protected function buildAgingBuckets(iterable $documents, Carbon $today, string $defaultCurrency): array
    {
        $buckets = [
            'coming_due' => 0,
            '1_30'       => 0,
            '31_60'      => 0,
            '61_90'      => 0,
            'over_90'    => 0,
        ];

        foreach ($documents as $doc) {
            $amount   = (int) $doc->getRawOriginal('amount_due');
            $currency = $doc->currency_code ?? $defaultCurrency;

            if ($currency !== $defaultCurrency) {
                $amount = CurrencyConverter::convertBalance($amount, $currency, $defaultCurrency);
            }

            if ($doc->due_date === null || $doc->due_date->gte($today)) {
                $buckets['coming_due'] += $amount;
            } elseif ($doc->due_date->gte($today->copy()->subDays(30))) {
                $buckets['1_30'] += $amount;
            } elseif ($doc->due_date->gte($today->copy()->subDays(60))) {
                $buckets['31_60'] += $amount;
            } elseif ($doc->due_date->gte($today->copy()->subDays(90))) {
                $buckets['61_90'] += $amount;
            } else {
                $buckets['over_90'] += $amount;
            }
        }

        $fmt = fn(int $v) => CurrencyConverter::formatCentsToMoney($v, $defaultCurrency);

        return [
            ['label' => __('Coming due'),         'amount' => $fmt($buckets['coming_due'])],
            ['label' => __('1–30 days overdue'),  'amount' => $fmt($buckets['1_30'])],
            ['label' => __('31–60 days overdue'), 'amount' => $fmt($buckets['31_60'])],
            ['label' => __('61–90 days overdue'), 'amount' => $fmt($buckets['61_90'])],
            ['label' => __('>90 days overdue'),   'amount' => $fmt($buckets['over_90'])],
        ];
    }
}
