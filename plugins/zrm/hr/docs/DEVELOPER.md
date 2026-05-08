# Developer Guide

This guide covers the plugin's internal architecture, the patterns established for plugin development in this monorepo, and how to extend the HR plugin.

---

## Plugin Architecture

This project uses a **plugin monorepo** pattern. Each plugin lives under `plugins/{vendor}/{name}/` and is auto-discovered by Composer via:

```json
// composer.json (root)
"extra": {
    "merge-plugin": {
        "include": ["plugins/*/*/composer.json"]
    }
}
```

Each plugin ships its own `composer.json` with a PSR-4 autoload map and declares its Laravel service provider. The root `composer.json` merges them all at install/update time.

---

## Service Provider Pattern

Every plugin's service provider extends `Spatie\LaravelPackageTools\PackageServiceProvider`:

```php
class HrServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('erpsaas-hr')
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations([]); // Makes migrations publishable via vendor:publish
    }

    public function packageRegistered(): void
    {
        // Register the Filament plugin on every panel
        Panel::configureUsing(fn (Panel $panel) => $panel->plugin(HrPlugin::make()));
    }

    public function packageBooted(): void
    {
        // Auto-load migrations so php artisan migrate picks them up
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
```

### ⚠️ Critical: `loadMigrationsFrom` vs `hasMigrations`

`hasMigrations([])` alone only makes migrations **publishable** — it does **not** register them with `php artisan migrate`. You must call `loadMigrationsFrom()` in `packageBooted()` for migrations to run automatically.

This is the established pattern for all plugins in this project.

---

## Filament Plugin Class

The `HrPlugin` class registers Filament resources only on the target panel:

```php
class HrPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'erpsaas-hr';
    }

    public function register(Panel $panel): void
    {
        if ($panel->getId() !== 'company') {
            return;
        }

        $panel->resources([
            EmployeeResource::class,
            PayrollEntryResource::class,
            SalaryPartResource::class,
            SalaryStructureResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}
}
```

To add a new resource, add it to the `$panel->resources([...])` array in `register()`.

---

## Adding a New Salary Part Type

1. Add a new case to `Zrm\Hr\Enums\Hr\SalaryPartType`:

```php
case Allowance = 'allowance';
```

2. Add a label in `getLabel()`:

```php
self::Allowance => 'Allowance',
```

3. Add a prefix in `getPrefix()`:

```php
self::Allowance => 'AL-',
```

4. Add a plural label in `getPluralLabel()`:

```php
self::Allowance => 'Allowances',
```

5. Decide whether it should subtract from net salary in `PayrollEntry::createWithTransaction()` — currently only `Deduction` subtracts.

6. Run `php artisan test --compact --filter=SalaryPartType` to verify nothing is broken.

---

## Adding a New Plugin

Follow this checklist:

1. Create the directory: `plugins/erpsaas/{name}/`
2. Add `composer.json` with PSR-4 autoload and Laravel provider declaration.
3. Create `src/{Name}ServiceProvider.php` extending `PackageServiceProvider`.
4. In `packageBooted()`, call `$this->loadMigrationsFrom(__DIR__ . '/../database/migrations')`.
5. Create migrations in `plugins/erpsaas/{name}/database/migrations/`.
6. Run `composer update` to merge the new `composer.json`.
7. Run `php artisan migrate` — migrations are auto-discovered from the plugin directory.

---

## Running Migrations

Because migrations are loaded via `loadMigrationsFrom`, the standard Artisan commands work as-is:

```bash
# Run all pending migrations (includes plugin migrations)
php artisan migrate

# Roll back one step (includes plugin migrations in reverse order)
php artisan migrate:rollback

# Check migration status
php artisan migrate:status
```

No special flags or paths are needed. This is intentional — plugin migrations are first-class citizens in the migration lifecycle.

---

## Code Style

This project uses Laravel Pint. After modifying any PHP file, run:

```bash
vendor/bin/pint --dirty
```

The Pint config (`pint.json` in the root) applies to all PHP files, including those inside `plugins/`.

---

## Testing

Place plugin tests in `tests/Feature/` or `tests/Unit/` following the existing naming pattern. All tests use Pest.

```bash
# Run tests for a specific filter
php artisan test --compact --filter=Employee

# Run the full test suite
php artisan test --compact
```

When creating Filament resource tests, authenticate within the test and use `livewire()` assertions:

```php
use Zrm\Hr\Filament\Company\Resources\Hr\EmployeeResource\Pages\ListEmployees;

it('can list employees', function () {
    $user = User::factory()->withPersonalCompany()->create();
    $this->actingAs($user);

    Filament::setCurrentPanel('company');

    $employees = Employee::factory()->count(3)->create(['company_id' => $user->currentCompany->id]);

    livewire(ListEmployees::class)
        ->assertCanSeeTableRecords($employees);
});
```

---

## Bill-Based Payroll Integration (Phase 5+)

### Overview

