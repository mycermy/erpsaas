<?php

use Erpsaas\Hr\Database\Seeders\HrDemoSeeder;
use Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeAdvanceResource\Pages\CreateEmployeeAdvance;
use Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeAdvanceResource\Pages\EditEmployeeAdvance;
use Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeAdvanceResource\Pages\ListEmployeeAdvances;
use Erpsaas\Hr\Models\Employee;
use Erpsaas\Hr\Models\EmployeeAdvance;
use Illuminate\Support\Facades\Artisan;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => HrDemoSeeder::class]);
});

it('can list employee advances', function () {
    $advances = EmployeeAdvance::query()->take(5)->get();

    livewire(ListEmployeeAdvances::class)
        ->assertCanSeeTableRecords($advances);
});

it('can create an employee advance', function () {
    $employee = Employee::query()->first();

    livewire(CreateEmployeeAdvance::class)
        ->fillForm([
            'employee_id' => $employee->id,
            'amount' => '500.00',
            'reason' => 'emergency',
            'notes' => 'Test advance',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(
        EmployeeAdvance::query()
            ->where('employee_id', $employee->id)
            ->where('reason', 'emergency')
            ->exists()
    )->toBeTrue();
});

it('shows pending status for unrecovered advances', function () {
    $pendingAdvance = EmployeeAdvance::query()
        ->whereNull('recovered_at')
        ->firstOrFail();

    livewire(ListEmployeeAdvances::class)
        ->assertTableColumnStateSet('status', 'Pending', record: $pendingAdvance);
});

it('shows recovered status for recovered advances', function () {
    $recoveredAdvance = EmployeeAdvance::query()
        ->whereNotNull('recovered_at')
        ->firstOrFail();

    livewire(ListEmployeeAdvances::class)
        ->assertTableColumnStateSet('status', 'Recovered', record: $recoveredAdvance);
});

it('disables fields for a recovered advance', function () {
    $recoveredAdvance = EmployeeAdvance::query()
        ->whereNotNull('recovered_at')
        ->firstOrFail();

    livewire(EditEmployeeAdvance::class, ['record' => $recoveredAdvance->id])
        ->assertFormFieldIsDisabled('employee_id')
        ->assertFormFieldIsDisabled('amount')
        ->assertFormFieldIsDisabled('reason');
});

it('keeps fields enabled for a pending advance', function () {
    $pendingAdvance = EmployeeAdvance::query()
        ->whereNull('recovered_at')
        ->firstOrFail();

    livewire(EditEmployeeAdvance::class, ['record' => $pendingAdvance->id])
        ->assertFormFieldIsEnabled('employee_id')
        ->assertFormFieldIsEnabled('amount');
});
