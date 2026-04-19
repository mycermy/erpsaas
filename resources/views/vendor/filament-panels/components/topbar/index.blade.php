@props([
    'navigation',
])

@php
    $isCompanyPanel = filament()->getCurrentPanel()->getId() === 'company';
    $hasTopNavigation = filament()->hasTopNavigation();
    $hasNavigation = filament()->hasNavigation();
    $hasTenancy = filament()->hasTenancy();
@endphp

<div
    {{
        $attributes->class([
            'fi-topbar sticky top-0 z-20 overflow-x-clip',
            'fi-topbar-with-navigation' => $hasTopNavigation,
        ])
    }}
>
    <nav
        class="flex items-center h-16 px-4 bg-white shadow-sm gap-x-4 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 md:px-6 lg:px-8"
    >
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_START) }}

        @if ($hasNavigation)
            <x-filament::icon-button
                color="gray"
                icon="heroicon-o-bars-3"
                icon-alias="panels::topbar.open-sidebar-button"
                icon-size="lg"
                :label="__('filament-panels::layout.actions.sidebar.expand.label')"
                x-cloak
                x-data="{}"
                x-on:click="$store.sidebar.open()"
                x-show="! $store.sidebar.isOpen"
                @class([
                    'fi-topbar-open-sidebar-btn',
                    'lg:hidden' => (! filament()->isSidebarFullyCollapsibleOnDesktop()) || filament()->isSidebarCollapsibleOnDesktop(),
                ])
            />

            <x-filament::icon-button
                color="gray"
                icon="heroicon-o-x-mark"
                icon-alias="panels::topbar.close-sidebar-button"
                icon-size="lg"
                :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                x-cloak
                x-data="{}"
                x-on:click="$store.sidebar.close()"
                x-show="$store.sidebar.isOpen"
                class="fi-topbar-close-sidebar-btn lg:hidden"
            />

            @if ($isCompanyPanel)
                @php
                    $mobileActiveGroup = collect($navigation)->first(fn ($g) => $g->isActive() && $g->getLabel() && $g->getItems()->isNotEmpty());
                @endphp

                <x-filament::dropdown placement="bottom-start" teleport width="xl">
                    <x-slot name="trigger">
                        <x-filament::icon-button
                            icon="heroicon-o-squares-2x2"
                            label="Modules"
                            color="gray"
                        />
                    </x-slot>

                    <div
                        class="grid gap-2 p-4 overflow-y-auto"
                        style="max-height: 80vh; grid-template-columns: repeat(2, minmax(0, 1fr));"
                        x-data="{}"
                        x-bind:style="window.innerWidth >= 768 ? 'max-height: 80vh; grid-template-columns: repeat(3, minmax(0, 1fr));' : 'max-height: 80vh; grid-template-columns: repeat(2, minmax(0, 1fr));'"
                    >
                        @foreach ($navigation as $group)
                            @php
                                $groupLabel = $group->getLabel();
                                $groupIcon = $group->getIcon();
                                $itemUrl = $group->getItems()->first()?->getUrl();
                            @endphp

                            @if (! $groupLabel || ! $itemUrl || ! $groupIcon)
                                @continue
                            @endif

                            <div
                                @class([
                                    'fi-topbar-item',
                                    'fi-active' => $group->isActive(),
                                ])
                            >
                                <a
                                    href="{{ $itemUrl }}"
                                    class="flex flex-col items-center justify-center gap-2 p-4 text-sm font-medium text-center transition-colors rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 whitespace-nowrap"
                                    @if ($group->isActive())
                                        aria-current="page"
                                    @endif
                                >
                                    <x-filament::icon
                                        :icon="$groupIcon"
                                        class="w-12 h-12 text-gray-600 dark:text-gray-400"
                                    />

                                    <span class="text-gray-700 dark:text-gray-300">{{ $groupLabel }}</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </x-filament::dropdown>

                {{-- Mobile only: dropdown for active group's items (hidden on lg+) --}}
                @if ($mobileActiveGroup)
                    <div class="lg:hidden">
                        <x-filament::dropdown placement="bottom-start" teleport width="xs">
                            <x-slot name="trigger">
                                <button type="button" class="flex items-center gap-1 px-3 py-2 text-sm font-semibold text-gray-700 rounded-lg hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800">
                                    <span>{{ $mobileActiveGroup->getLabel() }}</span>
                                    <x-filament::icon icon="heroicon-m-chevron-down" class="w-4 h-4 opacity-60" />
                                </button>
                            </x-slot>

                            <x-filament::dropdown.list>
                                @foreach ($mobileActiveGroup->getItems() as $item)
                                    <x-filament::dropdown.list.item
                                        :badge="$item->getBadge()"
                                        :badge-color="$item->getBadgeColor()"
                                        :badge-tooltip="$item->getBadgeTooltip()"
                                        :color="$item->isActive() ? 'primary' : 'gray'"
                                        :href="$item->getUrl()"
                                        :icon="$item->isActive() ? ($item->getActiveIcon() ?? $item->getIcon()) : $item->getIcon()"
                                        tag="a"
                                    >
                                        {{ $item->getLabel() }}
                                    </x-filament::dropdown.list.item>
                                @endforeach
                            </x-filament::dropdown.list>
                        </x-filament::dropdown>
                    </div>
                @endif
            @endif
        @endif

        @if ($hasTopNavigation || (! $hasNavigation))
            <div class="hidden me-6 lg:flex">
                @if ($homeUrl = filament()->getHomeUrl())
                    <a {{ \Filament\Support\generate_href_html($homeUrl) }}>
                        <x-filament-panels::logo />
                    </a>
                @else
                    <x-filament-panels::logo />
                @endif
            </div>

            @if ($hasTenancy && filament()->hasTenantMenu())
                <x-filament-panels::tenant-menu class="hidden lg:block" />
            @endif

            @if ($hasNavigation)
                <ul class="items-center hidden me-4 gap-x-4 lg:flex">
                    @foreach ($navigation as $group)
                        @php
                            $groupLabel = $group->getLabel();
                            $groupExtraTopbarAttributeBag = $group->getExtraTopbarAttributeBag();
                            $isGroupActive = $group->isActive();
                            $groupIcon = $group->getIcon();

                            if ($isCompanyPanel && ! $isGroupActive) {
                                continue;
                            }
                        @endphp

                        @if ($groupLabel)
                            @if ($isCompanyPanel)
                                {{-- Company panel: show active group name as a plain bold heading --}}
                                <li class="fi-topbar-item">
                                    <span class="px-3 py-2 text-lg font-bold text-gray-900 cursor-default dark:text-white">
                                        {{ $groupLabel }}
                                    </span>
                                </li>

                                @foreach ($group->getItems() as $item)
                                    <x-filament-panels::topbar.item
                                        :active="$item->isActive()"
                                        :active-icon="$item->getActiveIcon()"
                                        :badge="$item->getBadge()"
                                        :badge-color="$item->getBadgeColor()"
                                        :badge-tooltip="$item->getBadgeTooltip()"
                                        :icon="$item->getIcon()"
                                        :should-open-url-in-new-tab="$item->shouldOpenUrlInNewTab()"
                                        :url="$item->getUrl()"
                                    >
                                        {{ $item->getLabel() }}
                                    </x-filament-panels::topbar.item>
                                @endforeach
                            @else
                                <x-filament::dropdown
                                    placement="bottom-start"
                                    teleport
                                    :attributes="\Filament\Support\prepare_inherited_attributes($groupExtraTopbarAttributeBag)"
                                >
                                    <x-slot name="trigger">
                                        <x-filament-panels::topbar.item
                                            :active="$isGroupActive"
                                            :icon="$groupIcon"
                                        >
                                            {{ $groupLabel }}
                                        </x-filament-panels::topbar.item>
                                    </x-slot>

                                    @php
                                        $lists = [];

                                        foreach ($group->getItems() as $item) {
                                            if ($childItems = $item->getChildItems()) {
                                                $lists[] = [
                                                    $item,
                                                    ...$childItems,
                                                ];
                                                $lists[] = [];

                                                continue;
                                            }

                                            if (empty($lists)) {
                                                $lists[] = [$item];

                                                continue;
                                            }

                                            $lists[count($lists) - 1][] = $item;
                                        }

                                        if (! empty($lists) && empty($lists[count($lists) - 1])) {
                                            array_pop($lists);
                                        }
                                    @endphp

                                    @foreach ($lists as $list)
                                        <x-filament::dropdown.list>
                                            @foreach ($list as $item)
                                                @php
                                                    $itemIsActive = $item->isActive();
                                                @endphp

                                                <x-filament::dropdown.list.item
                                                    :badge="$item->getBadge()"
                                                    :badge-color="$item->getBadgeColor()"
                                                    :badge-tooltip="$item->getBadgeTooltip()"
                                                    :color="$itemIsActive ? 'primary' : 'gray'"
                                                    :href="$item->getUrl()"
                                                    :icon="$itemIsActive ? ($item->getActiveIcon() ?? $item->getIcon()) : $item->getIcon()"
                                                    tag="a"
                                                    :target="$item->shouldOpenUrlInNewTab() ? '_blank' : null"
                                                >
                                                    {{ $item->getLabel() }}
                                                </x-filament::dropdown.list.item>
                                            @endforeach
                                        </x-filament::dropdown.list>
                                    @endforeach
                                </x-filament::dropdown>
                            @endif
                        @else
                            @foreach ($group->getItems() as $item)
                                <x-filament-panels::topbar.item
                                    :active="$item->isActive()"
                                    :active-icon="$item->getActiveIcon()"
                                    :badge="$item->getBadge()"
                                    :badge-color="$item->getBadgeColor()"
                                    :badge-tooltip="$item->getBadgeTooltip()"
                                    :icon="$item->getIcon()"
                                    :should-open-url-in-new-tab="$item->shouldOpenUrlInNewTab()"
                                    :url="$item->getUrl()"
                                >
                                    {{ $item->getLabel() }}
                                </x-filament-panels::topbar.item>
                            @endforeach
                        @endif
                    @endforeach
                </ul>
            @endif
        @endif

        <div
            @if ($hasTenancy)
                x-persist="topbar.end.panel-{{ filament()->getId() }}.tenant-{{ filament()->getTenant()?->getKey() }}"
            @else
                x-persist="topbar.end.panel-{{ filament()->getId() }}"
            @endif
            class="flex items-center ms-auto gap-x-4"
        >
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_BEFORE) }}

            @if (filament()->isGlobalSearchEnabled())
                @livewire(Filament\Livewire\GlobalSearch::class)
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_AFTER) }}

            @if (filament()->auth()->check())
                @if (filament()->hasDatabaseNotifications())
                    @livewire(Filament\Livewire\DatabaseNotifications::class, [
                        'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
                    ])
                @endif

                <x-filament-panels::user-menu />
            @endif
        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_END) }}
    </nav>
</div>
