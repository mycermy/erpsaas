<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets;

use Erpsaas\Accounts\Models\Accounting\Invoice;
use Erpsaas\Core\Enums\Accounting\InvoiceStatus;
use Erpsaas\Core\Models\Company;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class InvoiceStatusChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Invoice Status Distribution';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $company = Filament::getTenant();
        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($this->filters['endDate'] ?? now()->endOfMonth());

        $invoices = Invoice::query()
            ->where('company_id', $company instanceof Company ? $company->getKey() : null)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->get();

        $breakdown = $invoices->groupBy('status')
            ->map(fn ($group) => $group->count());

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
            'scales' => [
                'x' => ['display' => false],
                'y' => ['display' => false],
            ],
        ];
    }
}
