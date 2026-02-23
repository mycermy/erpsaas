<x-filament-panels::page>
    <form wire:submit="generateReport">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                <x-filament::loading-indicator class="h-5 w-5" wire:loading wire:target="generateReport" />
                <span wire:loading.remove wire:target="generateReport">Generate Report</span>
                <span wire:loading wire:target="generateReport">Generating...</span>
            </x-filament::button>
        </div>
    </form>

    @if($reportData)
        <div class="mt-8">
            @if($selectedReport === 'valuation')
                <x-filament::section>
                    <x-slot name="heading">
                        Inventory Valuation Report
                    </x-slot>
                    
                    <x-slot name="description">
                        Total Value: {{ number_format($reportData['total_value'] ?? 0, 2) }} | 
                        Total Items: {{ $reportData['total_items'] ?? 0 }}
                    </x-slot>

                    @if(empty($reportData['items']))
                        <div class="text-center py-12">
                            <div class="text-gray-400 mb-4">
                                <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">No Inventory Data</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                There are no inventory items with stock in the selected warehouse(s).
                            </p>
                        </div>
                    @else
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Item</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">SKU</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Warehouse</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Qty</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Unit Cost</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Value</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($reportData['items'] as $item)
                                    <tr>
                                        <td class="px-4 py-3 text-sm">{{ $item['item'] }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $item['sku'] }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $item['warehouse'] }}</td>
                                        <td class="px-4 py-3 text-sm text-right">{{ number_format($item['quantity'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right">{{ number_format($item['unit_cost'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right font-semibold">{{ number_format($item['total_value'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </x-filament::section>

            @elseif($selectedReport === 'movements')
                <x-filament::section>
                    <x-slot name="heading">
                        Stock Movements Report
                    </x-slot>
                    
                    <x-slot name="description">
                        Showing last 100 movements
                    </x-slot>

                    @if(empty($reportData['movements']))
                        <div class="text-center py-12">
                            <div class="text-gray-400 mb-4">
                                <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">No Stock Movements</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                There are no stock movements in the selected date range.
                            </p>
                        </div>
                    @else
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Item</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Warehouse</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Quantity</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($reportData['movements'] as $movement)
                                    <tr>
                                        <td class="px-4 py-3 text-sm">{{ $movement['date'] }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $movement['item'] }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $movement['warehouse'] }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full
                                                {{ str_contains($movement['type'], 'In') || str_contains($movement['type'], 'Purchase') ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                                {{ $movement['type'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right {{ $movement['quantity'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                            {{ $movement['quantity'] > 0 ? '+' : '' }}{{ number_format($movement['quantity'], 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $movement['notes'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </x-filament::section>

            @elseif($selectedReport === 'low_stock')
                <x-filament::section>
                    <x-slot name="heading">
                        Low Stock Items Report
                    </x-slot>

                    @if(empty($reportData['items']))
                        <div class="text-center py-12">
                            <div class="text-gray-400 mb-4">
                                <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">All Stock Levels Good!</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                No items are currently below their reorder levels.
                            </p>
                        </div>
                    @else
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Item</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">SKU</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Current Stock</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Reorder Level</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Suggested Order</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Warehouses</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($reportData['items'] as $item)
                                    <tr>
                                        <td class="px-4 py-3 text-sm">{{ $item['item'] ?? 'N/A' }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $item['sku'] ?? 'N/A' }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-orange-600 dark:text-orange-400 font-semibold">{{ number_format($item['current_stock'] ?? 0, 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right">{{ number_format($item['reorder_level'] ?? 0, 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right">{{ number_format($item['reorder_quantity'] ?? 0, 2) }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $item['warehouses'] ?? 'N/A' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </x-filament::section>
            @endif
        </div>
    @else
        <div class="mt-8">
            <x-filament::section>
                <div class="text-center py-12">
                    <div class="text-gray-400 mb-4">
                        <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">No Report Generated</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Select report parameters above and click "Generate Report" to view data.
                    </p>
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
