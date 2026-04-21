<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets\Sales;

use Erpsaas\Accounts\Models\Accounting\Estimate;
use Erpsaas\Core\Enums\Accounting\EstimateStatus;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class DealStageChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Deals by Stage';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($this->filters['endDate'] ?? now()->endOfMonth());

        $estimates = Estimate::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $breakdown = $estimates->groupBy('status')
            ->map(fn($group) => $group->count());

        $labels = [];
        $data = [];
        $colors = [];

        $statusColors = [
            'unsent' => 'rgb(156, 163, 175)',
            'sent' => 'rgb(59, 130, 246)',
            'viewed' => 'rgb(147, 51, 234)',
            'accepted' => 'rgb(34, 197, 94)',
            'declined' => 'rgb(239, 68, 68)',
            'expired' => 'rgb(249, 115, 22)',
        ];

        foreach (EstimateStatus::cases() as $status) {
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
