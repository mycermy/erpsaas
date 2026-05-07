<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('Sales Forecast Data') }}</x-slot>

        @php
            $forecastData = $this->getForecastData();
        @endphp

        @if(empty($forecastData))
            <div class="flex items-center justify-center gap-2 rounded-lg px-3 py-8 bg-gray-50 dark:bg-white/5">
                <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5 text-gray-400" />
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No forecast data available') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">{{ __('Month') }}</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">{{ __('Type') }}</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">{{ __('Revenue') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($forecastData as $row)
                            <tr class="border-b border-gray-100 dark:border-white/5 hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="px-3 py-3 text-gray-900 dark:text-white">{{ $row['month'] }}</td>
                                <td class="px-3 py-3">
                                    @if($row['is_forecast'])
                                        <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-md bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400">
                                            <x-filament::icon icon="heroicon-m-sparkles" class="h-3.5 w-3.5" />
                                            {{ __('Forecast') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-md bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400">
                                            <x-filament::icon icon="heroicon-m-check-circle" class="h-3.5 w-3.5" />
                                            {{ __('Actual') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right font-medium text-gray-900 dark:text-white">
                                    ${{ number_format($row['revenue'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
