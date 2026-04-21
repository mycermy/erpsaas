<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets\Purchases;

use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Core\Enums\Accounting\BillStatus;
use Erpsaas\Core\Models\Common\Vendor;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Utilities\Currency\CurrencyConverter;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class ProductCategoryAnalysisWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Top Vendors by Spend';

    protected static ?string $maxHeight = '300px';

    protected static ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($this->filters['endDate'] ?? now()->endOfMonth());

        $topVendors = Vendor::query()
            ->withSum(['bills as total_spend' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate])
                    ->where('status', '!=', BillStatus::Void->value);
            }], 'total')
            ->having('total_spend', '>', 0)
            ->orderByDesc('total_spend')
            ->limit(6)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Total Spend',
                    'data' => $topVendors->map(fn($v) => (int) ($v->total_spend / 100))->values()->toArray(),
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                    ],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $topVendors->pluck('name')->values()->toArray(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => ['beginAtZero' => true],
                'y' => ['grid' => ['display' => false]],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
