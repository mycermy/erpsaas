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

class ConversionFunnelWidget extends EnhancedStatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Sales Funnel';

    protected function getStats(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($this->filters['endDate'] ?? now()->endOfMonth());

        // Funnel stages
        $allEstimates = Estimate::query()
            ->where('company_id', $company->getKey())
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $sentEstimates = $allEstimates->filter(fn ($e) => in_array($e->status, [
            EstimateStatus::Sent,
            EstimateStatus::Viewed,
            EstimateStatus::Accepted,
        ]));

        $viewedEstimates = $allEstimates->filter(fn ($e) => in_array($e->status, [
            EstimateStatus::Viewed,
            EstimateStatus::Accepted,
        ]));

        $acceptedEstimates = $allEstimates->filter(fn ($e) => $e->status === EstimateStatus::Accepted);

        $convertedToInvoice = Invoice::query()
            ->where('company_id', $company->getKey())
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->get();

        $totalEstimates = $allEstimates->count();
        $sentRate = $totalEstimates > 0 ? round(($sentEstimates->count() / $totalEstimates) * 100, 1) : 0;
        $viewRate = $sentEstimates->count() > 0 ? round(($viewedEstimates->count() / $sentEstimates->count()) * 100, 1) : 0;
        $acceptRate = $viewedEstimates->count() > 0 ? round(($acceptedEstimates->count() / $viewedEstimates->count()) * 100, 1) : 0;
        $convertRate = $totalEstimates > 0 ? round(($convertedToInvoice->count() / $totalEstimates) * 100, 1) : 0;

        return [
            EnhancedStatsOverviewWidget\EnhancedStat::make('Created', Number::format($totalEstimates))
                ->description(CurrencyConverter::formatCentsToMoney($allEstimates->sumMoneyInDefaultCurrency('total'), $defaultCurrency))
                ->descriptionIcon('heroicon-m-document-plus')
                ->color('info'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Sent', Number::format($sentEstimates->count()))
                ->description($sentRate . '% sent to clients')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('primary'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Viewed', Number::format($viewedEstimates->count()))
                ->description($viewRate . '% engagement rate')
                ->descriptionIcon('heroicon-m-eye')
                ->color('warning'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Converted', Number::format($convertedToInvoice->count()))
                ->description($convertRate . '% conversion to invoice')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
