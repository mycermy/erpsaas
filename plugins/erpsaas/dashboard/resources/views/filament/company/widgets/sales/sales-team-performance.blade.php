<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('Sales Team Performance') }}</x-slot>

        @if($salesData->isEmpty())
            <div class="flex items-center justify-center gap-2 rounded-lg px-3 py-8 bg-gray-50 dark:bg-white/5">
                <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5 text-gray-400" />
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No sales data available for this period') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">#</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">{{ __('Sales Rep') }}</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">{{ __('Deals Closed') }}</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">{{ __('Revenue Generated') }}</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">{{ __('Avg Deal Size') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($salesData as $index => $rep)
                            <tr class="border-b border-gray-100 dark:border-white/5 hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                <td class="px-3 py-3 font-medium text-gray-900 dark:text-white">{{ $rep['name'] }}</td>
                                <td class="px-3 py-3 text-right text-gray-900 dark:text-white">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <span>{{ Illuminate\Support\Number::abbreviate($rep['invoice_count'], maxPrecision: 1) }}</span>
                                        <x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4 text-success-500" />
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right text-gray-900 dark:text-white">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <span>{{ Erpsaas\Core\Utilities\Currency\CurrencyConverter::formatCentsToMoneyAbbreviated((int) $rep['total_revenue'], $defaultCurrency) }}</span>
                                        <x-filament::icon icon="heroicon-m-banknotes" class="h-4 w-4 text-primary-500" />
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right font-medium text-gray-900 dark:text-white">
                                    {{ Erpsaas\Core\Utilities\Currency\CurrencyConverter::formatCentsToMoneyAbbreviated((int) $rep['avg_deal_size'], $defaultCurrency) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
