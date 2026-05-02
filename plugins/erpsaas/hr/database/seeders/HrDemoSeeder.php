<?php

namespace Erpsaas\Hr\Database\Seeders;

use App\Models\User;
use Erpsaas\Accounts\Models\Accounting\Account;
use Erpsaas\Accounts\Models\Accounting\AccountSubtype;
use Erpsaas\Core\Models\Company;
use Erpsaas\Hr\Enums\Hr\SalaryPartBasis;
use Erpsaas\Hr\Enums\Hr\SalaryPartType;
use Erpsaas\Hr\Models\Employee;
use Erpsaas\Hr\Models\PayrollEntry;
use Erpsaas\Hr\Models\SalaryPart;
use Erpsaas\Hr\Models\SalaryStructure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class HrDemoSeeder extends Seeder
{
    public function run(): void
    {
        [$company, $owner] = $this->resolveCompanyAndOwner();

        $owner->forceFill([
            'current_company_id' => $company->id,
        ])->save();

        Auth::login($owner);
        session(['current_company_id' => $company->id]);

        $salariesAccount = $this->resolveAccount(
            company: $company,
            category: 'expense',
            fallbackName: 'Salaries and Wages',
            preferredNames: [
                'Salaries and Wages',
                'Payroll Expense',
            ],
        );

        $employerTaxesAccount = $this->resolveAccount(
            company: $company,
            category: 'expense',
            fallbackName: 'Payroll Employer Taxes and Contributions',
            preferredNames: [
                'Payroll Employer Taxes and Contributions',
                'Employer Taxes',
                'Payroll Taxes',
            ],
        );

        $liabilityAccount = $this->resolveAccount(
            company: $company,
            category: 'liability',
            fallbackName: 'Payroll Statutory Payable',
            preferredNames: [
                'Payroll Statutory Payable',
                'Accrued Payroll',
                'Payroll Liabilities',
                'Accounts Payable',
            ],
        );

        $structures = [
            $this->buildSalaryStructure(
                company: $company,
                name: 'MY Payroll - Operations',
                basicPay: 3800,
                salariesAccount: $salariesAccount,
                employerTaxesAccount: $employerTaxesAccount,
                liabilityAccount: $liabilityAccount,
            ),
            $this->buildSalaryStructure(
                company: $company,
                name: 'MY Payroll - Finance',
                basicPay: 5200,
                salariesAccount: $salariesAccount,
                employerTaxesAccount: $employerTaxesAccount,
                liabilityAccount: $liabilityAccount,
            ),
            $this->buildSalaryStructure(
                company: $company,
                name: 'MY Payroll - Management',
                basicPay: 7600,
                salariesAccount: $salariesAccount,
                employerTaxesAccount: $employerTaxesAccount,
                liabilityAccount: $liabilityAccount,
            ),
        ];

        $employees = [
            [
                'first_name' => 'Nur',
                'last_name' => 'Aisyah',
                'email' => 'nur.aisyah@example.my',
                'job_title' => 'HR Executive',
                'department' => 'Human Resources',
                'home_address' => [
                    'address_line_1' => 'No. 18, Jalan Setia 2/1',
                    'city' => 'Shah Alam',
                    'postal_code' => '40170',
                    'country_code' => 'MY',
                ],
            ],
            [
                'first_name' => 'Muhammad',
                'last_name' => 'Hafiz',
                'email' => 'muhammad.hafiz@example.my',
                'job_title' => 'Finance Analyst',
                'department' => 'Finance',
                'home_address' => [
                    'address_line_1' => 'No. 42, Jalan Ampang Hilir',
                    'city' => 'Kuala Lumpur',
                    'postal_code' => '55000',
                    'country_code' => 'MY',
                ],
            ],
            [
                'first_name' => 'Siti',
                'last_name' => 'Zulaikha',
                'email' => 'siti.zulaikha@example.my',
                'job_title' => 'Operations Manager',
                'department' => 'Operations',
                'home_address' => [
                    'address_line_1' => 'No. 12, Persiaran Tebrau',
                    'city' => 'Johor Bahru',
                    'postal_code' => '80300',
                    'country_code' => 'MY',
                ],
            ],
        ];

        foreach ($employees as $index => $employeeData) {
            $employee = $this->createEmployee($company, $employeeData, $index + 1);
            $this->seedPayrollEntries($employee, $structures[$index], $index + 2);
        }

        Auth::logout();
    }

    private function resolveCompanyAndOwner(): array
    {
        $company = Company::query()->first();

        if (! $company) {
            $owner = User::factory()
                ->withPersonalCompany()
                ->create([
                    'name' => 'HR Demo Owner',
                    'email' => 'hr.demo.owner@example.my',
                    'password' => bcrypt('password'),
                ]);

            $company = $owner->ownedCompanies()->firstOrFail();

            return [$company, $owner];
        }

        $owner = User::query()->find($company->user_id) ?? User::query()->first();

        if (! $owner) {
            throw new RuntimeException('Unable to resolve a user to seed HR demo data.');
        }

        return [$company, $owner];
    }

    private function resolveAccount(Company $company, string $category, string $fallbackName, array $preferredNames = []): Account
    {
        $candidateNames = array_values(array_unique(array_filter([
            $fallbackName,
            ...$preferredNames,
        ])));

        $account = null;

        foreach ($candidateNames as $candidateName) {
            $account = Account::query()
                ->where('company_id', $company->id)
                ->where('category', $category)
                ->where('name', $candidateName)
                ->where('archived', false)
                ->first();

            if ($account) {
                break;
            }
        }

        if (! $account) {
            $account = Account::query()
                ->where('company_id', $company->id)
                ->where('category', $category)
                ->where('archived', false)
                ->orderBy('id')
                ->first();
        }

        if ($account) {
            return $account;
        }

        $subtype = AccountSubtype::query()
            ->where('company_id', $company->id)
            ->where('category', $category)
            ->orderBy('id')
            ->first();

        if (! $subtype) {
            throw new RuntimeException("No account subtype found for category [{$category}] in company [{$company->id}].");
        }

        return Account::create([
            'company_id' => $company->id,
            'subtype_id' => $subtype->id,
            'name' => $fallbackName,
            'description' => 'Auto-created by HR demo seeder.',
        ]);
    }

    private function buildSalaryStructure(
        Company $company,
        string $name,
        float $basicPay,
        Account $salariesAccount,
        Account $employerTaxesAccount,
        Account $liabilityAccount,
    ): SalaryStructure {
        $basicPayPart = $this->upsertSalaryPart(
            company: $company,
            name: "Basic Pay ({$name})",
            type: SalaryPartType::BaseSalary,
            basis: SalaryPartBasis::Fixed,
            amount: $basicPay,
            inNetSalary: true,
            debitAccount: $salariesAccount,
            creditAccount: null,
            description: 'Gross monthly salary.',
        );

        $kwspDeductionPart = $this->upsertSalaryPart(
            company: $company,
            name: "KWSP Employee ({$name})",
            type: SalaryPartType::Deduction,
            basis: SalaryPartBasis::PercentageOfBaseSalary,
            amount: 11,
            inNetSalary: true,
            debitAccount: null,
            creditAccount: $liabilityAccount,
            description: 'Employee EPF contribution (KWSP).',
        );

        $socsoDeductionPart = $this->upsertSalaryPart(
            company: $company,
            name: "SOCSO Employee ({$name})",
            type: SalaryPartType::Deduction,
            basis: SalaryPartBasis::PercentageOfBaseSalary,
            amount: 0.5,
            inNetSalary: true,
            debitAccount: null,
            creditAccount: $liabilityAccount,
            description: 'Employee SOCSO contribution.',
        );

        $eisDeductionPart = $this->upsertSalaryPart(
            company: $company,
            name: "EIS Employee ({$name})",
            type: SalaryPartType::Deduction,
            basis: SalaryPartBasis::PercentageOfBaseSalary,
            amount: 0.2,
            inNetSalary: true,
            debitAccount: null,
            creditAccount: $liabilityAccount,
            description: 'Employee EIS contribution.',
        );

        $kwspEmployerPart = $this->upsertSalaryPart(
            company: $company,
            name: "KWSP Employer ({$name})",
            type: SalaryPartType::EmployerCost,
            basis: SalaryPartBasis::PercentageOfBaseSalary,
            amount: 13,
            inNetSalary: false,
            debitAccount: $employerTaxesAccount,
            creditAccount: $liabilityAccount,
            description: 'Employer EPF contribution (KWSP).',
        );

        $socsoEmployerPart = $this->upsertSalaryPart(
            company: $company,
            name: "SOCSO Employer ({$name})",
            type: SalaryPartType::EmployerCost,
            basis: SalaryPartBasis::PercentageOfBaseSalary,
            amount: 1.75,
            inNetSalary: false,
            debitAccount: $employerTaxesAccount,
            creditAccount: $liabilityAccount,
            description: 'Employer SOCSO contribution.',
        );

        $eisEmployerPart = $this->upsertSalaryPart(
            company: $company,
            name: "EIS Employer ({$name})",
            type: SalaryPartType::EmployerCost,
            basis: SalaryPartBasis::PercentageOfBaseSalary,
            amount: 0.2,
            inNetSalary: false,
            debitAccount: $employerTaxesAccount,
            creditAccount: $liabilityAccount,
            description: 'Employer EIS contribution.',
        );

        $structure = SalaryStructure::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'name' => $name,
            ],
            [
                'description' => 'Malaysia payroll template with statutory deductions and employer costs.',
                'effective_date' => now()->startOfYear()->toDateString(),
                'termination_date' => null,
                'account_id' => $liabilityAccount->id,
            ],
        );

        $structure->spss()->delete();

        foreach (
            [
                $basicPayPart,
                $kwspDeductionPart,
                $socsoDeductionPart,
                $eisDeductionPart,
                $kwspEmployerPart,
                $socsoEmployerPart,
                $eisEmployerPart,
            ] as $part
        ) {
            $structure->spss()->create([
                'salary_part_id' => $part->id,
                'amount' => $part->amount,
                'group' => $part->type->value,
            ]);
        }

        return $structure;
    }

    private function upsertSalaryPart(
        Company $company,
        string $name,
        SalaryPartType $type,
        SalaryPartBasis $basis,
        float $amount,
        bool $inNetSalary,
        ?Account $debitAccount,
        ?Account $creditAccount,
        string $description,
    ): SalaryPart {
        $salaryPart = SalaryPart::query()
            ->where('company_id', $company->id)
            ->where('name', $name)
            ->first();

        if (! $salaryPart) {
            $salaryPart = new SalaryPart([
                'company_id' => $company->id,
                'part_number' => $type->getPrefix() . SalaryPart::getNextSalaryPartNumber($type),
                'name' => $name,
            ]);
        }

        $salaryPart->fill([
            'type' => $type,
            'basis' => $basis,
            'in_net_salary' => $inNetSalary,
            'amount' => $amount,
            'debit_account_id' => $debitAccount?->id,
            'credit_account_id' => $creditAccount?->id,
            'description' => $description,
        ]);

        $salaryPart->save();

        return $salaryPart;
    }

    private function createEmployee(Company $company, array $employeeData, int $sequence): Employee
    {
        $existing = Employee::query()
            ->where('company_id', $company->id)
            ->whereHas('contact', fn($query) => $query->where('email', $employeeData['email']))
            ->first();

        if ($existing) {
            return $existing;
        }

        return Employee::createWithRelations([
            'company_id' => $company->id,
            'employee_number' => 'EMP-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'job_title' => $employeeData['job_title'],
            'department' => $employeeData['department'],
            'separate_work_address' => true,
            'contact' => [
                'first_name' => $employeeData['first_name'],
                'last_name' => $employeeData['last_name'],
                'email' => $employeeData['email'],
                'phones' => [
                    [
                        'type' => 'primary',
                        'data' => [
                            'number' => '+60 3-1234 5678',
                        ],
                    ],
                    [
                        'type' => 'mobile',
                        'data' => [
                            'number' => '+60 12-345 6789',
                        ],
                    ],
                ],
            ],
            'homeAddress' => $employeeData['home_address'],
            'workAddress' => [
                'address_line_1' => 'Menara ERPSAAS, Jalan Teknologi 3/6',
                'city' => 'Petaling Jaya',
                'postal_code' => '47810',
                'country_code' => 'MY',
            ],
        ]);
    }

    private function seedPayrollEntries(Employee $employee, SalaryStructure $salaryStructure, int $months): void
    {
        session(['current_company_id' => $employee->company_id]);

        for ($monthOffset = $months; $monthOffset >= 1; $monthOffset--) {
            $fromDate = now()->subMonths($monthOffset)->startOfMonth()->toDateString();
            $toDate = now()->subMonths($monthOffset)->endOfMonth()->toDateString();

            $alreadySeeded = PayrollEntry::query()
                ->where('company_id', $employee->company_id)
                ->where('employee_id', $employee->id)
                ->where('salary_structure_id', $salaryStructure->id)
                ->where('from_date', $fromDate)
                ->where('to_date', $toDate)
                ->exists();

            if ($alreadySeeded) {
                continue;
            }

            PayrollEntry::createWithTransaction([
                'company_id' => $employee->company_id,
                'entry_number' => PayrollEntry::getNextPayrollEntryNumber(),
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'employee_id' => $employee->id,
                'salary_structure_id' => $salaryStructure->id,
            ]);
        }
    }
}
