<?php

use Zrm\Hr\Database\Seeders\HrDemoSeeder;
use Zrm\Hr\Models\Employee;
use Zrm\Hr\Models\PayrollEntry;
use Zrm\Hr\Models\SalaryPart;
use Illuminate\Support\Facades\Artisan;
use Tests\PluginTestCase;

uses(PluginTestCase::class);

it('seeds malaysian employees with statutory payroll entries', function () {
    Artisan::call('db:seed', [
        '--class' => HrDemoSeeder::class,
    ]);

    expect(Employee::query()->count())->toBe(3)
        ->and(PayrollEntry::query()->count())->toBe(9)
        ->and(SalaryPart::query()->where('name', 'like', 'Basic Pay%')->exists())->toBeTrue()
        ->and(SalaryPart::query()->where('name', 'like', 'KWSP Employee%')->exists())->toBeTrue()
        ->and(SalaryPart::query()->where('name', 'like', 'SOCSO Employee%')->exists())->toBeTrue()
        ->and(SalaryPart::query()->where('name', 'like', 'EIS Employee%')->exists())->toBeTrue();

    $payslipDistribution = PayrollEntry::query()
        ->selectRaw('employee_id, COUNT(*) AS total')
        ->groupBy('employee_id')
        ->pluck('total')
        ->sort()
        ->values()
        ->all();

    expect($payslipDistribution)->toBe([2, 3, 4]);
});
