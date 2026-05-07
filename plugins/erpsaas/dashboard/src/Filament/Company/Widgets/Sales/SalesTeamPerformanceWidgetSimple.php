<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets\Sales;

use Erpsaas\Accounts\Models\Accounting\Invoice;
use Erpsaas\Core\Enums\Accounting\InvoiceStatus;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Services\CompanySettingsService;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class SalesTeamPerformanceWidgetSimple extends Widget
{
    use InteractsWithPageFilters;

    protected static string $view = 'erpsaas-dashboard::filament.company.widgets.sales.sales-team-performance';

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Sales Team Performance (Simple)';

    protected function getViewData(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($this->filters['endDate'] ?? now()->endOfMonth());

        $salesData = Invoice::query()
            ->with('createdBy:id,name')
            ->where('company_id', $company->getKey())
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->whereNotNull('created_by')
            ->get()
            ->groupBy('created_by')
            ->map(function ($invoices, $userId) {
                $user = $invoices->first()->createdBy;
                $invoiceCount = $invoices->count();
                $totalRevenue = $invoices->sum('total');
                $avgDealSize = $invoiceCount > 0 ? $totalRevenue / $invoiceCount : 0;

                return [
                    'name' => $user?->name ?? __('Unknown'),
                    'invoice_count' => $invoiceCount,
                    'total_revenue' => $totalRevenue,
                    'avg_deal_size' => $avgDealSize,
                ];
            })
            ->sortByDesc('total_revenue')
            ->take(10)
            ->values();

        return [
            'salesData' => $salesData,
            'defaultCurrency' => $defaultCurrency,
        ];
    }
}
