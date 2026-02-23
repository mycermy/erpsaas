<?php

namespace App\Scopes;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Log;

class CurrentCompanyScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // First, try to get company from Filament tenant context
        $companyId = null;

        try {
            if (function_exists('filament')) {
                $tenant = filament()->getTenant();
                if ($tenant && method_exists($tenant, 'getKey')) {
                    $companyId = $tenant->getKey();
                }
            }
        } catch (\Throwable $e) {
            // Filament might not be initialized, continue with fallback
        }

        // Fall back to session
        if (! $companyId) {
            $companyId = session('current_company_id');
        }

        // Skip scope in console (seeders, commands, etc.)
        if (! $companyId && app()->runningInConsole()) {
            return;
        }

        // Fall back to authenticated user's current company
        if (! $companyId && ($user = Filament::auth()->user()) && ($companyId = $user->current_company_id)) {
            session(['current_company_id' => $companyId]);
        }

        if ($companyId) {
            $builder->where("{$model->getTable()}.company_id", $companyId);
        } else {
            Log::error('CurrentCompanyScope: No company_id found for user ' . Filament::auth()->id());

            throw new ModelNotFoundException('CurrentCompanyScope: No company_id set in the session or on the user.');
        }
    }
}
