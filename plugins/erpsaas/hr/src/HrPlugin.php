<?php

namespace Erpsaas\Hr;

use Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeResource;
use Erpsaas\Hr\Filament\Company\Resources\Hr\PayrollEntryResource;
use Erpsaas\Hr\Filament\Company\Resources\Hr\SalaryPartResource;
use Erpsaas\Hr\Filament\Company\Resources\Hr\SalaryStructureResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class HrPlugin implements Plugin
{
    public function getId(): string
    {
        return 'erpsaas-hr';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function register(Panel $panel): void
    {
        $panel->when($panel->getId() === 'company', function (Panel $panel): void {
            $panel->resources([
                EmployeeResource::class,
                PayrollEntryResource::class,
                SalaryPartResource::class,
                SalaryStructureResource::class,
            ]);
        });
    }

    public function boot(Panel $panel): void {}
}
