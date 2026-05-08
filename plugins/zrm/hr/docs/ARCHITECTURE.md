# Architecture

This document describes the data model, Eloquent relationships, database schema, and namespace conventions of the HR plugin.

---

## Namespace Map

All classes live under `Zrm\Hr\*`. Cross-plugin references use the following resolved namespaces:

| Original (`App\*` in fork) | Resolved namespace in plugin |
|---|---|
| `App\Concerns\Blamable` | `Erpsaas\Core\Concerns\Blamable` |
| `App\Concerns\CompanyOwned` | `Erpsaas\Core\Concerns\CompanyOwned` |
| `App\Models\Company` | `Erpsaas\Core\Models\Company` |
| `App\Models\Common\Address` | `Erpsaas\Core\Models\Common\Address` |
| `App\Models\Common\Contact` | `Erpsaas\Core\Models\Common\Contact` |
| `App\Enums\Common\AddressType` | `Erpsaas\Core\Enums\Common\AddressType` |
| `App\Models\Accounting\Account` | `Erpsaas\Accounts\Models\Accounting\Account` |
| `App\Models\Accounting\Transaction` | `Erpsaas\Accounts\Models\Accounting\Transaction` |
| `App\Enums\Hr\SalaryPartBasis` | `Zrm\Hr\Enums\Hr\SalaryPartBasis` |
| `App\Enums\Hr\SalaryPartType` | `Zrm\Hr\Enums\Hr\SalaryPartType` |
| `App\Models\Hr\*` | `Zrm\Hr\Models\*` |
| `App\Filament\Company\Resources\Hr\*` | `Zrm\Hr\Filament\Company\Resources\Hr\*` |

---

## Entity Relationship Diagram

```
companies
    │
    ├─── employees (company_id)
    │        │
    │        ├─── addresses (addressable, type = home | work)   [polymorphic via core]
    │        └─── contacts  (contactable, is_primary = true)    [polymorphic via core]
    │
    ├─── salary_parts (company_id)
    │        ├─── accounts (debit_account_id)   [FK → accounts]
    │        └─── accounts (credit_account_id)  [FK → accounts]
    │
    ├─── salary_structures (company_id)
    │        ├─── accounts (account_id)          [FK → accounts — payroll liabilities]
    │        └─── salary_part_salary_structures  [pivot]
    │                 ├─── salary_part_id        [FK → salary_parts]
    │                 └─── salary_structure_id   [FK → salary_structures]
    │
    └─── payroll_entries (company_id)
             ├─── employee_id                    [FK → employees]
             ├─── salary_structure_id            [FK → salary_structures]
             └─── transaction_id                 [FK → transactions]
                      └─── journal_entries       [debit / credit rows per salary part]
```

---

## Database Tables

### `employees`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `company_id` | bigint FK | `companies.id`, cascade delete |
| `employee_number` | string | Auto-generated `EMP-0001` |
| `job_title` | string | Required |
| `department` | string | Nullable |
| `separate_work_address` | boolean | Default `false`; when `true` a separate work address is stored |
| `created_by` | bigint FK | `users.id`, null on delete |
| `updated_by` | bigint FK | `users.id`, null on delete |
| `created_at` / `updated_at` | timestamps | |

### `salary_parts`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `part_number` | string | Auto-generated per type, e.g. `BS-0001` |
| `name` | string | |
| `description` | text | Nullable |
| `type` | string | `SalaryPartType` enum value |
| `basis` | string | `SalaryPartBasis` enum value |
| `in_net_salary` | boolean | Whether this part is included in net salary calculation |
| `amount` | decimal(15,3) | Fixed amount or percentage |
| `debit_account_id` | bigint FK | `accounts.id`, nullable |
| `credit_account_id` | bigint FK | `accounts.id`, nullable |
| `company_id` | bigint FK | `companies.id`, cascade delete |
| `created_by` / `updated_by` | bigint FK | |

