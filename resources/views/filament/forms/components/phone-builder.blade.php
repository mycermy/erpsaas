@php
    use Filament\Actions\Action;
    use Filament\Support\Enums\Alignment;

    $fieldWrapperView = $getFieldWrapperView();
    $items = $getItems();
    $blockPickerBlocks = $getBlockPickerBlocks();
    $blockPickerColumns = $getBlockPickerColumns();
    $blockPickerWidth = $getBlockPickerWidth();

    $addAction = $getAction($getAddActionName());
    $addActionAlignment = $getAddActionAlignment();
    $addBetweenAction = $getAction($getAddBetweenActionName());
    $deleteAction = $getAction($getDeleteActionName());
    $moveDownAction = $getAction($getMoveDownActionName());
    $moveUpAction = $getAction($getMoveUpActionName());
    $reorderAction = $getAction($getReorderActionName());
    $extraItemActions = $getExtraItemActions();

    $isAddable = $isAddable();
    $isDeletable = $isDeletable();
    $isReorderableWithButtons = $isReorderableWithButtons();
    $isReorderableWithDragAndDrop = $isReorderableWithDragAndDrop();

    $persistCollapsed = $shouldPersistCollapsed();
    $key = $getKey();
    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$fieldWrapperView" :field="$field">
    <div
        {{
            $attributes
                ->merge($getExtraAttributes(), escape: false)
                ->class(['fi-fo-builder grid gap-y-4'])
        }}
    >
        @if (count($items))
            <ul
                x-sortable
                data-sortable-animation-duration="{{ $getReorderAnimationDuration() }}"
                x-on:end.stop="
                    $wire.mountAction(
                        'reorder',
                        { items: $event.target.sortable.toArray() },
                        { schemaComponent: '{{ $key }}' },
                    )
                "
                class="grid grid-cols-1 gap-4 sm:grid-cols-2"
            >
                @foreach ($items as $itemKey => $item)
                    @php
                        $deleteAction = $deleteAction(['item' => $itemKey]);
                        $deleteActionIsVisible = $isDeletable && $deleteAction->isVisible();
                        $moveDownAction = $moveDownAction(['item' => $itemKey])->disabled($loop->last);
                        $moveDownActionIsVisible = $isReorderableWithButtons && $moveDownAction->isVisible();
                        $moveUpAction = $moveUpAction(['item' => $itemKey])->disabled($loop->first);
                        $moveUpActionIsVisible = $isReorderableWithButtons && $moveUpAction->isVisible();
                        $reorderActionIsVisible = $isReorderableWithDragAndDrop && $reorderAction->isVisible();
                    @endphp

                    <li
                        wire:ignore.self
                        wire:key="{{ $item->getLivewireKey() }}.item"
                        x-data="{ isCollapsed: false }"
                        x-sortable-item="{{ $itemKey }}"
                        class="flex items-end gap-x-3"
                    >
                        <!-- Input Field -->
                        <div class="w-full">
                            {{ $item }}
                        </div>

                        <!-- Delete Action -->
                        @if ($deleteActionIsVisible)
                            <div class="mb-1 shrink-0">
                                {{ $deleteAction }}
                            </div>
                        @endif
                    </li>

                    @if (! $loop->last)
                        @if ($isAddable && $addBetweenAction(['afterItem' => $itemKey])->isVisible())
                            <li class="relative -top-2 mt-0! h-0">
                                <div
                                    class="flex w-full justify-center opacity-0 transition duration-75 hover:opacity-100"
                                >
                                    <div
                                        class="fi-fo-builder-block-picker-ctn rounded-lg bg-white dark:bg-gray-900"
                                    >
                                        <x-filament-forms::builder.block-picker
                                            :action="$addBetweenAction"
                                            :after-item="$itemKey"
                                            :columns="$blockPickerColumns"
                                            :blocks="$blockPickerBlocks"
                                            :key="$key"
                                            :width="$blockPickerWidth"
                                        >
                                            <x-slot name="trigger">
                                                {{ $addBetweenAction(['afterItem' => $itemKey]) }}
                                            </x-slot>
                                        </x-filament-forms::builder.block-picker>
                                    </div>
                                </div>
                            </li>
                        @endif
                    @endif
                @endforeach
            </ul>
        @endif

        @if ($isAddable && $addAction->isVisible())
            <x-filament-forms::builder.block-picker
                :action="$addAction"
                :action-alignment="$addActionAlignment"
                :blocks="$blockPickerBlocks"
                :columns="$blockPickerColumns"
                :key="$key"
                :width="$blockPickerWidth"
            >
                <x-slot name="trigger">
                    {{ $addAction }}
                </x-slot>
            </x-filament-forms::builder.block-picker>
        @endif
    </div>
</x-dynamic-component>
