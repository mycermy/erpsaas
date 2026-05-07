<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets\Sales;

use Erpsaas\Core\Enums\Accounting\InvoiceStatus;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Models\User;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Utilities\Currency\CurrencyConverter;
use Filament\Facades\Filament;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;

class SalesTeamPerformanceWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Sales Team Performance';

    // public ?array $filters = null;

    public function table(Table $table): Table
    {
        $company = Filament::getTenant();
        $defaultCurrency = $company instanceof Company
            ? CompanySettingsService::getDefaultCurrency($company->getKey())
            : 'USD';

        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($this->filters['endDate'] ?? now()->endOfMonth());

        return $table
            ->query(
                User::query()
                    ->withCount(['createdInvoices as invoice_count' => function ($query) use ($startDate, $endDate, $company) {
                        $query->where('company_id', $company->getKey())
                            ->whereBetween('date', [$startDate, $endDate])
                            ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Void->value]);
                    }])
                    ->withSum(['createdInvoices as total_revenue' => function ($query) use ($startDate, $endDate, $company) {
                        $query->where('company_id', $company->getKey())
                            ->whereBetween('date', [$startDate, $endDate])
                            ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Void->value]);
                    }], 'total')
                    ->having('invoice_count', '>', 0)
                    ->orderByDesc('total_revenue')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('rank')
                    ->label('#')
                    ->rowIndex()
                    ->sortable(false),

                TextColumn::make('name')
                    ->label('Sales Rep')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice_count')
                    ->label('Deals Closed')
                    ->numeric()
                    ->sortable()
                    ->icon('heroicon-m-check-circle')
                    ->iconPosition(IconPosition::After),

                TextColumn::make('total_revenue')
                    ->label('Revenue Generated')
                    ->formatStateUsing(fn($state) => CurrencyConverter::formatCentsToMoney((int) $state, $defaultCurrency))
                    ->sortable()
                    ->icon('heroicon-m-banknotes')
                    ->iconPosition(IconPosition::After),

                TextColumn::make('avg_deal_size')
                    ->label('Avg Deal Size')
                    ->formatStateUsing(function ($record) use ($defaultCurrency) {
                        $avg = $record->invoice_count > 0 ? (int) ($record->total_revenue / $record->invoice_count) : 0;

                        return CurrencyConverter::formatCentsToMoney($avg, $defaultCurrency);
                    }),
            ])
            ->defaultSort('total_revenue', 'desc')
            ->paginated([5, 10]);
    }
}
