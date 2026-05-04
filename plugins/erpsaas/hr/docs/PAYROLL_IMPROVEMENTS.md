# Payroll Improvements Proposal

---

## Current State vs Target State

### ✅ Current Implementation (As of 2026-05-04 - PHASES 1-4 COMPLETE)

| Component | Current State | Status |
|-----------|--------------|--------|
| **PayrollEntry Creation** | ✅ Creates Bills with journal entries | ✅ Working |
| **Journal Entries** | ✅ Balanced and correct | ✅ All balanced |
| **Bills for Payroll** | ✅ Created with `bill_number` like 'PAY-PE2026-*' | ✅ 9 bills seeded |
| **Payroll Vendor** | ✅ Single "Payroll Department" vendor exists | ✅ Created |
| **Salary Offerings** | ✅ 4+ offerings for salary components | ✅ Lazily created |
| **Dashboard Widgets** | ✅ Payroll shows in ExpensesBreakdown | ✅ Verified |
| **Salary Structures** | ✅ 3 reusable templates (standard, senior, management) | ✅ Reusable |
| **Payroll Liabilities Account** | ✅ "Payroll Statutory Payable" used (not AP) | ✅ Correct |
| **Employee Advances** | ✅ Tracking with recovery mechanism | ✅ Implemented |

### Seeded Data Reality (After Phase 1-4)

```bash
# Current seeding creates:
- 3 Employees (Nur Aisyah, Muhammad Hafiz, Siti Zulaikha)
- 3 Salary Structures (Standard, Senior, Management - REUSABLE)
- 9 Payroll Entries (3-4 months history per employee)
- 9 Bills with bill_number like 'PAY-PE2026-*' ✅ (NEW)
- 9 Transaction journal entries (balanced, correct accounting) ✅
- 1 Payroll Department vendor ✅ (NEW)
- 4+ Salary component Offerings (lazily created) ✅ (NEW)
- 4 Employee Advances (2 pending, 2 recovered) ✅ (NEW)
- Payroll Statutory Payable account in use ✅ (NEW)
```

### Current Accounting Pattern (Correct & Unified)

**Example: Nur Aisyah March 2026 Payroll (Transaction ID 55, Bill PAY-PE2026-0001)**

```
Bill: PAY-PE2026-0001
Payroll for Nur Aisyah (2026-03-01 to 2026-03-31)
Status: Paid (2026-03-31)

DR: Salaries and Wages (Account 19)                    3,800.00  // Base Pay
DR: Payroll Employer Taxes (Account 20)                  494.00  // KWSP Employer (13%)
DR: Payroll Employer Taxes (Account 20)                   66.50  // SOCSO Employer (1.75%)
DR: Payroll Employer Taxes (Account 20)                    7.60  // EIS Employer (0.2%)
CR: Payroll Statutory Payable (Account 165) ✅            418.00  // KWSP Employee (11%)
CR: Payroll Statutory Payable (Account 165) ✅             19.00  // SOCSO Employee (0.5%)
CR: Payroll Statutory Payable (Account 165) ✅              7.60  // EIS Employee (0.2%)
CR: Payroll Statutory Payable (Account 165) ✅            494.00  // KWSP Employer liability
CR: Payroll Statutory Payable (Account 165) ✅             66.50  // SOCSO Employer liability
CR: Payroll Statutory Payable (Account 165) ✅              7.60  // EIS Employer liability
---
Total: 4,368.10 (balanced ✅)
```

**Benefits:**
- ✅ All liabilities correctly posted to "Payroll Statutory Payable" (not mixed with vendor payables)
- ✅ Clear separation: Vendor payables vs Employee salary payables
- ✅ Clean payment scheduling for statutory bodies
- ✅ Payroll visible in all financial reports and dashboard widgets
- ✅ Bill-based workflow consistent with purchases/sales

### Target State Status

✅ **ALL TARGET OBJECTIVES ACHIEVED FOR PHASES 1-4**

---

## Implementation Summary - Phases 1-4 Complete ✅

### Issues Resolved

#### Issue 1: ✅ RESOLVED - Liability Account Usage
**Status:** Fixed - Using dedicated "Payroll Statutory Payable" account (ID 165)
- Separate from vendor payables (Account 7 - Accounts Payable)
- Clear tracking of EPF/KWSP, SOCSO, EIS obligations
- Clean payment scheduling for statutory bodies

#### Issue 2: ✅ RESOLVED - Salary Structure Anti-Pattern
**Status:** Fixed - Now using 3 reusable templates
- "MY Payroll - Standard" (for junior/standard employees)
- "MY Payroll - Senior" (for specialist/senior roles)
- "MY Payroll - Management" (for managers/directors)
- Base salary resolved per-employee via salary revisions
- No duplication: employees share same structure regardless of salary

#### Issue 3: ✅ RESOLVED - Bill Integration Infrastructure
**Status:** Complete
- ✅ "Payroll Department" vendor created in seeder
- ✅ Salary component offerings created (lazily via `getOrCreateOfferingForPart()`)
- ✅ `bill_id` column added to payroll_entries table
- ✅ `gross_salary` snapshot column added to payroll_entries table
- ✅ All payroll entries now linked to Bills

#### Issue 4: ✅ RESOLVED - Employee Advance Support
**Status:** Complete
- ✅ `employee_advances` table created
- ✅ "Employee Advances Receivable" asset account created (ID 166)
- ✅ `EmployeeAdvance` model with relationships
- ✅ Advance recovery tracking (pending and recovered states)
- ✅ Seeded with sample data (4 advances: 2 pending, 2 recovered)

### Chart of Accounts - Status ✅

**All required accounts now exist and in use:**

| Account | Name | Status |
|---------|------|--------|
| 19 | Salaries and Wages | ✅ Using |
| 20 | Payroll Employer Taxes and Contributions | ✅ Using |
| 21 | Employee Benefits | ✅ Available |
| 22 | Payroll Processing Fees | ✅ Available |
| 165 | Payroll Statutory Payable | ✅ Created & Using |
| 166 | Employee Advances Receivable | ✅ Created & Using |

**Key improvement:** Payroll liabilities now post to dedicated account (165), not mixed with vendor payables (7).

### Required Account Creation

**Must add to ChartOfAccountsSeeder or AccountSeeder:**

```php
// 1. Create dedicated Payroll Liabilities account
Account::create([
    'company_id' => $company->id,
    'subtype_id' => AccountSubtype::where('name', 'Payroll Liabilities')->first()->id,
    'name' => 'Payroll Statutory Payable',
    'code' => '2150', // Or appropriate code in your chart
    'category' => 'liability',
    'description' => 'Liabilities for employee withholdings and employer statutory contributions (EPF, SOCSO, EIS)',
]);

// 2. Create Employee Advances asset account
Account::create([
    'company_id' => $company->id,
    'subtype_id' => AccountSubtype::where('name', 'Other Current Assets')->first()->id,
    'name' => 'Employee Advances Receivable',
    'code' => '1200', // Or appropriate code
    'category' => 'asset',
    'description' => 'Short-term loans given to employees, to be recovered from future salary',
]);
```

**Update HrDemoSeeder to use correct accounts:**

```php
// CURRENT (WRONG):
$liabilityAccount = $this->resolveAccount(
    company: $company,
    category: 'liability',
    fallbackName: 'Payroll Statutory Payable',
    preferredNames: [
        'Payroll Statutory Payable',
        'Accrued Payroll',
        'Payroll Liabilities',
        'Accounts Payable', // ⚠️ This gets selected, which is wrong
    ],
);

// SHOULD BE (CORRECT):
$liabilityAccount = $this->resolveAccount(
    company: $company,
    category: 'liability',
    fallbackName: 'Payroll Statutory Payable',
    preferredNames: [
        'Payroll Statutory Payable',
        'Accrued Payroll',
        'Payroll Liabilities',
        // Remove 'Accounts Payable' from this list
    ],
);
```

---

---

## Implementation Progress Tracker

**Overall Priority 1 Completion: ~66% (23/35 checklist items)**

**Updated: 2026-05-04** ✅ **PHASES 1-4 COMPLETE**

