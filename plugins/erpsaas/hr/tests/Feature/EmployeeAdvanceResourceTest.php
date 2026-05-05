<?php

use App\Models\User;
use Erpsaas\Core\Models\Company;
use Erpsaas\Hr\Database\Seeders\HrDemoSeeder;
use Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeAdvanceResource\Pages\CreateEmployeeAdvance;
use Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeAdvanceResource\Pages\EditEmployeeAdvance;
use Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeAdvanceResource\Pages\ListEmployeeAdvances;
use Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeResource\Pages\ListEmployees;
use Erpsaas\Hr\Models\Employee;
use Erpsaas\Hr\Models\EmployeeAdvance;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Tests\PluginTestCase;

uses(PluginTestCase::class);

use function Pest\Livewire\livewire;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => HrDemoSeeder::class]);

    $user = User::first();
    $company = Company::first();

    $user->switchCompany($company);
    $this->actingAs($user);
    Filament::setTenant($company);
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

it('can record an advance from the employee list action', function () {
    $employee = Employee::query()->first();
    $countBefore = EmployeeAdvance::query()->where('employee_id', $employee->id)->count();

    livewire(ListEmployees::class)
        ->callTableAction('recordAdvance', $employee, data: [
            'amount' => '750.00',
            'given_at' => now()->toDateTimeString(),
            'reason' => 'medical',
            'notes' => 'Recorded from employee list',
        ])
        ->assertHasNoTableActionErrors();

    expect(EmployeeAdvance::query()->where('employee_id', $employee->id)->count())
        ->toBe($countBefore + 1);

    expect(
        EmployeeAdvance::query()
            ->where('employee_id', $employee->id)
            ->where('reason', 'medical')
            ->exists()
    )->toBeTrue();
});
