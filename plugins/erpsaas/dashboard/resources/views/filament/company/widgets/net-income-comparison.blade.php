<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('Net income') }}</x-slot>
        <x-slot name="description">{{ __('Comparison by fiscal year · Accrual (paid & unpaid)') }}</x-slot>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                    <th class="text-left pb-3"></th>
                    <th class="text-right pb-3">{{ $previousYear }}</th>
                    <th class="text-right pb-3">{{ $currentYear }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach($rows as $row)
                    <tr @class([
                        'font-semibold text-gray-900 dark:text-white' => $row['is_total'],
                        'text-gray-600 dark:text-gray-400'            => ! $row['is_total'],
                    ])>
                        <td class="py-2.5">{{ $row['label'] }}</td>
                        <td class="py-2.5 text-right">{{ $row['previous'] }}</td>
                        <td class="py-2.5 text-right">{{ $row['current'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-widgets::widget>
