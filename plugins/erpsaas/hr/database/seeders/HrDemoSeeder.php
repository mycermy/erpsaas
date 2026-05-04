<?php

namespace Erpsaas\Hr\Database\Seeders;

use App\Models\User;
use Erpsaas\Accounts\Models\Accounting\Account;
use Erpsaas\Accounts\Models\Accounting\AccountSubtype;
use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Core\Enums\Common\VendorType;
use Erpsaas\Core\Models\Common\Vendor;
use Erpsaas\Core\Models\Company;
use Erpsaas\Hr\Enums\Hr\SalaryPartBasis;
use Erpsaas\Hr\Enums\Hr\SalaryPartType;
use Erpsaas\Hr\Models\Employee;
use Erpsaas\Hr\Models\EmployeeAdvance;
use Erpsaas\Hr\Models\EmployeeSalaryRevision;
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

        $liabilityAccount = $this->resolveOrCreatePayrollLiabilityAccount($company);

        $this->ensurePayrollVendorExists($company);

        $structures = $this->buildReusableSalaryStructures(
            company: $company,
            salariesAccount: $salariesAccount,
            employerTaxesAccount: $employerTaxesAccount,
            liabilityAccount: $liabilityAccount,
        );

        $employeeDefinitions = [
            [
                'first_name' => 'Nur',
                'last_name' => 'Aisyah',
                'email' => 'nur.aisyah@example.my',
                'job_title' => 'HR Executive',
                'department' => 'Human Resources',
                'structure_key' => 'standard',
                'salary_revisions' => [
                    ['amount' => 3500, 'effective_from' => now()->subMonths(9)->startOfMonth()->toDateString(), 'reason' => 'initial'],
                    ['amount' => 3800, 'effective_from' => now()->subMonths(3)->startOfMonth()->toDateString(), 'reason' => 'kpi_increment'],
                ],
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
                'structure_key' => 'senior',
                'salary_revisions' => [
                    ['amount' => 5000, 'effective_from' => now()->subMonths(6)->startOfMonth()->toDateString(), 'reason' => 'initial'],
                    ['amount' => 5200, 'effective_from' => now()->subMonths(2)->startOfMonth()->toDateString(), 'reason' => 'promotion'],
                ],
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
                'structure_key' => 'management',
                'salary_revisions' => [
                    ['amount' => 7000, 'effective_from' => now()->subMonths(5)->startOfMonth()->toDateString(), 'reason' => 'initial'],
                    ['amount' => 7600, 'effective_from' => now()->subMonths(1)->startOfMonth()->toDateString(), 'reason' => 'kpi_increment'],
                ],
                'home_address' => [
                    'address_line_1' => 'No. 12, Persiaran Tebrau',
                    'city' => 'Johor Bahru',
                    'postal_code' => '80300',
                    'country_code' => 'MY',
                ],
            ],
        ];

        foreach ($employeeDefinitions as $index => $definition) {
            $employee = $this->createEmployee($company, $definition, $index + 1);
            $this->seedSalaryRevisions($company, $employee, $definition['salary_revisions']);
            $structure = $structures[$definition['structure_key']];
            $this->seedPayrollEntries($employee, $structure, $index + 2);
            $this->seedEmployeeAdvances($company, $employee);
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

    private function resolveOrCreatePayrollLiabilityAccount(Company $company): Account
    {
        $preferredNames = [
            'Payroll Statutory Payable',
            'Accrued Payroll',
            'Payroll Liabilities',
        ];

        foreach ($preferredNames as $name) {
            $account = Account::query()
                ->where('company_id', $company->id)
                ->where('category', 'liability')
                ->where('name', $name)
                ->where('archived', false)
                ->first();

            if ($account) {
                return $account;
            }
        }

        $subtype = AccountSubtype::query()
            ->where('company_id', $company->id)
            ->where('category', 'liability')
            ->orderBy('id')
            ->first();

        if (! $subtype) {
            throw new RuntimeException("No liability account subtype found for company [{$company->id}].");
        }

        return Account::create([
            'company_id' => $company->id,
            'subtype_id' => $subtype->id,
            'name' => 'Payroll Statutory Payable',
            'description' => 'Liabilities for employee withholdings and employer statutory contributions (EPF/KWSP, SOCSO, EIS).',
        ]);
    }

    private function resolveAccount(Company $company, string $category, string $fallbackName, array $preferredNames = []): Account
    {
        $candidateNames = array_values(array_unique(array_filter([
            $fallbackName,
            ...$preferredNames,
        ])));

        foreach ($candidateNames as $candidateName) {
            $account = Account::query()
                ->where('company_id', $company->id)
                ->where('category', $category)
                ->where('name', $candidateName)
                ->where('archived', false)
                ->first();

            if ($account) {
                return $account;
            }
        }

        $account = Account::query()
            ->where('company_id', $company->id)
            ->where('category', $category)
            ->where('archived', false)
            ->orderBy('id')
            ->first();

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

    private function ensurePayrollVendorExists(Company $company): Vendor
    {
        return Vendor::query()
            ->where('company_id', $company->id)
            ->where('name', 'Payroll Department')
            ->firstOr(fn () => Vendor::create([
                'company_id' => $company->id,
                'name' => 'Payroll Department',
                'type' => VendorType::Regular,
                'notes' => 'Internal vendor used for payroll salary bills.',
            ]));
    }

    /**
     * @return array<string, SalaryStructure>
     */
    private function buildReusableSalaryStructures(
        Company $company,
        Account $salariesAccount,
        Account $employerTaxesAccount,
        Account $liabilityAccount,
    ): array {
        // Create ONE shared set of salary parts for all structures
        $sharedParts = $this->createSharedSalaryParts(
            company: $company,
            salariesAccount: $salariesAccount,
            employerTaxesAccount: $employerTaxesAccount,
            liabilityAccount: $liabilityAccount,
        );

        return [
            'standard' => $this->buildSalaryStructure(
                company: $company,
                name: 'MY Payroll - Standard',
                sharedParts: $sharedParts,
                liabilityAccount: $liabilityAccount,
                description: 'Malaysia payroll template for standard/junior employees.',
            ),
            'senior' => $this->buildSalaryStructure(
                company: $company,
                name: 'MY Payroll - Senior',
                sharedParts: $sharedParts,
                liabilityAccount: $liabilityAccount,
                description: 'Malaysia payroll template for senior/specialist employees.',
            ),
            'management' => $this->buildSalaryStructure(
                company: $company,
                name: 'MY Payroll - Management',
                sharedParts: $sharedParts,
                liabilityAccount: $liabilityAccount,
                description: 'Malaysia payroll template for managers and directors.',
            ),
        ];
    }

    /**
     * Create ONE shared set of salary parts that all structures reference.
     * Base salary amount is ALWAYS resolved from EmployeeSalaryRevision, not from the part.
     *
     * @return array<string, SalaryPart>
     */
    private function createSharedSalaryParts(
        Company $company,
        Account $salariesAccount,
        Account $employerTaxesAccount,
        Account $liabilityAccount,
    ): array {
        return [
            'basicPay' => $this->upsertSalaryPart(
                company: $company,
                name: 'Basic Pay',
                type: SalaryPartType::BaseSalary,
                basis: SalaryPartBasis::Fixed,
                amount: 0, // Always resolved from EmployeeSalaryRevision
                inNetSalary: true,
                debitAccount: $salariesAccount,
                creditAccount: null,
                description: 'Gross monthly salary (resolved from employee salary revision).',
            ),
            'kwspEmployee' => $this->upsertSalaryPart(
                company: $company,
                name: 'KWSP Employee',
                type: SalaryPartType::Deduction,
                basis: SalaryPartBasis::PercentageOfBaseSalary,
                amount: 11,
                inNetSalary: true,
                debitAccount: null,
                creditAccount: $liabilityAccount,
                description: 'Employee EPF contribution (KWSP) — 11% of basic pay.',
            ),
            'socsoEmployee' => $this->upsertSalaryPart(
                company: $company,
                name: 'SOCSO Employee',
                type: SalaryPartType::Deduction,
                basis: SalaryPartBasis::PercentageOfBaseSalary,
                amount: 0.5,
                inNetSalary: true,
                debitAccount: null,
                creditAccount: $liabilityAccount,
                description: 'Employee SOCSO contribution — 0.5% of basic pay.',
            ),
            'eisEmployee' => $this->upsertSalaryPart(
                company: $company,
                name: 'EIS Employee',
                type: SalaryPartType::Deduction,
                basis: SalaryPartBasis::PercentageOfBaseSalary,
                amount: 0.2,
                inNetSalary: true,
                debitAccount: null,
                creditAccount: $liabilityAccount,
                description: 'Employee EIS contribution — 0.2% of basic pay.',
            ),
            'kwspEmployer' => $this->upsertSalaryPart(
                company: $company,
                name: 'KWSP Employer',
                type: SalaryPartType::EmployerCost,
                basis: SalaryPartBasis::PercentageOfBaseSalary,
                amount: 13,
                inNetSalary: false,
                debitAccount: $employerTaxesAccount,
                creditAccount: $liabilityAccount,
                description: 'Employer EPF contribution (KWSP) — 13% of basic pay.',
            ),
            'socsoEmployer' => $this->upsertSalaryPart(
                company: $company,
                name: 'SOCSO Employer',
                type: SalaryPartType::EmployerCost,
                basis: SalaryPartBasis::PercentageOfBaseSalary,
                amount: 1.75,
                inNetSalary: false,
                debitAccount: $employerTaxesAccount,
                creditAccount: $liabilityAccount,
                description: 'Employer SOCSO contribution — 1.75% of basic pay.',
            ),
            'eisEmployer' => $this->upsertSalaryPart(
                company: $company,
                name: 'EIS Employer',
                type: SalaryPartType::EmployerCost,
                basis: SalaryPartBasis::PercentageOfBaseSalary,
                amount: 0.2,
                inNetSalary: false,
                debitAccount: $employerTaxesAccount,
                creditAccount: $liabilityAccount,
                description: 'Employer EIS contribution — 0.2% of basic pay.',
            ),
        ];
    }

    /**
     * Build a salary structure using shared salary parts.
     * All structures reference the same parts - only the structure name differs.
     *
     * @param  array<string, SalaryPart>  $sharedParts
     */
    private function buildSalaryStructure(
        Company $company,
        string $name,
        array $sharedParts,
        Account $liabilityAccount,
        string $description = '',
    ): SalaryStructure {
        $structure = SalaryStructure::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'name' => $name,
            ],
            [
                'description' => $description ?: 'Malaysia payroll template with statutory deductions and employer costs.',
                'effective_date' => now()->startOfYear()->toDateString(),
                'termination_date' => null,
                'account_id' => $liabilityAccount->id,
            ],
        );

        $structure->spss()->delete();

        foreach ($sharedParts as $part) {
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
            ->whereHas('contact', fn ($query) => $query->where('email', $employeeData['email']))
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

    /**
     * @param  array<int, array{amount: float, effective_from: string, reason: string}>  $revisions
     */
    private function seedSalaryRevisions(Company $company, Employee $employee, array $revisions): void
    {
        foreach ($revisions as $revision) {
            $alreadyExists = EmployeeSalaryRevision::query()
                ->where('employee_id', $employee->id)
                ->where('effective_from', $revision['effective_from'])
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            EmployeeSalaryRevision::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'base_salary_amount' => $revision['amount'],
                'effective_from' => $revision['effective_from'],
                'reason' => $revision['reason'],
            ]);
        }
    }

    /**
     * Seed payroll entries via createWithBill() for full dashboard visibility.
     * ~70% of past payroll bills are marked as paid for realistic demo data.
     */
    private function seedPayrollEntries(Employee $employee, SalaryStructure $salaryStructure, int $months): void
    {
        session(['current_company_id' => $employee->company_id]);

        $totalMonths = $months;
        $paidThreshold = (int) ceil($totalMonths * 0.7);

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

            $payrollEntry = PayrollEntry::createWithBill([
                'company_id' => $employee->company_id,
                'entry_number' => PayrollEntry::getNextPayrollEntryNumber(),
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'employee_id' => $employee->id,
                'salary_structure_id' => $salaryStructure->id,
            ]);

            $isPaidMonth = ($totalMonths - $monthOffset + 1) <= $paidThreshold;

            if ($isPaidMonth && $payrollEntry->bill_id) {
                $billTotal = Bill::query()->where('id', $payrollEntry->bill_id)->value('total');

                Bill::query()
                    ->where('id', $payrollEntry->bill_id)
                    ->update([
                        'status' => 'paid',
                        'paid_at' => now()->subMonths($monthOffset)->endOfMonth(),
                        'amount_paid' => $billTotal,
                    ]);
            }
        }
    }

    private function seedEmployeeAdvances(Company $company, Employee $employee): void
    {
        // Create sample advances for demonstration purposes
        // Only create advances for employees with base salary >= 5000
        $currentRevision = $employee->salaryRevisions()
            ->where('effective_from', '<=', now()->toDateString())
            ->orderByDesc('effective_from')
            ->first();

        if (! $currentRevision || $currentRevision->base_salary_amount < 5000) {
            return; // Skip creating advances for lower-paid employees
        }

        // Create one pending advance (not yet given) and one recovered advance
        $alreadyHasAdvances = EmployeeAdvance::query()
            ->where('employee_id', $employee->id)
            ->exists();

        if ($alreadyHasAdvances) {
            return; // Skip if advances already exist
        }

        // Pending advance: requested but not yet disbursed
        EmployeeAdvance::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 1500,
            'given_at' => null, // Pending
            'recovered_at' => null,
            'reason' => 'emergency',
            'notes' => 'Emergency personal expense - pending approval and disbursement',
        ]);

        // Recovered advance: given 2 months ago, recovered from last month payroll
        $recoveredFromPayroll = PayrollEntry::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->where('to_date', '<=', now()->subMonths(1)->endOfMonth()->toDateString())
            ->orderByDesc('to_date')
            ->first();

        if ($recoveredFromPayroll) {
            EmployeeAdvance::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'amount' => 1000,
                'given_at' => now()->subMonths(3)->startOfMonth(),
                'recovered_at' => now()->subMonths(1)->endOfMonth(),
                'recovered_from_payroll_id' => $recoveredFromPayroll->id,
                'reason' => 'salary_advance',
                'notes' => 'Salary advance given for personal needs - successfully recovered',
            ]);
        }
    }
}
