<?php

namespace App\Console\Commands;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Illuminate\Console\Command;
use ReflectionClass;
use ReflectionObject;

class DebugFilamentNavigation extends Command
{
    protected $signature = 'filament:debug-navigation {panel=company} {--tenant=} {--user=} {--json}';

    protected $description = 'Dump Filament navigation groups and items for a panel (safe, read-only)';

    public function handle(): int
    {
        $panelName = (string) $this->argument('panel');

        // If a tenant is supplied, provide it to the URL generator so route() calls succeed
        $tenant = $this->option('tenant');
        if ($tenant) {
            try {
                // allow passing a model id or a route-key string
                url()->defaults(['tenant' => $tenant]);

                // if a Company model exists for the tenant, set it on Filament (if supported)
                if (class_exists(\App\Models\Company::class) && is_numeric($tenant)) {
                    $company = \App\Models\Company::find($tenant);

                    if ($company && method_exists(Filament::class, 'setTenant')) {
                        Filament::setTenant($company);
                    }

                    // also set session value used by some app code
                    session(['current_company_id' => $tenant]);
                }
            } catch (\Throwable $e) {
                $this->warn('Failed to configure tenant defaults: ' . $e->getMessage());
            }
        }

        $panel = Filament::getPanel($panelName);

        if (! $panel) {
            $this->error("Panel '{$panelName}' not found.");

            return 1;
        }

        $ro = new ReflectionObject($panel);

        // Extract navigation groups (protected property)
        $groups = [];
        if ($ro->hasProperty('navigationGroups')) {
            $prop = $ro->getProperty('navigationGroups');
            $prop->setAccessible(true);
            $rawGroups = $prop->getValue($panel) ?? [];

            foreach ($rawGroups as $g) {
                $gro = new ReflectionObject($g);
                $label = $gro->hasProperty('label') ? $gro->getProperty('label')->getValue($g) : (method_exists($g, 'getLabel') ? $g->getLabel() : null);
                $icon = $gro->hasProperty('icon') ? $gro->getProperty('icon')->getValue($g) : (method_exists($g, 'getIcon') ? $g->getIcon() : null);

                $items = [];
                if ($gro->hasProperty('items')) {
                    $itemsProp = $gro->getProperty('items');
                    $itemsProp->setAccessible(true);
                    $rawItems = $itemsProp->getValue($g) ?? [];

                    foreach ($rawItems as $it) {
                        if ($it instanceof NavigationItem) {
                            $items[] = [
                                'label' => (method_exists($it, 'getLabel') ? $it->getLabel() : null),
                                'url' => (method_exists($it, 'getUrl') ? $it->getUrl() : null),
                                'route' => (method_exists($it, 'getRouteName') ? $it->getRouteName() : (method_exists($it, 'getName') ? $it->getName() : null)),
                            ];
                        } else {
                            $items[] = (string) $it;
                        }
                    }
                }

                $groups[] = [
                    'label' => $label,
                    'icon' => $icon,
                    'items' => $items,
                ];
            }
        }

        // Discover resource/page classes attached to the panel (protected properties)
        $resources = [];
        foreach (['resources', 'pages'] as $propName) {
            if (! $ro->hasProperty($propName)) {
                continue;
            }

            $p = $ro->getProperty($propName);
            $p->setAccessible(true);
            $map = $p->getValue($panel) ?? [];

            foreach ($map as $class) {
                $resources[$propName][] = $class;
            }
        }

        // For each discovered resource/page, call its static getNavigationItems() (if available)
        $itemsFromResources = [];

        $userOption = $this->option('user');
        $user = null;
        if ($userOption) {
            if (is_numeric($userOption) && class_exists(\App\Models\User::class)) {
                $user = \App\Models\User::find($userOption);
            } elseif (class_exists(\App\Models\User::class)) {
                $user = \App\Models\User::where('email', $userOption)->first();
            }

            if (! $user) {
                $this->warn('User not found for --user=' . $userOption);
            }
        }

        foreach (array_merge($resources['pages'] ?? [], $resources['resources'] ?? []) as $class) {
            if (! class_exists($class)) {
                continue;
            }

            // navigation items
            $navItems = null;
            if (method_exists($class, 'getNavigationItems')) {
                try {
                    $navItems = $class::getNavigationItems();
                } catch (\Throwable $e) {
                    $navItems = ['__error' => $e->getMessage()];
                }
            }

            $parsed = [];
            if (is_array($navItems)) {
                foreach ($navItems as $ni) {
                    if ($ni instanceof NavigationItem) {
                        $parsed[] = [
                            'label' => (method_exists($ni, 'getLabel') ? $ni->getLabel() : null),
                            'group' => (method_exists($ni, 'getGroup') ? $ni->getGroup() : null),
                            'url' => (method_exists($ni, 'getUrl') ? $ni->getUrl() : null),
                            'route' => (method_exists($ni, 'getRouteName') ? $ni->getRouteName() : (method_exists($ni, 'getName') ? $ni->getName() : null)),
                        ];
                    } else {
                        $parsed[] = (string) $ni;
                    }
                }
            }

            // permission checks (best-effort)
            $canViewAny = null;
            try {
                if ($user && method_exists($class, 'canViewAny')) {
                    // try user-aware API first
                    $canViewAny = $class::canViewAny($user);
                } elseif ($user && method_exists($class, 'getModel')) {
                    $modelClass = $class::getModel();
                    $canViewAny = \Illuminate\Support\Facades\Gate::forUser($user)->allows('viewAny', $modelClass);
                } elseif (method_exists($class, 'canViewAny')) {
                    $canViewAny = $class::canViewAny();
                }
            } catch (\Throwable $e) {
                $canViewAny = 'error: ' . $e->getMessage();
            }

            $itemsFromResources[$class] = [
                'items' => $parsed,
                'can_view_any_for_user' => is_null($user) ? null : $canViewAny,
            ];
        }

        $out = [
            'panel_id' => $panel->getId(),
            'groups_declared_on_panel' => $groups,
            'discovered_pages_and_resources' => $resources,
            'navigation_items_from_resources' => $itemsFromResources,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 0;
        }

        $this->info("Panel: {$out['panel_id']}");
        $this->line('--- declared navigation groups on panel ---');
        foreach ($out['groups_declared_on_panel'] as $g) {
            $this->line(" - {$g['label']} (icon: {$g['icon']}) — items: " . count($g['items']));
        }

        $this->line('');
        $this->line('--- navigation items provided by discovered pages/resources ---');
        foreach ($out['navigation_items_from_resources'] as $class => $items) {
            $this->line("\n$class:");
            if (empty($items)) {
                $this->line('  (no navigation items)');
                continue;
            }

            foreach ($items as $it) {
                if (isset($it['__error'])) {
                    $this->line("  [error] {$it['__error']}");
                    continue;
                }

                $this->line(sprintf("  - %s — group=%s url=%s route=%s", $it['label'] ?? '(no label)', $it['group'] ?? '(none)', $it['url'] ?? '(no url)', $it['route'] ?? '(no route)'));
            }
        }

        return 0;
    }
}