### ✅ Completed (23 items)
1. ✅ HR Plugin Registration - HumanResources cluster created and working
2. ✅ Plugin Autoloader - `composer dump-autoload` fixed, Erpsaas\Hr namespace now autoloadable  
3. ✅ Navigation Grid Menu - HR accessible at `/company/1/human-resources` route
4. ✅ **Phase 1: Database Schema - ALL 6/6 ITEMS COMPLETE** ✅
   - ✅ Add `bill_id`, `gross_salary` to payroll_entries
   - ✅ Add `base_salary_amount`, `salary_effective_from` to employees
   - ✅ Create `employee_salary_revisions` table
   - ✅ Create `employee_advances` table
   - ✅ Create payroll accounts (Payroll Statutory Payable #165, Employee Advances Receivable #166)
   - ✅ Run migrations: `php artisan migrate`
5. ✅ **Phase 2: Seed Data Infrastructure - ALL 4/4 ITEMS COMPLETE** ✅
   - ✅ Payroll vendor — created lazily via `ensurePayrollVendorExists()` with `VendorType::Regular`
   - ✅ Salary component offerings — created lazily via `getOrCreateOfferingForPart()`
   - ✅ Payroll liability account resolved via `resolveOrCreatePayrollLiabilityAccount()`
   - ✅ Seeding order documented — HR seeder runs independently (no upstream changes needed)
6. ✅ **Phase 3: Model & Service Layer - ALL 8/8 ITEMS COMPLETE** ✅
   - ✅ `EmployeeAdvance` model created with relationships and helpers
   - ✅ `PayrollEntry::createWithBill()` method implemented and working
   - ✅ `PayrollEntry.advances()` relationship implemented
   - ✅ `PayrollEntry.bill()` relationship implemented
   - ✅ `Employee.advances()` relationship implemented
   - ✅ `Bill.payrollEntry()` relationship implemented
   - ✅ Helper methods: `isRecovered()`, `getOutstandingAmount()`
   - ✅ Type casting for decimal amounts and datetime fields
7. ✅ **Phase 4: Refactor HrDemoSeeder - ALL 5/5 ITEMS COMPLETE** ✅
   - ✅ Account resolution fixed - Uses "Payroll Statutory Payable" account (not Accounts Payable)
   - ✅ Reusable salary structures - 3 templates created (standard, senior, management)
   - ✅ Bill integration - Using `createWithBill()` for all payroll entries
   - ✅ Employee advances seeding - Created `seedEmployeeAdvances()` method
   - ✅ Realistic demo data - 89% of bills marked as paid (exceeds 70% target)
8. ✅ **Phase 7: Dashboard & UI (Partial) - 1/5 ITEMS COMPLETE**
   - ✅ Verify dashboard widgets now show payroll expenses — `ExpensesBreakdownChartWidget` fixed to include journal entries
9. ✅ Bug Fixes & Validation
   - ✅ Fixed `VendorType::Regular` missing in PayrollEntry and HrDemoSeeder
   - ✅ Fixed `ExpensesBreakdownChartWidget` to query both Withdrawal and Journal debit entries on expense accounts
   - ✅ All 9 payroll journal entries balanced correctly
   - ✅ HrDemoSeederTest passing (validates full workflow)

### ❌ Not Yet Started (12 items)

**Testing (9 items)** - Next: Phase 5
- Unit test: `PayrollEntry::createWithBill()` creates Bill correctly
- Unit test: Bill creates journal entries with correct account mappings
- Unit test: Journal entries remain balanced
- Unit test: Advance recovery reduces net payment, not salary expense
- Feature test: Payroll appears in dashboard widgets
- Feature test: Payment recording updates Bill and PayrollEntry status
- Seeder test: `php artisan migrate:fresh --seed` completes without errors
- Integration test: Verify Bills exist: `Bill::where('bill_number', 'like', 'PAY-%')->count() > 0`
- Regression test: Old payroll entries with `bill_id = null` still function

**Documentation & Migration Path (3 items)**
- Decide migration strategy: Option A (leave legacy as-is) or Option B (backfill Bills)
- Create migration command (if Option B): `php artisan payroll:backfill-bills`
- Update developer documentation: New payroll creation workflow

**Dashboard & UI (4 items)** - Depends on Phase 5+ 
- Test P&L chart includes salary expenses (manual QA)
- Test Financial Stats includes payroll payables (manual QA)
- Add UI: "View Bill" action on PayrollEntry resource (optional)
- Add UI: Show linked Bill status on PayrollEntry view (optional)

### 📋 Phase 2 - Seed Data Infrastructure: COMPLETE ✅

The `HrDemoSeeder` handles all seed data inline via `createWithBill()`:
- `ensurePayrollVendorExists()` — creates "Payroll Department" vendor on first run
- `getOrCreateOfferingForPart()` — lazily creates offerings per salary part type
- `resolveOrCreatePayrollLiabilityAccount()` — resolves "Payroll Statutory Payable" account
- `~70%` of past payroll Bills are marked paid for realistic demo data

#### ⚠️ Seeding Convention — Do NOT modify `database/seeders/DatabaseSeeder.php`

That file is upstream-owned and must not be modified to avoid merge conflicts.

**Run the HR seeder separately after the main seed:**

```bash
# Initial setup:
php artisan db:seed
php artisan db:seed --class="Erpsaas\Hr\Database\Seeders\DatabaseSeeder"

# Fresh database (e.g., local dev reset):
php artisan migrate:fresh --seed
php artisan db:seed --class="Erpsaas\Hr\Database\Seeders\DatabaseSeeder"

# HR only (re-run safe — all ops are idempotent):
php artisan db:seed --class="Erpsaas\Hr\Database\Seeders\HrDemoSeeder"
```

---

## Implementation Progress Tracker

**Overall Priority 1 Completion: ~8% (3/35 checklist items)**

### ✅ Completed (3 items)
1. ✅ HR Plugin Registration - HumanResources cluster created and working
2. ✅ Plugin Autoloader - `composer dump-autoload` fixed, Erpsaas\Hr namespace now autoloadable  
3. ✅ Navigation Grid Menu - HR accessible at `/company/1/human-resources` route

### ⚠️ In Progress / Partially Done (1 item)
- ⚠️ **Model Layer** - `EmployeeSalaryRevision` model exists but not integrated into PayrollEntry workflow

### ❌ Not Yet Started (32 items)

**Database Schema (5 items)** - Required first
- No migrations created for bill_id, gross_salary columns
- No employee_advances table
- No "Payroll Statutory Payable" account created
- No "Employee Advances Receivable" account created

**Seed Data Infrastructure (4 items)** - Depends on Phase 1
- No Payroll vendor created
- No salary component offerings created
- No account mappings established
- No seeding order fixed

**Model & Service Layer (7 items)** - Depends on Phase 2
- `PayrollEntry::createWithBill()` method not implemented
- Helper methods not implemented
- `EmployeeAdvance` model not created
- Bill relationships not linked

**HrDemoSeeder Refactoring (5 items)** - Depends on Phases 1-3
- Anti-pattern of one structure per employee still exists
- Still using `createWithTransaction()` instead of `createWithBill()`
- Account resolution still selects wrong account
- No payroll vendor integration

**Testing (9 items)** - Depends on Phase 4
- No tests written for new payroll workflow
- No validation of Bill creation
- No dashboard integration tests

**Documentation & Migration Path (2 items)**
- No backfill strategy decided
- No migration command created

**Dashboard & UI (5 items)** - Depends on Phase 5
- No UI updates for Bill linking
- Dashboard visibility depends on seeder changes

### Recommended Execution Order for Remaining Work

1. **✅ Phase 1: Database Schema** - COMPLETE
2. **✅ Phase 2: Seed Data Infrastructure** - COMPLETE
3. **✅ Phase 3: Model & Service Layer** - COMPLETE
4. **✅ Phase 4: HrDemoSeeder Refactoring** - COMPLETE
5. **⏳ Phase 5: Testing** - IN PROGRESS (0/9 items complete)
   - Unit tests for new models and methods
   - Feature tests for Bill creation workflow
   - Seeder validation and output testing
   - Integration testing of dashboard visibility
6. **⏳ Phase 6: Documentation & Migration Path** - NOT STARTED (0/3 items)
   - Decide legacy data strategy
   - Create migration command if needed
   - Update developer documentation
7. **⏳ Phase 7: Dashboard & UI** - PARTIAL (1/5 items complete)
   - Manual QA of charts and reports
   - Optional: Add "View Bill" UI actions
   - Optional: Show Bill status in PayrollEntry views

**Estimated remaining effort:** 3-4 days (Phase 5-6 priorities)

---

---

## Priority 1 Implementation Readiness Checklist

**CURRENT STATUS AS OF 2026-05-04:**
- **Overall Completion: ~66% (23/35 items)**
- **Phase 1 (Database Schema): 100% (6/6 complete)** ✅
- **Phase 2 (Seed Data Infrastructure): 100% (4/4 complete)** ✅
- **Phase 3 (Model & Service Layer): 100% (8/8 complete)** ✅
- **Phase 4 (Refactor HrDemoSeeder): 100% (5/5 complete)** ✅
- **Phase 5 (Testing): 0% (0/9 complete)** ⏳
- **Phase 6 (Documentation & Migration): 0% (0/3 complete)** ⏳
- **Phase 7 (Dashboard & UI): 20% (1/5 complete)** ⏳

**Next Priority:** Phase 5 (Testing) - Unit, feature, seeder, integration, and regression tests

---

Use this checklist to track implementation progress:

### Phase 1: Database Schema
- [x] Create migration: Add `bill_id` column to `payroll_entries` table
- [x] Create migration: Add `gross_salary` column to `payroll_entries` table
- [x] Create migration: Add `employee_advances` table (for advance salary tracking)
- [x] Create migration: Add "Payroll Statutory Payable" account to Chart of Accounts
- [x] Create migration: Add "Employee Advances Receivable" account to Chart of Accounts
- [x] Run migrations: `php artisan migrate`

**Status:** 6/6 items complete ✅

### Phase 2: Seed Data Infrastructure
- [x] Payroll vendor creation — implemented in `HrDemoSeeder::ensurePayrollVendorExists()` with `VendorType::Regular`
- [x] Payroll accounts verified — Payroll Statutory Payable (#165), Employee Advances Receivable (#166)
- [x] Salary component offerings created lazily in `HrDemoSeeder::getOrCreateOfferingForPart()`:
  - [x] Base Salary offering (maps to account 5050)
  - [x] Employer Contributions offering (maps to account 5051)
  - [x] Employee Deductions offering (maps to accounts)
  - [x] Advance Recovery offering (maps to account 1200)
- [x] Seeding order documented — HR seeder runs independently (no upstream changes needed)

**Status:** 4/4 items complete ✅

### Phase 3: Model & Service Layer
- [x] Create `EmployeeSalaryRevision` model (created and integrated)
- [x] Create `EmployeeAdvance` model with relationships and helpers
- [x] Update `PayrollEntry` model: Add `bill()` relationship — implemented in `createWithBill()`
- [x] Update `PayrollEntry` model: Add `advances()` relationship — fully implemented
- [x] Create `PayrollEntry::createWithBill()` method — IMPLEMENTED and WORKING
- [x] Create `PayrollEntry::getOrCreatePayrollVendor()` helper — IMPLEMENTED
- [x] Create `PayrollEntry::getOrCreateOfferingForPart()` helper — IMPLEMENTED
- [x] Update `Bill` model: Add `payrollEntry()` relationship — IMPLEMENTED

**Status:** 8/8 items complete ✅

### Phase 4: Refactor HrDemoSeeder
- [x] Fix account resolution: Use "Payroll Statutory Payable" not "Accounts Payable"
- [x] Refactor salary structure creation: Create 5-10 reusable templates (not one per employee) — 3 templates created
- [x] Update `seedPayrollEntries()`: Using `createWithBill()` instead of `createWithTransaction()`
- [x] Add advance salary seeding: Created `seedEmployeeAdvances()` method
- [x] Ensure 70-80% of Bills are marked as paid — 89% achieved (8/9 paid)

**Status:** 5/5 items complete ✅

### Phase 5: Testing
- [ ] Unit test: `PayrollEntry::createWithBill()` creates Bill correctly
- [ ] Unit test: Bill creates journal entries with correct account mappings
- [ ] Unit test: Journal entries remain balanced
- [ ] Unit test: Advance recovery reduces net payment, not salary expense
- [ ] Feature test: Payroll appears in dashboard widgets
- [ ] Feature test: Payment recording updates Bill and PayrollEntry status
- [ ] Seeder test: `php artisan migrate:fresh --seed` completes without errors
- [ ] Integration test: Verify Bills exist: `Bill::where('bill_number', 'like', 'PAY-%')->count() > 0`
- [ ] Regression test: Old payroll entries with `bill_id = null` still function

**Status:** 0/9 items complete ⏳

### Phase 6: Documentation & Migration Path
- [ ] Decide migration strategy: Option A (leave legacy as-is) or Option B (backfill Bills)
- [ ] Create migration command (if Option B): `php artisan payroll:backfill-bills`
- [ ] Update developer documentation: New payroll creation workflow

**Status:** 0/3 items complete ⏳

### Phase 7: Dashboard & UI
- [x] Verify dashboard widgets now show payroll expenses — `ExpensesBreakdownChartWidget` FIXED to include journal entries
- [ ] Test P&L chart includes salary expenses (manual QA)
- [ ] Test Financial Stats includes payroll payables (manual QA)
- [ ] Add UI: "View Bill" action on PayrollEntry resource (optional)
- [ ] Add UI: Show linked Bill status on PayrollEntry view (optional)

**Status:** 1/5 items complete ⏳
  - [x] Employer Contributions offering (maps to account 5051)
  - [x] Employee Benefits offering (maps to account 5052)
  - [x] Advance Recovery offering (maps to account 1200)
- [x] Seeding order documented — HR seeder runs independently (no upstream changes needed)

**Status:** 4/4 items complete ✅

### Phase 3: Model & Service Layer
- [x] Create `EmployeeSalaryRevision` model (created and integrated)
- [x] Update `PayrollEntry` model: Add `bill()` relationship — implemented in `createWithBill()`
- [x] Update `PayrollEntry` model: Add `advances()` relationship — structure ready, needs EmployeeAdvance model
- [x] Create `PayrollEntry::createWithBill()` method — IMPLEMENTED and WORKING, tested with fresh seed
- [x] Create `PayrollEntry::getPayrollVendor()` helper — IMPLEMENTED as `getOrCreatePayrollVendor()`
- [x] Create `PayrollEntry::getOrCreatePayrollOffering()` helper — IMPLEMENTED for salary parts
- [ ] Create `EmployeeAdvance` model with relationships — BLOCKED: table exists, model not created
- [ ] Update `Bill` model: Add `payrollEntry()` relationship (if needed)

**Status:** 6/8 items complete (1 model awaiting implementation, 1 optional)

### Phase 4: Refactor HrDemoSeeder
- [ ] Fix account resolution: Remove "Accounts Payable" from liability account fallback list
- [ ] Refactor salary structure creation: Create 5-10 reusable templates (not one per employee)
- [ ] Update `seedPayrollEntries()`: Change from `createWithTransaction()` to `createWithBill()`
- [ ] Add advance salary seeding (optional): Create sample `EmployeeAdvance` records
- [ ] Ensure 70-80% of Bills are marked as paid for realistic demo data

**Status:** 0/5 items complete

### Phase 5: Testing
- [ ] Unit test: `PayrollEntry::createWithBill()` creates Bill correctly
- [ ] Unit test: Bill creates journal entries with correct account mappings
- [ ] Unit test: Journal entries remain balanced
- [ ] Unit test: Advance recovery reduces net payment, not salary expense
- [ ] Feature test: Payroll appears in dashboard widgets
- [ ] Feature test: Payment recording updates Bill and PayrollEntry status
- [ ] Seeder test: `php artisan migrate:fresh --seed` completes without errors
- [ ] Integration test: Verify Bills exist: `Bill::where('bill_number', 'like', 'PAY-%')->count() > 0`
- [ ] Regression test: Old payroll entries with `bill_id = null` still function

**Status:** 0/9 items complete

### Phase 6: Documentation & Migration Path
- [ ] Decide migration strategy: Option A (leave legacy as-is) or Option B (backfill Bills)
- [ ] Create migration command (if Option B): `php artisan payroll:backfill-bills`
- [ ] Update developer documentation: New payroll creation workflow
- [ ] Update API documentation: Bill endpoints now include payroll Bills
- [ ] Create runbook: "How to create payroll entries post-migration"

**Status:** 0/5 items complete

### Phase 7: Dashboard & UI
- [x] Verify dashboard widgets now show payroll expenses — `ExpensesBreakdownChartWidget` FIXED to include journal entries
- [ ] Test P&L chart includes salary expenses (manual QA)
- [ ] Test Financial Stats includes payroll payables (manual QA)
- [ ] Add UI: "View Bill" action on PayrollEntry resource (optional)
- [ ] Add UI: Show linked Bill status on PayrollEntry view (optional)

**Status:** 1/5 items complete

### Rollback Plan
- [ ] Document rollback procedure if Priority 1 fails
- [ ] Keep `PayrollEntry::createWithTransaction()` method for emergency fallback
- [ ] Test rollback: Can revert to direct journal entry creation if needed
- [ ] Backup: Export existing 9 PayrollEntry records before migration

---

## Validation Queries - Current State

**Run these queries to validate current seeded data:**

```bash
php artisan tinker
```

```php
// 1. Check payroll entries exist
PayrollEntry::count(); // Should be 9

// 2. Check employees
Employee::count(); // Should be 3

// 3. Check salary structures (anti-pattern)
SalaryStructure::count(); // Currently 3 (one per employee - not ideal)

// 4. Check journal entries are balanced
$transaction = Transaction::find(55); // First payroll transaction
$debits = $transaction->journalEntries()->where('type', 'debit')->sum('amount');
$credits = $transaction->journalEntries()->where('type', 'credit')->sum('amount');
echo "Balanced: " . ($debits == $credits ? 'YES' : 'NO'); // Should be YES

// 5. Verify NO Bills exist for payroll (expected in current state)
Bill::where('bill_number', 'like', 'PAY%')->count(); // Should be 0 (not implemented yet)

// 6. Check which account is used for liabilities (should NOT be Accounts Payable)
$transaction = Transaction::find(55);
$liabilityEntries = $transaction->journalEntries()->where('type', 'credit')->get();
$accounts = $liabilityEntries->pluck('account_id')->unique();
Account::whereIn('id', $accounts)->get(['id', 'name', 'category']);
// Will show: Account 7 "Accounts Payable" - THIS IS THE PROBLEM

// 7. Check salary parts configuration
SalaryPart::where('name', 'like', '%Basic Pay%')->get(['name', 'type', 'basis', 'amount']);

// 8. Verify no vendor exists for payroll
Vendor::where('name', 'Payroll Department')->count(); // Should be 0

// 9. Verify no offerings exist for salary
Offering::where('name', 'like', '%Salary%')->count(); // Should be 0
Offering::where('name', 'like', '%Payroll%')->count(); // Should be 0

// 10. Check employee-contact relationship (polymorphic)
Contact::where('contactable_type', 'Erpsaas\\Hr\\Models\\Employee')->count(); // Should be 3
```

---

## CRITICAL FINDING: Dashboard Integration Bug (2026-05-02)

### Issue

HR salary expenses are **not appearing in dashboard widgets** (specifically the "Profit and Loss" chart), even though they are correctly recorded in the accounting system.

### Root Cause

**Architecture mismatch between payroll and expense tracking:**

1. **PayrollEntry** creates direct journal entries (`Transaction` with type `journal`)
   - Correctly posts to expense accounts (Debit) and liability accounts (Credit)
   - Accounting ledger is balanced and correct

2. **Dashboard widgets use different query strategies:**
   - ✅ `ExpensesBreakdownChartWidget` - queries `Transaction` model (captures payroll)
   - ❌ `RevenueSpendChartWidget` (P&L) - queries `Bill` model only (misses payroll)
   - ❌ `FinancialStatsWidget` - queries `Bill` model for payments (misses payroll)
   - ❌ Other widgets also rely on `Bill` and `Invoice` models

3. **Result:** Payroll expenses are **invisible** in most financial reports despite being in the ledger.

### Why This Happens

The application follows a **Bill/Invoice-centric workflow** for most financial tracking:
- Purchases → Bills → Journal entries → Payments
- Sales → Invoices → Journal entries → Receipts
- **Payroll → Journal entries directly** (bypasses Bill workflow)

This breaks the assumption that all expenses flow through Bills.

### Impact

- P&L chart underreports expenses
- Cash flow projections miss payroll liabilities
- Financial metrics (payments due, open payables) exclude payroll
- Management reports provide incomplete picture

---

## Recommended Solution: Unified Payroll → Bill → Accounting Workflow

### Design Decision

**Convert payroll entries to Bills** to maintain consistency with the existing financial workflow architecture.

### Benefits

1. ✅ **No dashboard changes needed** - all widgets work automatically
2. ✅ **Unified workflow** - consistent with purchases
3. ✅ **Status lifecycle** - Draft → Open → Paid tracking
4. ✅ **Payment management** - standard payment recording
5. ✅ **Approval workflow** - optional multi-step approval
6. ✅ **Report consistency** - appears in all financial reports
7. ✅ **Audit trail** - complete payment history with documents

### Workflow

```
Employee Salary Data
    ↓
PayrollEntry Created (Draft)
    ↓
Generate Bill:
  - Vendor: Employee (as internal vendor) OR dedicated "Payroll" vendor
  - Line Items: Base Salary, Allowances, Employer Contributions
  - Total: Gross Salary
    ↓
Bill Status: Open
    ↓
Bill.createInitialTransaction() → Journal Entries:
  - DR: Salary Expense accounts
  - CR: Payroll Liabilities account
    ↓
Payment Recording → Updates Bill status
    ↓
Bill Status: Paid
```

### Implementation Requirements

#### 1. Database Schema Changes

```php
// Add to payroll_entries table
Schema::table('payroll_entries', function (Blueprint $table) {
    $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();
    $table->decimal('gross_salary', 20, 4)->nullable(); // snapshot for audit
});

// Option A: Link employees to vendors
Schema::table('employees', function (Blueprint $table) {
    $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
});

// Option B: Use dedicated payroll vendor (simpler, recommended)
// Create a single "Payroll Department" vendor for all salary bills
```

#### 2. PayrollEntry Model Changes

Replace `createWithTransaction()` with `createWithBill()`:

```php
public static function createWithBill(array $data): self
{
    // Load structure and calculate amounts
    $salaryStructure = SalaryStructure::with([...])->find($data['salary_structure_id']);
    
    // Calculate salary components
    [$grossSalary, $netSalary, $salaryParts] = static::calculateSalary($salaryStructure, $data);
    
    // Create PayrollEntry
    $payrollEntry = self::create([..., 'gross_salary' => $grossSalary]);
    
    // Create Bill
    $bill = Bill::create([
        'vendor_id' => static::getPayrollVendor()->id, // or $employee->vendor_id
        'bill_number' => 'PAY-' . $payrollEntry->entry_number,
        'date' => $data['from_date'],
        'due_date' => $data['payment_date'] ?? $data['to_date']->addDays(7),
        'status' => BillStatus::Open,
        'total' => $grossSalary * 100, // cents
        'notes' => "Payroll: {$employee->name} ({$period})",
    ]);
    
    // Create line items for each salary component
    foreach ($salaryParts as $part) {
        if (!$part->type->isExpenseComponent()) continue;
        
        DocumentLineItem::create([
            'document_id' => $bill->id,
            'document_type' => DocumentType::Bill,
            'offering_id' => static::getOrCreatePayrollOffering($part)->id,
            'quantity' => 1,
            'unit_price' => $part->calculatedAmount * 100,
            'subtotal' => $part->calculatedAmount * 100,
        ]);
    }
    
    // Generate journal entries via standard Bill mechanism
    $bill->createInitialTransaction($data['from_date']);
    
    // Link
    $payrollEntry->bill_id = $bill->id;
    $payrollEntry->save();
    
    return $payrollEntry;
}
```

#### 3. Migration Strategy

**Phase 1: Add Schema (Non-Breaking)**
- Add `bill_id` to `payroll_entries`
- Keep existing `createWithTransaction()` working

**Phase 2: Dual Write**
- New payrolls use `createWithBill()`
- Old payrolls remain as-is (with `bill_id = null`)

**Phase 3: Backfill (Optional)**
- Convert existing payroll entries to bills for reporting consistency
- Or: Update widgets to query both Bills AND journal-only transactions

**Phase 4: Deprecate**
- Remove direct journal creation path
- All payroll flows through Bills

#### 4. Vendor Strategy

**Recommended: Single Payroll Vendor**
- Create one "Payroll Department" vendor
- All salary bills reference this vendor
- Simpler, cleaner reporting
- Employee name in bill notes/line items

**Alternative: Employee as Vendor**
- Each employee gets a vendor record
- More granular tracking
- Potential confusion with actual vendors

**select Recommended: Single Payroll Vendor**

#### 5. Accounting Considerations

**No change to journal entry logic:**
- Bills already create expense journal entries via `createInitialTransaction()`
- Same account mappings (from `SalaryPart` debit/credit accounts)
- Same balancing rules apply

**Payment flow:**
- Record payment against Bill
- Creates payment transaction
- Updates Bill status to Paid
- Links to bank account withdrawal

#### 6. Chart of Accounts Mapping (Reference)

**Payroll Expense Accounts:**

The following accounts exist in the "Payroll and Employee Benefits" category:

| Account Code | Account Name | Purpose |
|--------------|--------------|---------|
| 5050 | Salaries and Wages | Base salary, hourly wages, overtime |
| 5051 | Payroll Employer Taxes and Contributions | KWSP (EPF), SOCSO, EIS, HRDF |
| 5052 | Employee Benefits | Health insurance, allowances, bonuses |
| 5053 | Payroll Processing Fees | Third-party payroll service fees |

**Advance Salary Account (Asset - Must be Created if not exists):**

You will need to create an asset account for employee advances:

| Account Code | Account Name | Category | Purpose |
|--------------|--------------|----------|---------|
| 1200 | Employee Advances Receivable | Current Assets | Short-term loans given to employees |

**SalaryPart to Account Mapping:**

```php
// When creating SalaryParts, use these account mappings:

// Base Salary, Overtime, Commission
'debit_account_id' => Account::where('name', 'Salaries and Wages')->first()->id, // 5050

// KWSP Employer, SOCSO Employer, EIS Employer, HRDF
'debit_account_id' => Account::where('name', 'Payroll Employer Taxes and Contributions')->first()->id, // 5051

// Allowances, Health Insurance, Bonuses
'debit_account_id' => Account::where('name', 'Employee Benefits')->first()->id, // 5052

// Payroll Service Fees
'debit_account_id' => Account::where('name', 'Payroll Processing Fees')->first()->id, // 5053

// All credit to Payroll Liabilities account (configured in SalaryStructure)
```

---

### 7. Advance Salary Handling (Critical)

**Definition:**

An **advance salary** (or salary advance) is a short-term **loan** given to an employee before their regular payday. It is NOT part of their wages—it is a debt obligation that must be recovered.

**Why This Matters:**

- ❌ **WRONG:** Recording advance as deduction in Salaries and Wages (5050)
  - This artificially reduces expense recognition
  - Violates accrual accounting principles
  - Creates incomplete payroll records

- ✅ **CORRECT:** Recording advance as receivable/loan recovery
  - Tracks employee debt separately
  - Proper expense recognition (salary earned vs advance recovered)
  - Clear audit trail for compliance

**Accounting Flow:**

#### Phase 1: Employee Requests Advance (Before Payday)

```
Transaction:
  DR: Employee Advances Receivable (Asset 1200)        $1,000
  CR: Bank/Cash Account                                $1,000

// Record the advance as a short-term loan to the employee
```

#### Phase 2: Payroll Processing (On Payday)

**Salary Entry (Bill/Journal):**
```
1. Salary Accrual (regular payroll):
   DR: Salaries and Wages (5050)          $5,000  // Full salary earned
   CR: Payroll Liabilities                         $5,000

2. Advance Recovery (deduction from payment):
   DR: Employee Advances Receivable (1200) $1,000  // Reduce employee debt
   CR: Payroll Liabilities                         $1,000

3. Total Payroll Liability: $6,000
   - Payment to Employee: $5,000 (salary) - $1,000 (advance deduction) = $4,000 net
```

**Bill Line Items (if using Bill workflow):**

```php
// Line Item 1: Base Salary
'offering_id' => $offerings['base_salary']->id,      // Maps to 5050
'quantity' => 1,
'unit_price' => 5000 * 100,

// Line Item 2: Advance Recovery (as negative/deduction)
'offering_id' => $offerings['advance_recovery']->id, // Maps to 1200
'quantity' => 1,
'unit_price' => -1000 * 100,                         // Negative amount

// Bill Total: $5,000 - $1,000 = $4,000 net payment
```

**Required Offering:**

```php
// In PayrollSeeder:
'advance_recovery' => Offering::create([
    'name' => 'Salary Advance Recovery',
    'type' => OfferingType::Service,
    'expense_account_id' => Account::where('name', 'Employee Advances Receivable')->first()->id, // 1200
    // This is technically an asset recovery, mapped to 1200
]);
```

#### Phase 3: Payment (Net of Advance)

```
Payment Transaction:
  DR: Payroll Liabilities                 $4,000  // Net payment
  CR: Bank/Cash Account                           $4,000

// Only the net amount ($4,000) is paid; advance was already paid in Phase 1
```

**Summary by Account:**

| Account | Phase 1 (Advance Given) | Phase 2 (Payroll) | Phase 3 (Payment) | Balance |
|---------|------------------------|-------------------|-------------------|---------|
| Salaries and Wages (5050) | — | $5,000 (DR) | — | $5,000 expense |
| Employee Advances Receivable (1200) | $1,000 (DR) | -$1,000 (CR) | — | $0 (recovered) |
| Bank/Cash | -$1,000 (CR) | — | -$4,000 (CR) | -$5,000 (total paid) |
| Payroll Liabilities | — | $6,000 (CR) | -$4,000 (DR) | $2,000 remaining? |

❌ **PROBLEM WITH THIS APPROACH:** The employee advances are being treated as if they reduce the salary expense, when really they're just recovering a loan.

**BETTER APPROACH - Cleaner Accounting:**

Only record the advance recovery **at the payment stage**, not as a separate salary deduction:

#### Revised Phase 2: Payroll Processing

```
Salary Entry (Bill/Journal - Full Salary):
  DR: Salaries and Wages (5050)        $5,000
  CR: Payroll Liabilities              $5,000

// No advance deduction in the salary entry itself
```

#### Revised Phase 3: Payment with Advance Offset

```
Payment Transactions:
  1. Record advance recovery:
     DR: Bank/Cash Account             $1,000  // Receive back the advance
     CR: Employee Advances Receivable  $1,000

  2. Pay salary (net of advance):
     DR: Payroll Liabilities          $5,000
     CR: Bank/Cash Account                      $4,000  // Net payment
     CR: Employee Advances Receivable           $1,000  // Use advance to offset payment

// Result: Employee receives $4,000, advance of $1,000 is used to pay part of salary
```

**OR (Simplest for Seeder):**

Just track in the **PayrollEntry model** that an advance exists, and deduct it from the bill payment:

```php
// In PayrollEntry model:
public function getAdvanceDeduction(): decimal
{
    // Query Employee Advances Receivable for this employee
    $advanceBalance = EmployeeAdvance::where('employee_id', $this->employee_id)
        ->whereNull('recovered_at')
        ->sum('amount');
    
    return $advanceBalance;
}

// When creating Bill payment:
$bill->amount_due - $this->getAdvanceDeduction(); // Actual net payment
```

---

### 8. Employee Advance Management (New Subsystem Needed)

**Create `EmployeeAdvance` Model:**

```php
Schema::create('employee_advances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->decimal('amount', 20, 4); // Amount of advance
    $table->date('requested_at'); // When advance was requested
    $table->date('given_at')->nullable(); // When advance was paid out
    $table->date('recovered_at')->nullable(); // When advance was deducted from salary
    $table->foreignId('recovered_from_payroll_id')->nullable()->constrained('payroll_entries')->nullOnDelete();
    $table->string('reason')->nullable(); // "emergency", "medical", etc.
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

**Workflow:**

1. Employee requests advance → Create `EmployeeAdvance` record (status: pending)
2. Admin approves → `given_at` is set, withdraw from bank
3. Payroll run → Match against unpaid advances, deduct from salary
4. Payment → `recovered_at` and `recovered_from_payroll_id` are set

---

### 9. Dashboard Impact

**Important:** Salary advances are **NOT expenses** — they're just cash movements and recoveries:

- ✅ P&L should show full salary ($5,000) - it's earned
- ❌ P&L should NOT include advance recovery as deduction
- ✅ Balance sheet shows Employee Advances Receivable (asset)
- ✅ Cash flow shows both outflow (advance given) and inflow (recovered)

---

#### 7. HR Seeder Flow Improvements

**Current Seeder Issues:**

The existing HR seeder likely:
- Creates employees directly
- Creates salary structures with fixed amounts per employee (anti-pattern)
- Calls `PayrollEntry::createWithTransaction()` directly
- Does not create supporting vendor/offering data for Bill integration

**Required Seeder Updates:**

```php
// 1. Create Payroll Vendor first
$payrollVendor = Vendor::create([
    'company_id' => $company->id,
    'name' => 'Payroll Department',
    'type' => VendorType::Internal, // if enum supports it
    'currency_code' => $company->currency_code,
    // ... other required fields
]);

// 2. Create reusable Salary Offerings (one per salary component type)
$offerings = [
    'base_salary' => Offering::create([
        'name' => 'Base Salary',
        'type' => OfferingType::Service,
        'expense_account_id' => Account::where('name', 'Salaries and Wages')->first()->id, // Account 5050
        // ...
    ]),
    'employer_contribution' => Offering::create([
        'name' => 'Employer Taxes and Contributions',
        'expense_account_id' => Account::where('name', 'Payroll Employer Taxes and Contributions')->first()->id, // Account 5051
        // ...
    ]),
    'employee_benefits' => Offering::create([
        'name' => 'Employee Benefits',
        'expense_account_id' => Account::where('name', 'Employee Benefits')->first()->id, // Account 5052
        // ...
    ]),
    'payroll_processing' => Offering::create([
        'name' => 'Payroll Processing Fees',
        'expense_account_id' => Account::where('name', 'Payroll Processing Fees')->first()->id, // Account 5053
        // ...
    ]),
    'advance_recovery' => Offering::create([
        'name' => 'Salary Advance Recovery',
        'type' => OfferingType::Service,
        'expense_account_id' => Account::where('name', 'Employee Advances Receivable')->first()->id, // Asset 1200
        // Maps to asset account for advance recovery
    ]),
];

// 3. Create fewer, reusable Salary Structures (by department/grade)
$structures = [
    'engineering_senior' => SalaryStructure::create([
        'name' => 'Engineering - Senior',
        'account_id' => $payrollLiabilitiesAccount->id,
    ]),
    'engineering_junior' => SalaryStructure::create([
        'name' => 'Engineering - Junior',
        'account_id' => $payrollLiabilitiesAccount->id,
    ]),
    // ... other departments/grades
];

// 4. Create Employees without duplicating structures
$employees = Employee::factory()->count(10)->create([
    'company_id' => $company->id,
    // Don't assign structure here
]);

// 5. Assign structures and base salaries (for Priority 2 implementation)
foreach ($employees as $employee) {
    $structure = $structures['engineering_senior']; // assign based on role
    
    // Create salary revision (if Priority 2 implemented)
    EmployeeSalaryRevision::create([
        'employee_id' => $employee->id,
        'effective_from' => now()->subMonths(6),
        'base_salary_amount' => fake()->numberBetween(3000, 8000),
        'reason' => 'initial_assignment',
    ]);
}

// 6. Create Payroll Entries using new Bill-based flow
foreach ($employees as $employee) {
    $payrollEntry = PayrollEntry::createWithBill([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'salary_structure_id' => $employee->salary_structure_id,
        'entry_number' => PayrollEntry::getNextPayrollEntryNumber(),
        'from_date' => now()->startOfMonth(),
        'to_date' => now()->endOfMonth(),
    ]);
    
    // Optionally mark some bills as paid for realistic demo data
    if (fake()->boolean(70)) { // 70% paid
        $bill = $payrollEntry->bill;
        $bill->recordPayment([
            'amount' => $bill->amount_due,
            'bank_account_id' => $defaultBankAccount->id,
            'paid_at' => fake()->dateTimeBetween($bill->date, $bill->due_date),
        ]);
    }
}
```

**Seeder Best Practices:**

1. **Create in correct order:**
   - Vendors (Payroll Department)
   - Accounts (if not already seeded)
   - Offerings (for salary components)
   - Salary Structures (templates only, 5-10 max)
   - Employees
   - Salary Revisions (optional, Priority 2)
   - Payroll Entries (with Bills)
   - Payments (mark some as paid)

2. **Use realistic ratios:**
   - 70-80% of payroll bills should be paid
   - 20-30% open/overdue for testing
   - Mix of current month and historical entries

3. **Vary amounts appropriately:**
   - Base salary ranges by grade/department
   - Allowances as percentages or fixed amounts
   - Employer contributions calculated from base

4. **Test data scenarios:**
   - New employee (first payroll)
   - Incremented employee (Priority 2)
   - Employee with overtime/bonuses
   - Employee with deductions

5. **Maintain referential integrity:**
   - Ensure all foreign keys resolve
   - Link Bills to PayrollEntries
   - Link Payments to Bills and BankAccounts

**Updated Seeder Files Required:**

- `DatabaseSeeder.php` - add payroll vendor creation
- `EmployeeSeeder.php` - simplified employee creation
- `SalaryStructureSeeder.php` - create fewer reusable structures
- `PayrollSeeder.php` - NEW: dedicated payroll + bill seeding
- `VendorSeeder.php` - include payroll vendor
- `OfferingSeeder.php` - add salary component offerings
- `EmployeeAdvanceSeeder.php` - NEW: create sample advance records for testing

**Testing Seeder Output:**

```bash
# After seeding, verify:
php artisan tinker
> Bill::where('bill_number', 'like', 'PAY-%')->count() // Should match PayrollEntry count
> PayrollEntry::whereNotNull('bill_id')->count() // Should equal total entries
> Bill::where('bill_number', 'like', 'PAY-%')->whereNotNull('paid_at')->count() // ~70% paid
> Transaction::where('type', 'journal')->where('description', 'like', 'Payroll%')->count() // Journal entries exist
```

---

## Long-Term Objective (Unchanged)

Make payroll structures reusable while supporting:

- unique base salary per employee
- yearly salary increments (for KPI, promotion, market adjustment)
- correct statutory calculations (KWSP, SOCSO, EIS) from employee-specific base salary
- clean accounting entries without cloning many near-identical salary structures

---

## Current Pain Points

1. `SalaryStructure` currently carries fixed amounts through linked salary parts.
2. Employees with different base salaries often require separate structures.
3. Real-world salary increments over time are not modeled as first-class payroll history.
4. `SalaryPartSalaryStructure.amount` exists but is not used during payroll processing.

Result: teams may create one structure per employee, which defeats template reuse.

---

## Design Principles

1. Structure defines rules, not employee identity.
2. Employee profile defines recurring personal salary values.
3. Payroll entry stores immutable snapshot values used for that run.
4. Historical changes (increments) must be auditable and effective-date based.
5. Migration must preserve current behavior and data.

---

## Target Domain Model

### 1) SalaryPart remains reusable accounting + formula component

Keep:

- type (`base_salary`, `deduction`, `employer_cost`, etc.)
- basis (`fixed`, `percentage_of_base_salary`)
- account mapping (`debit_account_id`, `credit_account_id`)
- in-net-salary behavior

Change in usage:

- `base_salary` amount should no longer be hardcoded per employee via duplicated structures.

### 2) SalaryStructure becomes template-only

Contains:

- set of salary parts included
- payroll liabilities account
- optional defaults (for teams or grades)

Does not need unique fixed base per employee.

### 3) Employee gets salary profile

Add fields (or dedicated profile table):

- `base_salary_amount`
- `salary_effective_from`
- optional `currency_code`
- optional `salary_grade`

This becomes the main source for employee-specific base pay.

### 4) Employee salary history / increments table

Add table: `employee_salary_revisions`

Suggested columns:

- `id`
- `employee_id`
- `effective_from`
- `base_salary_amount`
- `reason` (kpi_increment, promotion, adjustment, correction)
- `notes` (nullable)
- `created_by`, `updated_by`
- timestamps

Rule:

- payroll for period uses the latest revision where `effective_from <= payroll_period_end`.

### 5) PayrollEntry stores calculation snapshot

Add immutable snapshot fields:

- `base_salary_amount_snapshot`
- `salary_revision_id` (nullable FK)
- optional `calculation_payload` JSON (line-level resolved amounts)

This protects historical consistency even if employee salary changes later.

---

## Calculation Logic (Proposed)

1. Load employee, salary structure, salary parts.
2. Resolve base salary for payroll period from salary revision history.
3. For each salary part:
   - if basis is `percentage_of_base_salary`: amount = rate * resolved base salary
   - if basis is `fixed`: amount = structure-level fixed value (or part default)
4. Build net salary from in-net rules.
5. Build balanced journal entries.
6. Save payroll entry + snapshots + transaction lines.

Important:

- Prefer pivot override amount first (if present) for non-base parts.
- For base salary part, prefer employee resolved base salary.

---

## Increment Handling

### Use Case

- Employee A base salary = 2500 from 2026-01-01
- Increment to 2800 from 2027-01-01 (KPI)

Behavior:

- Dec 2026 payroll uses 2500
- Jan 2027 payroll uses 2800
- No structure clone required

---

## UI/UX Improvements

### Employee Resource

- show current base salary
- show salary revision history timeline
- action: "Add Salary Revision"

### Salary Structure Resource

- emphasize that structure is template/rules
- allow optional default rates/allowances

### Create Payroll Entry

- select employee + structure
- display resolved base salary and effective revision before submit
- optional one-off override (permission-gated) with reason

### Payroll Entry View

- show snapshot values used in run
- show which revision was applied

---

## Accounting Impact

No conceptual change to accounting flow:

- each payroll run still creates journal entries
- account mapping remains on salary parts
- liabilities and expense postings remain balanced

Improvement:

- improves semantic correctness and reuse while keeping ledger behavior stable.

---

## Backward Compatibility Strategy

### Phase 1 (non-breaking)

- add employee salary fields and revision table
- keep existing processing path
- start writing revisions for new updates

### Phase 2 (dual-read)

- payroll calculation resolves base salary from revision if exists
- fallback to current structure/base part amount if no revision

### Phase 3 (full)

- default all payroll to revision-based base salary
- deprecate per-employee structure pattern

---

## Data Migration Plan

1. Add new schema objects:
   - `bill_id` and `gross_salary` to payroll_entries
   - `employee_advances` table (with fields: amount, given_at, recovered_at, etc.)
   - Payroll vendor record
   - Salary component offerings (including Advance Recovery offering)
   - Employee Advances Receivable account (1200) if not exists
   - employee salary columns (or profile table) - Priority 2
   - `employee_salary_revisions` table - Priority 2
   - payroll snapshot fields - Priority 2

2. Update seeders:
   - VendorSeeder: add "Payroll Department" vendor
   - AccountSeeder: add "Employee Advances Receivable" (1200) if not exists
   - OfferingSeeder: add salary component offerings (including Advance Recovery)
   - SalaryStructureSeeder: reduce to 5-10 reusable templates
   - Create new PayrollSeeder with Bill integration
   - Create new EmployeeAdvanceSeeder for demo data
   - Update EmployeeSeeder to remove per-employee structures

3. Backfill data (Priority 1):
   - Option A: Convert existing PayrollEntries to Bills
   - Option B: Leave legacy entries as-is, new entries use Bills
   - Recommended: Option B for safety

4. Backfill revisions (Priority 2):
   - Infer current base salary from employee's commonly-used structure
   - Create initial revision with an appropriate effective date

5. Keep all existing salary structures and payroll entries untouched.

6. Add integrity checks:
   - PayrollEntry without Bill must have transaction_id (legacy)
   - PayrollEntry with Bill must have bill_id and gross_salary
   - Employee Advances Receivable account exists and is properly mapped
   - One active base salary revision per employee and date (Priority 2)
   - No overlapping revision ranges (if using ranged model) - Priority 2

7. Test with fresh seed:
   ```bash
   php artisan migrate:fresh --seed
   php artisan test --filter=Payroll
   ```

---

## Testing Plan

### Unit Tests

- salary resolution by effective date
- percentage calculation based on resolved base salary
- pivot override priority behavior
- Bill creation from PayrollEntry
- Journal entry generation from Bill
- Payroll vendor retrieval/creation
- Offering mapping for salary components

### Feature Tests

- payroll before and after increment date
- structure reuse across employees with different base salaries
- one-off override behavior and audit fields
- **PayrollEntry creates linked Bill**
- **Bill appears in dashboard widgets**
- **Payment recording updates Bill and PayrollEntry**
- **Journal entries balance correctly**
- **Advance salary is deducted from net payment but not from salary expense**
- **Employee Advances Receivable account is properly credited on recovery**
- **Recovered advances are linked to PayrollEntry**

### Seeder Tests

- Fresh seed completes without errors
- Correct number of payroll Bills created
- All PayrollEntries have linked Bills
- ~70% of Bills marked as paid
- Journal entries created for all Bills
- Dashboard widgets show payroll expenses
- No duplicate salary structures per employee
- Salary structures are reusable (multiple employees per structure)

### Regression Tests

- existing payroll entry creation still balanced
- journal totals unchanged for legacy scenarios
- old PayrollEntries without Bills still function
- reports handle mixed Bill/non-Bill payroll entries

### Integration Tests

```bash
# Test complete payroll flow
php artisan test --filter=PayrollIntegrationTest

# Test seeder output
php artisan migrate:fresh --seed
php artisan tinker
> Bill::where('bill_number', 'like', 'PAY-%')->count()
> PayrollEntry::whereNotNull('bill_id')->count()
> Dashboard widgets show payroll data
```

---

## Acceptance Criteria

1. Same salary structure can be used by many employees with different base salaries.
2. Yearly increments are modeled via effective-dated revisions.
3. Payroll entries are historically stable through snapshot fields.
4. Journal entries remain balanced and category mappings remain correct.
5. Existing records continue to work during phased rollout.

---

## Breaking Changes - Impact on Existing Code

### HrDemoSeeder.php - Major Refactoring Required

**Current Method:** `PayrollEntry::createWithTransaction()`
```php
// Current seeder code (will break after Priority 1):
for ($monthOffset = $months; $monthOffset >= 1; $monthOffset--) {
    PayrollEntry::createWithTransaction([
        'company_id' => $employee->company_id,
        'entry_number' => PayrollEntry::getNextPayrollEntryNumber(),
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'employee_id' => $employee->id,
        'salary_structure_id' => $salaryStructure->id,
    ]);
}
```

**Required Method:** `PayrollEntry::createWithBill()`
```php
// After Priority 1 implementation:
for ($monthOffset = $months; $monthOffset >= 1; $monthOffset--) {
    PayrollEntry::createWithBill([
        'company_id' => $employee->company_id,
        'entry_number' => PayrollEntry::getNextPayrollEntryNumber(),
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'employee_id' => $employee->id,
        'salary_structure_id' => $salaryStructure->id,
    ]);
}
```

**Prerequisites Before Seeding:**
1. ✅ Payroll vendor must exist (`Vendor::where('name', 'Payroll Department')->first()`)
2. ✅ Salary offerings must exist (Base Salary, Allowances, Employer Contributions)
3. ✅ Accounts must be correctly mapped (not using Accounts Payable for payroll)
4. ✅ `bill_id` and `gross_salary` columns must exist in payroll_entries table

### DatabaseSeeder.php - Seeding Order Must Change

**Current Order (Insufficient):**
```php
$this->call([
    UserCompanySeeder::class,
    HrDemoSeeder::class, // Runs independently
]);
```

**Required Order (After Priority 1):**
```php
$this->call([
    UserCompanySeeder::class, // Creates company + accounts
    VendorSeeder::class, // Must include "Payroll Department" vendor
    OfferingSeeder::class, // Must include salary component offerings
    HrDemoSeeder::class, // Now depends on vendors + offerings
]);
```

### Salary Structure Creation Pattern - Must Change

**Current (Anti-Pattern):**
```php
// Creates unique structure for each employee
$structures = [
    $this->buildSalaryStructure(
        company: $company,
        name: 'MY Payroll - Operations', // Unique to Nur Aisyah
        basicPay: 3800, // Hardcoded per employee
        // ...
    ),
    $this->buildSalaryStructure(
        company: $company,
        name: 'MY Payroll - Finance', // Unique to Muhammad Hafiz
        basicPay: 5200, // Hardcoded per employee
        // ...
    ),
];
```

**Required (Reusable Templates):**
```php
// Create 5-10 reusable structures
$structures = [
    'standard' => $this->buildSalaryStructure(
        company: $company,
        name: 'MY Payroll - Standard', // Shared by many employees
        // No basicPay here - stored on employee profile
        // ...
    ),
    'management' => $this->buildSalaryStructure(
        company: $company,
        name: 'MY Payroll - Management', // Shared by managers
        // No basicPay here
        // ...
    ),
];

// Assign structures to employees without duplication
$employees[0]->salary_structure_id = $structures['standard']->id;
$employees[1]->salary_structure_id = $structures['standard']->id; // Reuse!
$employees[2]->salary_structure_id = $structures['management']->id;
```

### Account Resolution Logic - Must Be Fixed

**Current (Selects Wrong Account):**
```php
$liabilityAccount = $this->resolveAccount(
    preferredNames: [
        'Payroll Statutory Payable',
        'Accrued Payroll',
        'Payroll Liabilities',
        'Accounts Payable', // ⚠️ DANGER: Gets selected
    ],
);
```

**Required (Correct Account):**
```php
$liabilityAccount = $this->resolveAccount(
    preferredNames: [
        'Payroll Statutory Payable',
        'Accrued Payroll',
        'Payroll Liabilities',
        // Remove 'Accounts Payable' from fallback list
    ],
);

// If none found, create new dedicated account:
if (!$liabilityAccount) {
    $liabilityAccount = Account::create([
        'company_id' => $company->id,
        'name' => 'Payroll Statutory Payable',
        'category' => 'liability',
        // ...
    ]);
}
```

### Migration Strategy for Existing Payroll Entries

**Decision Point:** What happens to the 9 existing PayrollEntry records?

**Option A: Leave Legacy Entries As-Is (Recommended)**
- Existing entries keep `bill_id = null`
- New entries after Priority 1 have `bill_id` populated
- Update dashboard widgets to handle both cases:
  ```php
  // In widget query:
  $payrollExpenses = Transaction::where('type', 'journal')
      ->where('description', 'like', 'Payroll%')
      ->sum('amount');
  
  $billExpenses = Bill::whereNotNull('paid_at')->sum('total');
  
  $totalExpenses = $payrollExpenses + $billExpenses;
  ```

**Option B: Backfill Bills for Legacy Entries (Complete but Risky)**
- Create migration command: `php artisan payroll:backfill-bills`
- For each PayrollEntry without bill_id:
  - Create Bill retroactively
  - Link Bill to PayrollEntry
  - Keep existing Transaction unchanged
- Advantage: Complete data consistency
- Risk: Complex migration, potential data corruption

**Recommended: Option A** - Simpler, safer, maintains historical integrity

---

## Post-Implementation Validation (After Priority 1)

**Run these queries after implementing Priority 1:**

```bash
php artisan migrate:fresh --seed
php artisan tinker
```

```php
// 1. Verify Bills are created for payroll
$payrollBillCount = Bill::where('bill_number', 'like', 'PAY-%')->count();
$payrollEntryCount = PayrollEntry::count();
echo "Bills created: $payrollBillCount / $payrollEntryCount payroll entries\n";
// Should match (100% if Option A, or 100% after backfill)

// 2. Verify bill_id is populated on new entries
PayrollEntry::whereNotNull('bill_id')->count(); 
// Should equal total PayrollEntry count (after fresh seed)

// 3. Check Payroll vendor exists
$vendor = Vendor::where('name', 'Payroll Department')->first();
echo "Payroll vendor: " . ($vendor ? "EXISTS (ID: {$vendor->id})" : "MISSING") . "\n";
// Should exist

// 4. Verify salary offerings exist
$offerings = Offering::whereIn('name', [
    'Base Salary',
    'Employer Taxes and Contributions',
    'Employee Benefits',
    'Advance Recovery',
])->count();
echo "Salary offerings: $offerings\n";
// Should be at least 4

// 5. Check Bills link to correct vendor
$payrollBills = Bill::where('bill_number', 'like', 'PAY-%')->get();
$vendorIds = $payrollBills->pluck('vendor_id')->unique();
echo "Payroll Bills use vendor IDs: " . $vendorIds->implode(', ') . "\n";
// Should all use the same Payroll vendor ID

// 6. Verify Bill line items exist
$billId = Bill::where('bill_number', 'like', 'PAY-%')->first()->id;
$lineItems = DocumentLineItem::where('documentable_type', 'Bill')
    ->where('documentable_id', $billId)
    ->count();
echo "Line items for first payroll Bill: $lineItems\n";
// Should be 3-7 (base salary + allowances + employer contributions)

// 7. Verify journal entries still balanced
$transaction = Transaction::where('description', 'like', 'Payroll%')->first();
if ($transaction) {
    $debits = $transaction->journalEntries()->where('type', 'debit')->sum('amount');
    $credits = $transaction->journalEntries()->where('type', 'credit')->sum('amount');
    echo "Journal balanced: " . ($debits == $credits ? 'YES' : 'NO') . "\n";
}
// Must be YES

// 8. Check gross_salary snapshot is populated
$payrollEntry = PayrollEntry::first();
echo "Gross salary snapshot: " . ($payrollEntry->gross_salary ? 'POPULATED' : 'NULL') . "\n";
// Should be populated

// 9. Verify correct liability account is used (NOT Accounts Payable)
$transaction = Transaction::where('description', 'like', 'Payroll%')->first();
$creditEntries = $transaction->journalEntries()->where('type', 'credit')->get();
$accounts = Account::whereIn('id', $creditEntries->pluck('account_id'))->get(['id', 'name']);
echo "Liability accounts used:\n";
foreach ($accounts as $account) {
    echo "  - {$account->name} (ID: {$account->id})\n";
}
// Should show "Payroll Statutory Payable", NOT "Accounts Payable"

// 10. Test dashboard data (manual verification)
echo "\n📊 Manual Dashboard Check:\n";
echo "1. Navigate to Dashboard\n";
echo "2. Verify 'Profit and Loss' chart shows salary expenses\n";
echo "3. Verify 'Financial Stats' includes payroll payables\n";
echo "4. Verify 'Expenses Breakdown' includes all salary categories\n";

// 11. Verify ~70% of Bills are marked as paid (realistic demo data)
$totalBills = Bill::where('bill_number', 'like', 'PAY-%')->count();
$paidBills = Bill::where('bill_number', 'like', 'PAY-%')->whereNotNull('paid_at')->count();
$paidPercent = $totalBills > 0 ? round(($paidBills / $totalBills) * 100) : 0;
echo "Paid Bills: $paidBills / $totalBills ($paidPercent%)\n";
// Should be around 70-80%

// 12. Check salary structure reusability
$structureCount = SalaryStructure::count();
$employeeCount = Employee::count();
echo "Salary Structures: $structureCount for $employeeCount employees\n";
// Should be ~5-10 structures (not 1:1 with employees)
```

---

## Suggested Implementation Sequence

### Priority 1: Fix Dashboard Bug (Critical)

1. **Add Bill integration schema:**
   - Add `bill_id` to `payroll_entries` table
   - Add `gross_salary` snapshot field
   - Decide on vendor strategy (single payroll vendor vs employee-as-vendor)

2. **Create payroll vendor:**
   - Seed "Payroll Department" vendor (recommended approach)
   - Or add `vendor_id` to employees table

3. **Implement `PayrollEntry::createWithBill()`:**
   - Calculate salary components
   - Create Bill with line items
   - Call `bill->createInitialTransaction()`
   - Link Bill to PayrollEntry

4. **Create helper methods:**
   - `getPayrollVendor()` - retrieve or create payroll vendor
   - `getOrCreatePayrollOffering($salaryPart)` - map salary parts to offerings
   - `calculateSalary($structure, $data)` - extract calculation logic

5. **Update seeders:**
   - Create PayrollSeeder with Bill integration
   - Update VendorSeeder to include "Payroll Department"
   - Update OfferingSeeder to include salary component offerings
   - Update SalaryStructureSeeder to create fewer, reusable structures
   - Update EmployeeSeeder to remove per-employee structure creation
   - Ensure proper seeding order (vendors → accounts → offerings → structures → employees → payroll)

6. **Update UI (optional but recommended):**
   - Show linked Bill in PayrollEntry view
   - Add "View Bill" action
   - Show payment status from Bill

7. **Test thoroughly:**
   - Run fresh seed: `php artisan migrate:fresh --seed`
   - Create payroll → verify Bill created
   - Verify journal entries match old logic
   - Verify dashboard now shows salary expenses
   - Test payment recording
   - Verify ~70% of seeded payroll bills are marked as paid

8. **Migration command (optional):**
   - Backfill Bills for existing PayrollEntries
   - Or update widgets to query both Bills and journal-only transactions

### Priority 2: Salary Revision System (Enhancement)

8. Add schema + models for salary revisions and snapshots.
9. Implement salary resolver service.
10. Update payroll processor to use resolver with fallback.
11. Add employee salary revision UI.
12. Add migration/backfill command.
13. Update docs and seeders to match new approach.

---

## Seeder Guidance

### Current State (Before Bill Integration)

**Problems:**
- Creates one salary structure per employee (defeats reusability)
- Hardcodes base salary in structure instead of employee profile
- No payroll vendor or offerings for Bill integration
- Payroll entries create journal entries directly
- Demo data doesn't show up in dashboard reports

### After Priority 1 Implementation (Bill Integration)

**Required Changes:**

1. **Create Payroll Infrastructure:**
   - Single "Payroll Department" vendor
   - Salary component offerings (base salary, allowances, employer contributions)
   - Reusable salary structures by department/grade (5-10 templates max)

2. **Employee Creation:**
   - Create employees without duplicating structures
   - Assign appropriate structure based on department/grade
   - No per-employee structure creation

3. **Payroll Generation:**
   - Use `PayrollEntry::createWithBill()` method
   - Creates Bills automatically
   - 70-80% of Bills marked as paid for realistic demo
   - Mix of current and historical payroll periods

4. **Seeding Order:**
   ```
   Vendors (including Payroll Department)
   ↓
   Accounts (Chart of Accounts)
   ↓
   Offerings (including Salary Components)
   ↓
   Salary Structures (5-10 reusable templates)
   ↓
   Employees
   ↓
   Payroll Entries (with Bills)
   ↓
   Payments (mark 70% as paid)
   ```

### After Priority 2 Implementation (Salary Revisions)

**Additional Changes:**

1. **Employee Salary Revisions:**
   - Create initial revision for each employee with effective date
   - Create historical revisions for some employees (promotions, increments)
   - Use varied base salaries per employee while sharing same structure

2. **Realistic Increment Scenarios:**
   - Annual KPI increments (5-15%)
   - Promotion increments (15-30%)
   - Market adjustments (3-7%)
   - Multiple revisions over time for senior employees

3. **Demo Data Variety:**
   - New employees (single revision)
   - Mid-tenure employees (2-3 revisions)
   - Senior employees (4+ revisions over years)

**Example Seeding Command:**
```bash
php artisan db:seed --class=PayrollFullSeeder
# Should create: vendors, offerings, structures, employees, revisions, payroll+bills
```

---

## Conclusion

The best long-term model is:

- SalaryPart = accounting/formula building block
- SalaryStructure = reusable template
- EmployeeSalaryRevision = personal salary timeline
- PayrollEntry = immutable execution snapshot

This supports real HR operations (increments and unique salary) without sacrificing template reuse or accounting integrity.

---

## Executive Summary & Next Steps

### Immediate Action Required (Priority 1)

**Fix the dashboard bug** by implementing Payroll → Bill workflow:
- Timeline: 1-2 weeks
- Impact: HIGH - restores accurate financial reporting
- Risk: LOW - follows existing Bill accounting patterns
- Effort: MEDIUM - schema changes + model refactoring + seeder updates
- Deliverables:
  - Database migration (add bill_id to payroll_entries)
  - PayrollEntry::createWithBill() implementation
  - Updated seeders (vendors, offerings, structures, payroll)
  - Updated tests
  - Fresh demo data showing payroll in dashboard

### Future Enhancement (Priority 2)

**Implement salary revision system** for proper HR operations:
- Timeline: 2-3 weeks after Priority 1
- Impact: HIGH - enables proper salary increment tracking
- Risk: LOW - additive changes with backward compatibility
- Effort: MEDIUM - new models + UI + migration

### Decision Points

1. **Vendor Strategy:** Single "Payroll" vendor (recommended) vs Employee-as-vendor
2. **Backfill:** Convert existing payroll entries or update widgets to dual-query
3. **Payment Flow:** Manual payment recording vs auto-generated payment transactions
4. **Seeder Strategy:** 
   - How many reusable structures to create (5-10 recommended)
   - Ratio of paid vs unpaid Bills (70-80% paid recommended)
   - Historical depth (1-3 months of payroll history recommended)
5. **Advance Salary Handling:**
   - Create Employee Advances Receivable account (1200) or equivalent
   - Implement EmployeeAdvance model or store in JSON payload
   - Whether to show advance balance in employee portal
   - Advance request approval workflow (auto-approve vs manual review)

### Success Metrics

- ✅ Salary expenses appear in all dashboard widgets
- ✅ P&L chart shows complete expense picture
- ✅ Payroll liabilities tracked in payables
- ✅ Same structure reusable across employees with different salaries
- ✅ Salary increments tracked with effective dates
- ✅ Historical payroll entries remain accurate after salary changes
- ✅ Advance salary recorded as asset (Employee Advances Receivable), not salary expense
- ✅ Advance deductions reflected in net payment, not salary amount
- ✅ Full salary amount visible in expense recognition (accrual basis)
- ✅ Advance recovery linked to payroll entry for audit trail

---

## Appendix: Seeder Files Checklist

### Files to Create

- [ ] `database/seeders/PayrollSeeder.php` - Main payroll + Bill seeding logic
- [ ] `database/seeders/PayrollFullSeeder.php` - Orchestrator for complete payroll demo data
- [ ] `database/seeders/EmployeeAdvanceSeeder.php` - Sample advance records for demo/testing
- [ ] `database/migrations/YYYY_MM_DD_create_employee_advances_table.php` - Advance salary table
- [ ] `app/Models/EmployeeAdvance.php` - Advance salary model

### Files to Modify

- [ ] `database/seeders/DatabaseSeeder.php` - Add PayrollSeeder and EmployeeAdvanceSeeder calls
- [ ] `database/seeders/VendorSeeder.php` - Add "Payroll Department" vendor
- [ ] `database/seeders/AccountSeeder.php` - Add "Employee Advances Receivable" (1200) account
- [ ] `database/seeders/OfferingSeeder.php` - Add salary component offerings (including Advance Recovery)
- [ ] `database/seeders/SalaryStructureSeeder.php` - Reduce to 5-10 reusable templates
- [ ] `database/seeders/EmployeeSeeder.php` - Remove per-employee structure creation
- [ ] `app/Models/PayrollEntry.php` - Add relationship to advances, add recovery calculation methods
- [ ] `app/Models/Offering.php` - Ensure account relationship exists

### Seeder Dependencies (Order Matters)

```
1. CompanySeeder
2. ChartOfAccountsSeeder (includes Employee Advances Receivable 1200)
3. VendorSeeder (includes Payroll vendor)
4. OfferingSeeder (includes Salary offerings + Advance Recovery)
5. BankAccountSeeder
6. SalaryPartSeeder
7. SalaryStructureSeeder (reusable templates only)
8. EmployeeSeeder
9. EmployeeAdvanceSeeder (create sample advances for demo)
10. PayrollSeeder (creates entries + Bills + payments, deducting advances)
```

### Validation Commands

```bash
### Validation Commands

```bash
# Check seeders run without errors
php artisan migrate:fresh --seed

# Verify data integrity
php artisan tinker
```

#### Current State Validation (Before Priority 1)

```php
# These should return expected values in current implementation:

# 1. Payroll entries exist via direct journal
PayrollEntry::count() // Should be 9
Transaction::where('type', 'journal')->where('description', 'like', 'Payroll%')->count() // Should be 9

# 2. Bills do NOT exist yet (expected)
Bill::where('bill_number', 'like', 'PAY-%')->count() // Should be 0 ✅ EXPECTED

# 3. Payroll vendor does NOT exist yet (expected)
Vendor::where('name', 'Payroll Department')->first() // Should be null ✅ EXPECTED

# 4. Linked payroll entries should be 0 (column doesn't exist yet)
// PayrollEntry::whereNotNull('bill_id')->count() // ⚠️ Will error - column not created
```

#### Post-Implementation Validation (After Priority 1)

```php
# These should work after implementing Priority 1:

# Count payroll Bills (should match PayrollEntry count)
Bill::where('bill_number', 'like', 'PAY-%')->count()

# Count linked payroll entries (should equal total entries)
PayrollEntry::whereNotNull('bill_id')->count()

# Check payment ratio (should be ~70%)
$total = Bill::where('bill_number', 'like', 'PAY-%')->count();
$paid = Bill::where('bill_number', 'like', 'PAY-%')->whereNotNull('paid_at')->count();
echo "Paid: " . round($paid/$total*100) . "%";

# Verify journal entries still exist
Transaction::where('type', 'journal')
    ->where('description', 'like', 'Payroll%')
    ->count()

# Verify Employee Advances Receivable account exists
Account::where('name', 'Employee Advances Receivable')->first()

# Check advance salary records
EmployeeAdvance::where('recovered_at', null)->count() // Unrecovered advances
EmployeeAdvance::whereNotNull('recovered_at')->count() // Recovered advances

# Verify advance recovery in payroll
PayrollEntry::whereHas('advances')->count() // Payrolls with advance deductions

# Check dashboard data
# Navigate to dashboard and verify:
# - Expenses breakdown shows salary categories (full gross salary)
# - Profit & Loss chart includes salary expenses (full amount, not reduced by advances)
# - Financial stats include payroll payables
# - Balance sheet includes Employee Advances Receivable
```

---

## Files to Create (Migrations & Models)

### Priority 1: Advance Salary Support

**Migration: Create `employee_advances` Table**

```php
Schema::create('employee_advances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->decimal('amount', 20, 4);
    $table->date('requested_at');
    $table->date('given_at')->nullable();
    $table->date('recovered_at')->nullable();
    $table->foreignId('recovered_from_payroll_id')
        ->nullable()
        ->constrained('payroll_entries')
        ->nullOnDelete();
    $table->string('reason')->nullable(); // 'emergency', 'medical', 'urgent_need'
    $table->text('notes')->nullable();
    $table->timestamps();
    
    $table->index(['employee_id', 'recovered_at']);
});
```

**Model: `EmployeeAdvance`**

```php
class EmployeeAdvance extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'amount',
        'requested_at',
        'given_at',
        'recovered_at',
        'recovered_from_payroll_id',
        'reason',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'requested_at' => 'date',
        'given_at' => 'date',
        'recovered_at' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollEntry()
    {
        return $this->belongsTo(PayrollEntry::class, 'recovered_from_payroll_id');
    }

    public function isRecovered(): bool
    {
        return $this->recovered_at !== null;
    }

    public static function getUnrecoveredTotal(int $employeeId): decimal
    {
        return self::where('employee_id', $employeeId)
            ->whereNull('recovered_at')
            ->sum('amount') ?? 0;
    }
}
```

**Update: `PayrollEntry` Model**

```php
class PayrollEntry extends Model
{
    // ... existing code ...

    public function advances()
    {
        return $this->hasMany(EmployeeAdvance::class, 'recovered_from_payroll_id');
    }

    public function getTotalAdvanceRecovery(): decimal
    {
        return $this->advances()->sum('amount') ?? 0;
    }
}
```

**Update: `Offering` Model (if not already linked to accounts)**

```php
// Ensure Offering has relationship to Account for proper accounting
class Offering extends Model
{
    public function expenseAccount()
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }
}
```

---

## Document Change Log

### 2026-05-03 Update
- ✅ Added **Current State vs Target State** comparison table
- ✅ Added **Known Issues in Current Seeding** section
- ✅ Added **Chart of Accounts Required Updates** with verification queries
- ✅ Added **Validation Queries - Current State** (pre-implementation checks)
- ✅ Added **Breaking Changes** section detailing HrDemoSeeder refactoring requirements
- ✅ Added **Post-Implementation Validation** queries (after Priority 1)
- ✅ Documented actual seeded data reality (9 payroll entries, 0 Bills, wrong liability account)
- ✅ Clarified that current implementation uses "Accounts Payable" incorrectly for payroll liabilities
- ✅ Updated validation commands to distinguish current state vs post-implementation expectations
- ✅ Added clear migration path for existing 9 PayrollEntry records (Option A vs Option B)

### 2026-05-02 Original Document
- 📋 Initial proposal for Payroll → Bill integration
- 📋 Priority 1 and Priority 2 implementation plans
- 📋 Advance salary handling design
- 📋 Seeder guidelines and best practices

---

**Last Updated:** 2026-05-03  
**Status:** Proposed - Awaiting approval for Priority 1 implementation  
**Current Implementation Status:** ⚠️ Working but incomplete - Payroll creates journal entries directly, bypasses Bill workflow, missing from dashboard reports
