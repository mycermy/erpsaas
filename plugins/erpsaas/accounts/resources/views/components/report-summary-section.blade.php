@props([
    'reportLoaded' => false,
    'summaryData' => [],
    'targetLabel' => null,
])

@php
    use Erpsaas\Core\Utilities\Currency\CurrencyAccessor;
@endphp

<div>
    <x-filament::section>
        @if($reportLoaded)
            <div class="flex flex-wrap items-end gap-4 mx-auto text-center place-content-center max-w-fit">
                @foreach($summaryData as $summary)
                    <div class="text-sm">
                        <div class="mb-2 font-medium text-gray-600 dark:text-gray-200">{{ $summary['label'] }}</div>

                        @php
                            $isTargetLabel = $summary['label'] === $targetLabel;
                            $isPositive = money($summary['value'], CurrencyAccessor::getDefaultCurrency())->isPositive();
                        @endphp

                        <strong
                            @class([
                                'text-lg',
                                'text-success-700 dark:text-success-400' => $isTargetLabel && $isPositive,
                                'text-danger-700 dark:text-danger-400' => $isTargetLabel && ! $isPositive,
                            ])
                        >
                            {{ $summary['value'] }}
                        </strong>
                    </div>

                    @if(! $loop->last)
                        <div class="flex items-center justify-center">
                            <strong class="text-lg">
                                {{ $loop->remaining === 1 ? '=' : '-' }}
                            </strong>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </x-filament::section>
</div>
