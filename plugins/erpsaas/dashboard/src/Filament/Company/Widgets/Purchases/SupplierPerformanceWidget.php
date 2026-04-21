<?php

namespace Erpsaas\Dashboard\Filament\Company\Widgets\Purchases;

use Erpsaas\Core\Enums\Accounting\BillStatus;
use Erpsaas\Core\Models\Common\Vendor;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Utilities\Currency\CurrencyConverter;
use Filament\Facades\Filament;
use Filament\Support\Enums\IconPosition;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class SupplierPerformanceWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Top Vendors by Spend & Performance';

    protected int | string | array $columnSpan = 'full';

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
                Vendor::query()
                    ->withSum(['bills as total_spend' => function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('date', [$startDate, $endDate])
                            ->where('status', '!=', BillStatus::Void->value);
                    }], 'total')
                    ->withCount(['bills as invoices_count' => function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('date', [$startDate, $endDate])
                            ->where('status', '!=', BillStatus::Void->value);
                    }])
                    ->withSum(['bills as amount_paid_sum' => function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('date', [$startDate, $endDate])
                            ->where('status', '!=', BillStatus::Void->value);
                    }], 'amount_paid')
                    ->having('total_spend', '>', 0)
                    ->orderByDesc('total_spend')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoices_count')
                    ->label('Invoices')
                    ->numeric()
                    ->sortable()
                    ->icon('heroicon-m-document-text')
                    ->iconPosition(IconPosition::After),

                Tables\Columns\TextColumn::make('total_spend')
                    ->label('Total Spend')
                    ->formatStateUsing(fn($state) => CurrencyConverter::formatCentsToMoney((int) $state, $defaultCurrency))
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount_paid_sum')
                    ->label('Paid')
                    ->formatStateUsing(fn($state) => CurrencyConverter::formatCentsToMoney((int) $state, $defaultCurrency))
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_rate')
                    ->label('Payment Rate')
                    ->getStateUsing(function ($record): string {
                        $spend = (int) $record->total_spend;
                        $paid = (int) $record->amount_paid_sum;

                        return $spend > 0
                            ? Number::format(($paid / $spend) * 100, maxPrecision: 1) . '%'
                            : '—';
                    })
                    ->badge()
                    ->color(function ($record): string {
                        $spend = (int) $record->total_spend;
                        $paid = (int) $record->amount_paid_sum;
                        $rate = $spend > 0 ? ($paid / $spend) * 100 : 0;

                        return $rate >= 90 ? 'success' : ($rate >= 50 ? 'warning' : 'danger');
                    }),
            ])
            ->paginated(false);
    }
}
