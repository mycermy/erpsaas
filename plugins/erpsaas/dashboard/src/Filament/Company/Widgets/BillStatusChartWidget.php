<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets;

use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Core\Enums\Accounting\BillStatus;
use Erpsaas\Core\Models\Company;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class BillStatusChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Bill Status Distribution';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $company = Filament::getTenant();
        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($this->filters['endDate'] ?? now()->endOfMonth());

        $bills = Bill::query()
            ->where('company_id', $company instanceof Company ? $company->getKey() : null)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', '!=', BillStatus::Void)
            ->get();

        $breakdown = $bills->groupBy('status')
            ->map(fn ($group) => $group->count());

        $labels = [];
        $data = [];
        $colors = [];

        $statusColors = [
            'open' => 'rgb(59, 130, 246)',
            'overdue' => 'rgb(239, 68, 68)',
            'partial' => 'rgb(249, 115, 22)',
            'paid' => 'rgb(34, 197, 94)',
        ];

        foreach (BillStatus::cases() as $status) {
            if ($status === BillStatus::Void) {
                continue;
            }

            $count = $breakdown->get($status->value, 0);

            if ($count > 0) {
                $labels[] = $status->getLabel();
                $data[] = $count;
                $colors[] = $statusColors[$status->value] ?? 'rgb(156, 163, 175)';
            }
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
