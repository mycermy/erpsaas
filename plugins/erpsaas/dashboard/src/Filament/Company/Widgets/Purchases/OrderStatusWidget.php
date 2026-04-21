<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets\Purchases;

use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Core\Enums\Accounting\BillStatus;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class OrderStatusWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Purchase Order Status';

    protected static ?string $maxHeight = '300px';

    protected static ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($this->filters['endDate'] ?? now()->endOfMonth());

        $counts = Bill::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotIn('status', [BillStatus::Void])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'datasets' => [
                [
                    'label' => 'Bills',
                    'data' => [
                        $counts->get(BillStatus::Open->value, 0),
                        $counts->get(BillStatus::Partial->value, 0),
                        $counts->get(BillStatus::Paid->value, 0),
                        $counts->get(BillStatus::Overdue->value, 0),
                    ],
                    'backgroundColor' => [
                        '#3b82f6', // Open — Blue
                        '#f59e0b', // Partial — Amber
                        '#10b981', // Paid — Green
                        '#ef4444', // Overdue — Red
                    ],
                ],
            ],
            'labels' => ['Open', 'Partial', 'Paid', 'Overdue'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'bottom'],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
