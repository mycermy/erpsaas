# ERPSAAS HR Plugin

A Human Resources & Payroll plugin for [ERPSAAS](https://github.com/andrewdwallo/erpsaas), originally authored by [NR-Anna-Rosenwasser](https://github.com/NR-Anna-Rosenwasser/erpsaas) across the branches:

- [`add-payroll-module`](https://github.com/NR-Anna-Rosenwasser/erpsaas/tree/add-payroll-module)
- [`add_attachment_capability`](https://github.com/NR-Anna-Rosenwasser/erpsaas/tree/add_attachment_capability)
- [`allow_FY_in_january`](https://github.com/NR-Anna-Rosenwasser/erpsaas/tree/allow_FY_in_january)

It is packaged here as a standalone Filament plugin under `plugins/zrm/hr/`, following the same conventions as the existing `erpsaas/accounts` and `erpsaas/core` plugins.

---

## Overview

The HR plugin adds employee management and a complete payroll processing workflow to the **Company** panel. It integrates tightly with the `erpsaas/accounts` plugin: every time a payroll entry is processed, balanced journal entries are created automatically in the accounting ledger.

```
Employee → Salary Structure → Salary Parts → Payroll Entry → Journal Entries (Transaction)
                                                          ↓
                                                    Bill (for tracking)
```

### Bill-Based Payroll Integration (Phase 5+)

As of May 2026, payroll entries now automatically create Bills for each payroll run. This provides:

- **Invoice Tracking:** Payroll is tracked just like vendor bills
- **Dashboard Integration:** Appears in "Open payables" and financial metrics
- **Liability Segregation:** Uses dedicated "Payroll Statutory Payable" account (not mixed with vendor payables)
- **Audit Trail:** Full bill history and payment recording
- **Backward Compatibility:** Existing payroll entries continue to work; Bills are additive

**Architecture:**
```
PayrollEntry::createWithBill() 
  ↓
Creates Bill (vendor: "Payroll Department", status: Open)
  ↓
Creates Journal Entries (debits to expense accounts, credits to liability account)
  ↓
Automatically included in all financial reports and dashboard widgets
```

See [DEVELOPER.md](./docs/DEVELOPER.md) for integration examples and [MIGRATION_STRATEGY.md](./docs/MIGRATION_STRATEGY.md) for legacy data handling.

---

## Features

| Area | Feature |
|---|---|
| **Employee Management** | Create and manage employees with auto-generated employee numbers (`EMP-0001`), contact details, phone numbers, home and optionally separate work addresses |
| **Salary Parts** | Define reusable building blocks for pay — base salary, deductions, additions, bonuses, commissions, overtime, employer costs, etc. — each with a debit and credit account mapping |
| **Salary Structures** | Compose salary structures from salary parts with per-structure amount overrides; each structure is date-bounded and linked to a payroll liabilities account |
| **Payroll Entries** | Process payroll for an employee against a salary structure for a date range; generates balanced journal entries automatically |
| **Accounting Integration** | Every payroll entry creates a `Transaction` with individual `JournalEntry` rows — one per salary part — plus a net salary payable credit entry |
| **Bill Integration** | Payroll entries automatically create Bills for full dashboard integration, tracking, and financial reporting (as of Phase 5+) |
| **Employee Advances** | Track salary advances with recovery mechanism - advances are deducted from future payroll |
| **Dashboard Widgets** | Payroll expenses appear in dashboard financial metrics, including "Open payables", "Expenses breakdown", and other accounting reports |

---

## Installation

The plugin is auto-discovered by Composer via the `wikimedia/composer-merge-plugin` wildcard already present in the root `composer.json`:

```json
"merge-plugin": {
    "include": ["plugins/*/*/composer.json"]
}
```

After adding the plugin folder, run:

```bash
composer update
php artisan migrate
```

Migrations live inside the plugin and are loaded automatically via `loadMigrationsFrom()` in `HrServiceProvider::packageBooted()`. No manual migration copying is required.

---

## Directory Structure

```
plugins/zrm/hr/
├── composer.json
├── database/
│   └── migrations/
│       ├── 2025_10_06_224949_create_employees_table.php
│       ├── 2025_10_06_235746_make_country_code_nullable_on_addresses_table.php
│       ├── 2025_10_07_000328_add_separate_work_address_bool_to_employees_table.php
│       ├── 2025_10_07_000734_add_fields_to_employees_table.php
│       ├── 2025_10_07_173648_create_salary_parts_table.php
│       ├── 2025_10_07_193000_create_salary_structures_table.php
│       ├── 2025_10_07_213721_create_salary_part_salary_structures_table.php
│       └── 2025_10_07_233922_create_payroll_entries_table.php
└── src/
    ├── HrPlugin.php
    ├── HrServiceProvider.php
    ├── Enums/Hr/
    │   ├── SalaryPartBasis.php
    │   └── SalaryPartType.php
    ├── Filament/Company/Resources/Hr/
    │   ├── EmployeeResource.php  (+ Pages/)
    │   ├── SalaryPartResource.php  (+ Pages/)
    │   ├── SalaryStructureResource.php  (+ Pages/)
    │   └── PayrollEntryResource.php  (+ Pages/)
    └── Models/
        ├── Employee.php
        ├── SalaryPart.php
        ├── SalaryStructure.php
        ├── SalaryPartSalaryStructure.php
        └── PayrollEntry.php
```

---

## Dependencies

| Plugin | Why |
|---|---|
| `erpsaas/core` | `Blamable`, `CompanyOwned` traits; `Address`, `Contact` models; `AddressType` enum (requires `Home` and `Work` cases) |
| `erpsaas/accounts` | `Account` model (for debit/credit mapping on salary parts and payroll liabilities); `Transaction` + `JournalEntry` models |

> The `AddressType` enum in `erpsaas/core` was extended with `Home` and `Work` cases to support employee address types.

---

## Further Reading

- [Architecture](ARCHITECTURE.md) — data model, relationships, namespace map
- [Payroll Processing](PAYROLL.md) — how journal entries are calculated and balanced
- [Developer Guide](DEVELOPER.md) — adding new salary part types, extending the plugin, running migrations from a new plugin
