<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets\Sales;

use Erpsaas\Accounts\Models\Accounting\Invoice;
use Erpsaas\Core\Enums\Accounting\InvoiceStatus;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Utilities\Currency\CurrencyConverter;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SalesForecastTableWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static string $view = 'erpsaas-dashboard::filament.company.widgets.sales.sales-forecast-table-widget';

    protected static ?string $heading = 'Sales Forecast Data';

    protected int | string | array $columnSpan = 'full';

    public function getForecastData(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $endDate = Carbon::parse($this->filters['endDate'] ?? now()->endOfMonth());
        $startMonth = $endDate->copy()->startOfMonth()->subMonths(5);
        $endMonth = $endDate->copy()->endOfMonth();

        $invoices = Invoice::query()
            ->where('company_id', $company instanceof Company ? $company->getKey() : null)
            ->whereBetween('date', [$startMonth, $endMonth])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->get();

        $labels = [];
        $actualData = [];

        // Calculate historical data
        for ($i = 0; $i < 6; $i++) {
            $month = $startMonth->copy()->addMonths($i);
            $labels[] = $month->format('M Y');

            $monthlyInvoices = $invoices->filter(function (Model $doc) use ($month): bool {
                return $doc->date !== null && $doc->date->isSameMonth($month);
            });

            $monthlyRevenue = $this->convertToDefaultCurrency($monthlyInvoices, 'total', $defaultCurrency);
            $actualData[] = $monthlyRevenue / 100;
        }

        // Calculate forecast
        $recentAvg = array_sum($actualData) / count($actualData);
        $growthRate = 0;
        if (count($actualData) >= 2) {
            $firstMonth = $actualData[0];
            $lastMonth = $actualData[count($actualData) - 1];

            if ($firstMonth > 0) {
                $totalGrowth = ($lastMonth - $firstMonth) / $firstMonth;
                $growthRate = $totalGrowth / 5;
            }
        }

        $growthRate = max(-0.2, min(0.5, $growthRate));

        $lastActualValue = end($actualData);
        $forecastBase = $lastActualValue > 0 ? $lastActualValue : $recentAvg;

        $forecastData = [];
        for ($i = 1; $i <= 3; $i++) {
            $forecastMonth = $endDate->copy()->addMonths($i);
            $labels[] = $forecastMonth->format('M Y');
            $forecastData[] = $forecastBase * (1 + ($growthRate * $i));
        }

        // Build data array
        $rows = [];

        // Historical data rows
        for ($i = 0; $i < 6; $i++) {
            $rows[] = [
                'month' => $labels[$i],
                'type' => 'Actual',
                'revenue' => $actualData[$i],
                'is_forecast' => false,
            ];
        }

        // Forecast data rows
        for ($i = 0; $i < 3; $i++) {
            $rows[] = [
                'month' => $labels[6 + $i],
                'type' => 'Forecast',
                'revenue' => $forecastData[$i],
                'is_forecast' => true,
            ];
        }

        return $rows;
    }

    protected function convertToDefaultCurrency(iterable $documents, string $column, string $defaultCurrency): int
    {
        $total = 0;

        foreach ($documents as $document) {
            $amount = (int) $document->getRawOriginal($column);
            $currency = $document->currency_code ?? $defaultCurrency;

            if ($currency === $defaultCurrency) {
                $total += $amount;
            } else {
                $total += CurrencyConverter::convertBalance($amount, $currency, $defaultCurrency);
            }
        }

        return $total;
    }
}
