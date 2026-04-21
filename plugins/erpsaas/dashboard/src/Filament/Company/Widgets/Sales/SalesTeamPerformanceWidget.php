<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets\Sales;

use Erpsaas\Accounts\Models\Accounting\Invoice;
use Erpsaas\Core\Enums\Accounting\InvoiceStatus;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Utilities\Currency\CurrencyConverter;
use Filament\Facades\Filament;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class SalesTeamPerformanceWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Sales Team Performance';

    public ?array $filters = null;

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
                Invoice::query()
                    ->with('createdBy:id,name')
                    ->whereBetween('date', [$startDate, $endDate])
                    ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
                    ->selectRaw('created_by, COUNT(*) as invoice_count, SUM(total) as total_revenue')
                    ->groupBy('created_by')
                    ->orderByDesc('total_revenue')
            )
            ->columns([
                TextColumn::make('rank')
                    ->label('#')
                    ->rowIndex()
                    ->sortable(false),

                TextColumn::make('createdBy.name')
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
