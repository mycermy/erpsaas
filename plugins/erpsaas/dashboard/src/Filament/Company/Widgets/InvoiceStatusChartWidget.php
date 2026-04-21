<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets;

use Erpsaas\Accounts\Models\Accounting\Invoice;
use Erpsaas\Core\Enums\Accounting\InvoiceStatus;
use Filament\Widgets\ChartWidget;

class InvoiceStatusChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Invoice Status Distribution';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $invoices = Invoice::query()
            ->whereBetween('date', [now()->startOfYear(), now()->endOfYear()])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->get();

        $breakdown = $invoices->groupBy('status')
            ->map(fn($group) => $group->count());

        $labels = [];
        $data = [];
        $colors = [];

        $statusColors = [
            'unsent' => 'rgb(156, 163, 175)',
            'sent' => 'rgb(59, 130, 246)',
            'viewed' => 'rgb(147, 51, 234)',
            'partial' => 'rgb(249, 115, 22)',
            'paid' => 'rgb(34, 197, 94)',
            'overdue' => 'rgb(239, 68, 68)',
            'overpaid' => 'rgb(16, 185, 129)',
        ];

        foreach (InvoiceStatus::cases() as $status) {
            if (in_array($status, [InvoiceStatus::Draft, InvoiceStatus::Void])) {
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
