<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('Payable and owing') }}</x-slot>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            {{-- Invoices payable to you --}}
            <div>
                <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">
                    {{ __('Invoices payable to you') }}
                </h3>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach($receivables as $row)
                            <tr>
                                <td class="py-2 text-gray-600 dark:text-gray-400">{{ $row['label'] }}</td>
                                <td class="py-2 text-right font-medium text-gray-900 dark:text-white">{{ $row['amount'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Bills you owe --}}
            <div>
                <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">
                    {{ __('Bills you owe') }}
                </h3>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach($payables as $row)
                            <tr>
                                <td class="py-2 text-gray-600 dark:text-gray-400">{{ $row['label'] }}</td>
                                <td class="py-2 text-right font-medium text-gray-900 dark:text-white">{{ $row['amount'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
