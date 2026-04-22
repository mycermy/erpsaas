<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('Overdue invoices and bills') }}</x-slot>

        {{-- Overdue Invoices --}}
        <div>
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-1.5">
                    <x-filament::icon
                        icon="heroicon-o-document-text"
                        class="h-4 w-4 text-primary-600 dark:text-primary-400"
                    />
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                        {{ __('Overdue invoices') }}
                        @if($overdueInvoiceCount > 5)
                            (5+)
                        @elseif($overdueInvoiceCount > 0)
                            ({{ $overdueInvoiceCount }})
                        @endif
                    </span>
                </div>
            </div>

            <div class="space-y-1">
                @forelse($overdueInvoices->take(5) as $invoice)
                    <div class="flex items-center justify-between rounded-lg px-3 py-2 bg-gray-50 dark:bg-white/5">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $invoice['name'] }}</p>
                            <p class="text-xs text-danger-600 dark:text-danger-400">{{ $invoice['overdue_text'] }}</p>
                        </div>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $invoice['amount'] }}</span>
                    </div>
                @empty
                    <div class="flex items-center gap-2 rounded-lg px-3 py-2 bg-success-50 dark:bg-success-500/10">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-4 w-4 text-success-600 dark:text-success-400" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No overdue invoices. Nice!') }}</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="my-4 border-t border-gray-100 dark:border-white/10"></div>

        {{-- Overdue Bills --}}
        <div>
            <div class="flex items-center gap-1.5 mb-2">
                <x-filament::icon
                    icon="heroicon-o-receipt-percent"
                    class="h-4 w-4 text-primary-600 dark:text-primary-400"
                />
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                    {{ __('Overdue bills') }}
                    @if($overdueBillCount > 0)
                        ({{ $overdueBillCount }})
                    @endif
                </span>
            </div>

            <div class="space-y-1">
                @forelse($overdueBills->take(5) as $bill)
                    <div class="flex items-center justify-between rounded-lg px-3 py-2 bg-gray-50 dark:bg-white/5">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $bill['name'] }}</p>
                            <p class="text-xs text-danger-600 dark:text-danger-400">{{ $bill['overdue_text'] }}</p>
                        </div>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $bill['amount'] }}</span>
                    </div>
                @empty
                    <div class="flex items-center gap-2 rounded-lg px-3 py-2 bg-success-50 dark:bg-success-500/10">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-4 w-4 text-success-600 dark:text-success-400" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No overdue bills. Nice!') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
