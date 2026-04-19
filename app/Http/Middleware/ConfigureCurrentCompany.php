<?php

namespace App\Http\Middleware;

use Erpsaas\Core\Events\CompanyConfigured;
use Erpsaas\Core\Models\Company;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigureCurrentCompany
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Company $company */
        $company = Filament::getTenant();

        if ($company) {
            CompanyConfigured::dispatch($company);
        }

        return $next($request);
    }
}
