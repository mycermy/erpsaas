<?php

use Erpsaas\Hr\Database\Seeders\HrDemoSeeder;
use Erpsaas\Hr\Models\PayrollEntry;
use Illuminate\Support\Facades\Artisan;
use Tests\PluginTestCase;

uses(PluginTestCase::class);

it('builds payslip breakdown and renders payslip pdf view', function () {
    Artisan::call('db:seed', [
        '--class' => HrDemoSeeder::class,
    ]);

    $payrollEntry = PayrollEntry::query()
        ->with(['employee.contact', 'salaryStructure.spss.salaryPart'])
        ->firstOrFail();

    $payslip = $payrollEntry->getPayslipBreakdown();

    expect($payslip)
        ->toHaveKeys(['base_salary', 'gross', 'deductions', 'employer_cost', 'net', 'lines'])
        ->and($payslip['lines'])->not->toBeEmpty()
        ->and($payslip['gross'])->toBeGreaterThan(0)
        ->and($payslip['net'])->toBeGreaterThan(0);

    $html = view('erpsaas-hr::filament.company.resources.hr.payroll-entry-resource.pdf.payslip', [
        'record' => $payrollEntry,
        'payslip' => $payslip,
    ])->render();

    expect($html)
        ->toContain('Payroll Payslip')
        ->toContain('Payslip ' . $payrollEntry->entry_number)
        ->toContain('Net Salary Payable');
});