### `salary_structures`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | |
| `description` | text | Nullable |
| `effective_date` | date | Required; defaults to first day of next month |
| `termination_date` | date | Nullable |
| `account_id` | bigint FK | `accounts.id` — payroll liabilities account |
| `company_id` | bigint FK | `companies.id`, cascade delete |
| `created_by` / `updated_by` | bigint FK | |

### `salary_part_salary_structures` (pivot)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Incrementing (not a composite key) |
| `salary_structure_id` | bigint FK | cascade delete |
| `salary_part_id` | bigint FK | cascade delete |
| `amount` | decimal(15,3) | Nullable; overrides the salary part's default amount for this structure |
| `group` | string | Mirrors the salary part type value; default `base_salary` |

### `payroll_entries`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `entry_number` | string | Auto-generated `0001` sequence |
| `from_date` | date | Period start |
| `to_date` | date | Period end; auto-set to end of `from_date`'s month |
| `employee_id` | bigint FK | `employees.id`, cascade delete |
| `salary_structure_id` | bigint FK | `salary_structures.id`, cascade delete |
| `transaction_id` | bigint FK | `transactions.id`, nullable, cascade delete |
| `company_id` | bigint FK | `companies.id`, cascade delete |
| `created_by` / `updated_by` | bigint FK | |

---

## Models & Key Methods

### `Employee`

- `createWithRelations(array $data): self` — creates the employee record, then creates the `Contact` (morphOne, `is_primary = true`), `homeAddress` (morphOne, `type = Home`), and optionally `workAddress` (morphOne, `type = Work`) in a single call.
- `updateWithRelations(array $data): self` — updates or creates each relation; deletes the work address if `separate_work_address` is toggled off.
- `getNextEmployeeNumber(?Company $company): string` — returns the next sequential `EMP-XXXX` number based on the current company scope.

### `SalaryPart`

- `getNextSalaryPartNumber(SalaryPartType $type): string` — returns the next `0001` sequence scoped per type.
- Casts: `type → SalaryPartType`, `basis → SalaryPartBasis`, `amount → decimal:3`.
- Relations: `debitAccount()` / `creditAccount()` — `hasOne(Account)` by explicit FK columns.

### `SalaryStructure`

- `spss(): HasMany` — pivot relation to `SalaryPartSalaryStructure`.
- `payrollLiabilitiesAccount()` — `hasOne(Account)` by `account_id`.

### `SalaryPartSalaryStructure` (Pivot)

- Extends `Pivot` with `$incrementing = true`.
- `salaryPart()` and `salaryStructure()` BelongsTo relations.

### `PayrollEntry`

- `getNextPayrollEntryNumber(): string` — global `0001` sequence.
- `createWithTransaction(array $data): self` — see [Payroll Processing](PAYROLL.md) for full details.

---

## Enums

### `SalaryPartType`

| Case | Value | Prefix | Net Salary |
|---|---|---|---|
| `BaseSalary` | `base_salary` | `BS-` | ✅ |
| `Deduction` | `deduction` | `DE-` | ✅ (subtracts) |
| `Addition` | `addition` | `AD-` | ✅ |
| `Reimbursement` | `reimbursement` | `RE-` | ✅ |
| `Bonus` | `bonus` | `BO-` | ✅ |
| `Commission` | `commission` | `CO-` | ✅ |
| `Overtime` | `overtime` | `OT-` | ✅ |
| `EmployerCost` | `employer_cost` | `EC-` | ❌ (excluded from net) |
| `Other` | `other` | `VAR-` | ✅ |

### `SalaryPartBasis`

| Case | Value | Meaning |
|---|---|---|
| `Fixed` | `fixed` | Flat amount in the company currency |
| `PercentageOfBaseSalary` | `percentage_of_base_salary` | `amount` is a percentage of the base salary part |

### `AddressType` (extended in `erpsaas/core`)

The `Home` and `Work` cases were added to the existing `AddressType` enum in `Erpsaas\Core\Enums\Common\AddressType`:

```php
case Home = 'home';
case Work = 'work';
```