As of 2026-05-04, payroll entries now integrate with the Bill system for complete invoice tracking and financial reporting. This section covers how the integration works and how to use it in your code.

### Key Changes

**Database:**
- `payroll_entries` now has `bill_id` column (nullable for legacy entries)
- `payroll_entries` now has `gross_salary` snapshot column
- New `employee_advances` table for advance salary tracking
- New "Payroll Statutory Payable" account (ID 165) for liabilities
- New "Employee Advances Receivable" account (ID 166) for assets

**Models:**
- `PayrollEntry::createWithBill()` — creates entry with Bill + Journal Entries
- `PayrollEntry->bill()` — relationship to Bill
- `PayrollEntry->advances()` — relationship to advances
- `EmployeeAdvance` — new model for tracking advances
- `Bill->payrollEntry()` — inverse relationship

### Creating Payroll with Bills

**Recommended Approach:**
```php
use Zrm\Hr\Models\PayrollEntry;

$payrollEntry = PayrollEntry::createWithBill(
    employee: $employee,
    entryNumber: 'PE-2026-MAR-001',
    fromDate: now()->startOfMonth(),
    toDate: now()->endOfMonth(),
    salaryStructure: $structure,
    company: $company,
);

// Automatically:
// - Creates Bill with bill_number like 'PAY-PE2026-0001'
// - Creates journal entries with correct accounts
// - Posts to "Payroll Statutory Payable" (not Accounts Payable)
// - Ensures all entries are balanced
```

**Legacy Approach (Still Works):**
```php
// Old way still functions but bill_id = NULL
$entry = PayrollEntry::create([...]);
```

### Testing Payroll Integration

See `plugins/zrm/hr/tests/Feature/PayrollIntegrationTest.php` for comprehensive examples covering:
- Bill creation and linking
- Journal entry balance validation
- Employee advance handling
- Dashboard integration
- Backward compatibility with legacy entries

All 33 tests passing (186 assertions).

### Querying Payroll & Bills

```php
// Find entries with bills
$withBills = PayrollEntry::whereNotNull('bill_id')->with('bill')->get();

// Find legacy entries (no bills)
$legacy = PayrollEntry::whereNull('bill_id')->get();

// Query payroll transactions
$transactions = Transaction::query()
    ->where('type', 'journal')
    ->where('description', 'like', 'Payroll for%')
    ->with('journalEntries.account')
    ->get();

// Check bill totals
$payrollBills = Bill::where('bill_number', 'like', 'PAY%')->get();
$totalPayroll = $payrollBills->sum('total_amount');
```

### Employee Advances

```php
// Create advance
$advance = EmployeeAdvance::create([
    'employee_id' => $employee->id,
    'amount' => 1000.00,
    'given_at' => now(),
]);

// Mark as recovered during payroll
$advance->update([
    'recovered_from_payroll_id' => $payrollEntry->id,
    'recovered_at' => now(),
]);

// Query advances
$pending = $employee->advances()->whereNull('recovered_at')->get();
$recovered = $employee->advances()->whereNotNull('recovered_at')->get();
```

### Migration Strategy

**Default:** Option A (Conservative) — no backfill of legacy entries
- New entries have bills
- Legacy entries (`bill_id = NULL`) remain untouched
- Zero risk to existing data

**Optional:** Option B (Aggressive) — backfill Bills for legacy entries
```bash
php artisan payroll:backfill-bills
```

See [MIGRATION_STRATEGY.md](./MIGRATION_STRATEGY.md) for detailed guidance.

### Account Mapping

Payroll journal entries use:
- **Debits:** 
  - Account 19 (Salaries and Wages)
  - Account 20 (Payroll Employer Taxes)
- **Credits:**
  - Account 165 (Payroll Statutory Payable) ← **NEW**
  - NOT Account 7 (Accounts Payable)

This separation ensures clean liability tracking and proper financial reporting.

### Performance Notes

When working with payroll data, eager load relationships:
```php
// ✓ GOOD
$entries = PayrollEntry::with('bill', 'employee', 'transaction.journalEntries')->get();

// ✗ BAD (N+1 queries)
$entries = PayrollEntry::all();
foreach ($entries as $entry) {
    echo $entry->bill->bill_number;
}
```

---

## Known Limitations & Future Work

- **No payroll approval workflow** — payroll entries are created immediately without a draft/approve cycle.
- **No period conflict check** — multiple payroll entries can be created for the same employee and period.
- **Amount override on pivot not wired in processing** — `SalaryPartSalaryStructure.amount` is stored but `PayrollEntry::createWithTransaction()` reads the base `SalaryPart.amount` instead. A future improvement should prefer the pivot `amount` when it is set.
- **No leave / attendance integration** — payroll currently assumes full-period pay.
- **No payslip PDF generation** — referenced in the `add_attachment_capability` branch; not yet implemented in the plugin.
- **Phase 7:** Dashboard & UI enhancements (view Bill status, optional UI actions) — partially complete, more work possible
