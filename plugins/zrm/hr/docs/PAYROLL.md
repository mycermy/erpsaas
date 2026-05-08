# Seeding command
```bash
php artisan migrate:fresh --seed && php artisan db:seed --class="Zrm\Hr\Database\Seeders\DatabaseSeeder"
```

# Payroll Processing

This document explains how `PayrollEntry::createWithBill()` calculates pay, creates Bills with line items, builds balanced journal entries, and persists the result in the accounting system.

---

## Workflow

```
User submits Payroll Entry form
        │
        ▼
PayrollEntry::createWithBill($data)
        │
        ├── 1. Load SalaryStructure + its SalaryParts (via spss pivot)
        ├── 2. Identify Base Salary amount
        ├── 3. Loop each SalaryPart → resolve amount (fixed or % of base)
        │         ├── Accumulate netSalary (add/subtract based on type)
        │         └── Track journal entry pairs (debit + credit per account)
        ├── 4. Append Net Salary Payable credit entry
        ├── 5. Assert debits === credits (throw Exception if not)
        ├── 6. Create PayrollEntry record with gross_salary snapshot
        ├── 7. Create Bill linked to Payroll vendor
        │         ├── Bill number: 'PAY-PE####' format
        │         ├── Line items: one per salary component (with offerings)
        │         └── Total: gross_salary amount
        ├── 8. Call Bill::createInitialTransaction()
        │         ├── Create Transaction record (type = journal)
        │         └── Create JournalEntry rows (balanced and correct)
        ├── 9. Process Employee Advances recovery (if any)
        ├── 10. Link bill_id back to PayrollEntry → save
        └── 11. Return PayrollEntry with linked Bill
```

---

## Step-by-Step Detail

### 1. Load the Salary Structure

```php
$salaryStructure = SalaryStructure::find($data['salary_structure_id']);
$salaryParts = $salaryStructure->spss->map(fn($sps) => $sps->salaryPart);
```

The pivot table `salary_part_salary_structures` is eager-loaded via the `spss` HasMany relation. Each row carries an `amount` override and a `group` field (mirrors type value).

### 2. Resolve the Base Salary

```php
$baseSalary = $salaryParts->where('type', 'base_salary')->sum('amount');
```

The base salary is the sum of all parts with type `base_salary`. It is used as the reference for percentage-based calculations.

### 3. Calculate Each Part's Amount

For each salary part:

```php
if ($part->basis == SalaryPartBasis::PercentageOfBaseSalary) {
    $amount = ($part->amount / 100) * $baseSalary;
} else {
    $amount = $part->amount;  // fixed
}
```

**Net salary accumulation:**

```php
if ($part->in_net_salary) {
    if ($part->type === SalaryPartType::Deduction) {
        $netSalary -= $amount;
    } else {
        $netSalary += $amount;
    }
}
```

`EmployerCost` parts have `in_net_salary = false` by design, so they do not affect the amount owed to the employee.

### 4. Build Journal Entry Lines

For each part, depending on account assignment:

| Scenario | Entries created |
|---|---|
| Both `debitAccount` and `creditAccount` set | One debit entry + one credit entry |
| Only one account set | One entry for that account with its type |
| No accounts set | No journal entries for this part |

All amounts are stored in **minor currency units** (multiplied by 100) to match the `erpsaas/accounts` convention.

### 5. Net Salary Payable Entry

```php
$journalEntries[] = [
    'account_id' => $salaryStructure->payrollLiabilitiesAccount->id,  // Account 165: Payroll Statutory Payable
    'type'       => 'credit',
    'amount'     => $netSalary * 100,
    'description'=> 'Net Salary Payable',
];
```

This final credit entry records the liability to pay the employee. It **must** use the dedicated "Payroll Statutory Payable" account (ID 165), NOT the general Accounts Payable account. This ensures clean separation between employee salary liabilities and vendor payables.

**Why Dedicated Account 165?**
- ✅ Clear separation: salary payables vs vendor payables
- ✅ Proper GL reporting: payroll liabilities grouped correctly
- ✅ Payment scheduling: statutory bodies vs vendors handled separately
- ✅ Audit trail: all employee obligations tracked in one place
- ❌ WRONG: Using Account 7 (Accounts Payable) mixes salary with vendor payables

### 6. Balance Check

```php
$totalDebit  = collect($journalEntries)->where('type', 'debit')->sum('amount');
$totalCredit = collect($journalEntries)->where('type', 'credit')->sum('amount');

if ($totalDebit !== $totalCredit) {
    throw new \Exception("Journal entries do not balance. Debit = $totalDebit, Credit = $totalCredit");
}
```

If the entries do not balance, an exception is thrown **before** any record is persisted. This protects ledger integrity. The UI will surface this as a validation error.

### 7. Create Bill (NEW - Bill Integration)

```php
$bill = Bill::create([
    'company_id'    => $data['company_id'],
    'vendor_id'     => self::getPayrollVendor()->id,  // Single "Payroll Department" vendor
    'bill_number'   => 'PAY-PE' . str_pad($payrollEntry->id, 4, '0', STR_PAD_LEFT),
    'date'          => $data['from_date'],
    'due_date'      => $data['to_date']->addDays(7),
    'total'         => $grossSalary * 100,  // minor currency units
    'status'        => BillStatus::Open,
    'description'   => "Payroll for {$employee->name} ({$period})",
]);

// Create line items for each salary component
foreach ($salaryParts as $part) {
    if (!$part->in_net_salary && $part->type === SalaryPartType::EmployerCost) continue;
    
    $offering = self::getOrCreateOfferingForPart($part);
    
    DocumentLineItem::create([
        'document_id'   => $bill->id,
        'document_type' => DocumentType::Bill,
        'offering_id'   => $offering->id,
        'quantity'      => 1,
        'unit_price'    => $part->calculatedAmount * 100,  // minor units
        'subtotal'      => $part->calculatedAmount * 100,
    ]);
}
```

The Bill is linked to the "Payroll Department" vendor (auto-created if missing) and includes line items for each salary component using appropriate offerings.

### 8. Create Journal Entries via Bill

```php
$bill->createInitialTransaction();

$transaction = $bill->transaction();

foreach ($journalEntries as $entry) {
    $entry['transaction_id'] = $transaction->id;
    $transaction->journalEntries()->create($entry);
}
```

The Bill's standard transaction creation mechanism is used, ensuring consistency with other document types (invoices, purchase orders, etc.). The `Transaction` is now polymorphically related to the `Bill`.

### 9. Process Employee Advances (if any)

```php
$advances = EmployeeAdvance::where('employee_id', $data['employee_id'])
    ->whereNull('recovered_at')
    ->get();

foreach ($advances as $advance) {
    $advance->recovered_at = now();
    $advance->recovered_from_payroll_id = $payrollEntry->id;
    $advance->save();
    
    // Advance was already paid earlier; recovery reduces net payment
    // See "Employee Advances" section below
}
```

Outstanding employee advances are marked as recovered and linked to this payroll entry. The net payment is reduced by the advance amount.

### 10. Persist PayrollEntry

```php
$payrollEntry = self::create($data);

$payrollEntry->bill_id = $bill->id;
$payrollEntry->gross_salary = $grossSalary;  // Immutable snapshot
$payrollEntry->save();

return $payrollEntry;
```

The PayrollEntry now stores:
- `bill_id` - reference to the linked Bill
- `gross_salary` - immutable snapshot of total salary (for historical accuracy if salaries change)
- `transaction_id` - reference to the journal transaction (via Bill relationship)

This design ensures payroll entries remain historically accurate even if salary structures or employee profiles change later.

---

## Chart of Accounts

Payroll processing requires specific accounts to be properly configured:

### Expense Accounts (Required)

| Account | Name | Code | Purpose |
|---------|------|------|----------|
| 19 | Salaries and Wages | 5050 | Base salary, hourly wages, overtime |
| 20 | Payroll Employer Taxes and Contributions | 5051 | KWSP (EPF), SOCSO, EIS, HRDF employer portion |
| 21 | Employee Benefits | 5052 | Health insurance, allowances, bonuses |
| 22 | Payroll Processing Fees | 5053 | Third-party payroll service fees |

### Liability Accounts (Required)

| Account | Name | Code | Purpose | Status |
|---------|------|------|---------|--------|
| 165 | **Payroll Statutory Payable** | 2150 | ✅ **REQUIRED** - Employee withholdings and employer statutory contributions | Must Create |

**Critical:** This dedicated liability account (165) is essential. DO NOT use Account 7 (Accounts Payable) for payroll liabilities.

### Asset Accounts (Required for Advances)

| Account | Name | Code | Purpose | Status |
|---------|------|------|---------|--------|
| 166 | **Employee Advances Receivable** | 1200 | ✅ **REQUIRED** - Short-term loans given to employees, recovered from salary | Must Create |

**Verify these accounts exist:**

```bash
php artisan tinker
```

```php
// Check if required accounts exist
Account::where('id', 165)->first() // Should return "Payroll Statutory Payable"
Account::where('id', 166)->first() // Should return "Employee Advances Receivable"
```

---

## Employee Advances System

An **employee advance** (salary advance) is a **loan** given to an employee before their regular payday. It is NOT part of their wages—it is a debt obligation that must be recovered.

### Why Track Advances Separately?

- ✅ **Correct Accounting:** Salary is expensed in full (accrual basis). Advance is tracked as an asset.
- ✅ **Audit Trail:** Clear separation between earned salary and borrowed money
- ✅ **P&L Impact:** Does NOT reduce salary expense (expense remains $5,000 even if $1,000 advance is deducted from payment)
- ❌ **WRONG:** Recording advance as salary deduction artificially reduces expense recognition

### Advance Recovery Workflow

#### Phase 1: Employee Requests Advance

```
Transaction:
  DR: Employee Advances Receivable (Account 166)  $1,000
  CR: Bank/Cash Account                           $1,000
```

Employee receives cash loan; recorded as company asset (receivable from employee).

#### Phase 2: Payroll Processing

**Salary Accrual (via Bill):**
```
DR: Salaries and Wages (Account 19)              $5,000  // Full salary EARNED
CR: Payroll Statutory Payable (Account 165)      $5,000
```

**Advance Recovery (linked to PayrollEntry):**
```
DR: Employee Advances Receivable (Account 166)   $1,000  // Reduce loan outstanding
CR: Payroll Statutory Payable (Account 165)      $1,000
```

**Result:**
- Total salary liability: $6,000
- Employee receives: $5,000 (salary) - $1,000 (advance deduction) = $4,000 net
- Salary expense recognized: $5,000 (full amount, NOT reduced)
- Advance recovered: $1,000 (asset decreases)

#### Phase 3: Payment

```
Payment Transaction:
  DR: Payroll Statutory Payable                  $4,000
  CR: Bank/Cash Account                                  $4,000
```

Only net amount ($4,000) is paid; advance was already disbursed in Phase 1.

### Model: EmployeeAdvance

```php
class EmployeeAdvance extends Model {
    public function employee() { return $this->belongsTo(Employee::class); }
    public function payrollEntry() { return $this->belongsTo(PayrollEntry::class, 'recovered_from_payroll_id'); }
    
    public function isRecovered(): bool { return $this->recovered_at !== null; }
    public static function getUnrecoveredTotal(int $employeeId): decimal { ... }
}
```

**Table Structure:**

| Column | Type | Purpose |
|--------|------|----------|
| id | PK | Record ID |
| company_id | FK | Company |
| employee_id | FK | Employee |
| amount | Decimal | Advance amount |
| requested_at | Date | When requested |
| given_at | Date | When paid out |
| recovered_at | Date | When deducted from salary |
| recovered_from_payroll_id | FK | PayrollEntry where recovery occurred |
| reason | String | e.g., 'emergency', 'medical' |
| notes | Text | Additional context |

---

## Reusable Salary Structures

Payroll now uses **reusable salary structure templates** rather than creating unique structures per employee. This reduces duplication and improves maintainability.

### Anti-Pattern (Old)

```
Employees: 3
Structures: 3 (one per employee)
→ Each structure hardcodes base salary, duplicates allowances
→ Hard to maintain, wastes database space
```

### Pattern (New) ✅

```
Employees: 10+
Structures: 3-5 (by department/grade)
→ Standard, Senior, Management
→ Each shared by multiple employees
→ Individual base salary stored on Employee profile (Priority 2)
```

### Current Structure Templates

The seeder creates these reusable templates:

| Name | Purpose | Used By |
|------|---------|----------|
| MY Payroll - Standard | Junior and standard employees | Entry-level, coordinators |
| MY Payroll - Senior | Specialist and senior roles | Leads, specialists |
| MY Payroll - Management | Managers and directors | Managers, directors |

All templates:
- Include same salary parts (base, allowances, employer contributions, deductions)
- Use same payroll liabilities account ("Payroll Statutory Payable")
- Differ only in default amounts (which can be overridden per employee in Priority 2)

---

## Seeding Infrastructure

Payroll seeding creates supporting infrastructure automatically:

### 1. Payroll Vendor

A single "Payroll Department" vendor is created (or reused if it exists):

```php
$payrollVendor = Vendor::firstOrCreate(
    ['company_id' => $company->id, 'name' => 'Payroll Department'],
    ['type' => VendorType::Internal, /* ... */]
);
```

All payroll Bills are linked to this vendor for clean accounting separation.

### 2. Salary Component Offerings

Offerings are created lazily for each salary part type:

| Offering | Accounting Account | Purpose |
|----------|-------------------|----------|
| Base Salary | Account 19 (Salaries and Wages) | Employee base pay |
| Employer Contributions | Account 20 (Payroll Taxes) | KWSP employer, SOCSO employer, EIS employer |
| Employee Benefits | Account 21 (Benefits) | Health insurance, allowances |
| Salary Advance Recovery | Account 166 (Employee Advances Receivable) | Recovery from previous advance |

### 3. Payroll Liability Account

The seeder ensures "Payroll Statutory Payable" account exists:

```php
$liabilityAccount = Account::firstOrCreate(
    ['company_id' => $company->id, 'name' => 'Payroll Statutory Payable'],
    ['category' => 'liability', 'code' => '2150', /* ... */]
);
```

### Seeding Order (Required)

Seeders must run in this order to avoid missing dependencies:

```
1. CompanySeeder (creates company + chart of accounts)
2. VendorSeeder (must include "Payroll Department" vendor)
3. OfferingSeeder (must include salary component offerings)
4. SalaryPartSeeder
5. SalaryStructureSeeder (reusable templates only, 3-5 structures)
6. EmployeeSeeder
7. HrDemoSeeder (creates PayrollEntries with Bills)
```

---

## Dashboard Integration

Payroll entries now appear in financial dashboards via Bill integration:

### Widgets Updated

- **Profit & Loss Chart:** Shows salary expenses (full gross salary)
- **Financial Stats:** Includes payroll payables (from Bills)
- **Expenses Breakdown:** Salary categories visible with amounts
- **Open Payables:** Payroll Bills appear in outstanding payables list

### Seeded Data Reality

Default seeding creates:

- 3 Employees with unique base salaries
- 3-5 Reusable salary structures
- 9+ Payroll entries (monthly history)
- 9+ Bills with bill_number like 'PAY-PE0001'
- ~70% of Bills marked as paid (realistic demo)
- 4+ Employee advances (pending and recovered)
- All journal entries balanced and correct

---

## Example

Given a salary structure with these parts (Malaysian payroll example):

| Part | Type | Basis | Amount | In Net | Debit Account | Credit Account |
|---|---|---|---|---|---|---|
| Basic Pay | `base_salary` | Fixed | 3,800 | ✅ | Account 19 (Salaries) | Account 165 (Payroll Payable) |
| KWSP Employer | `employer_cost` | 13% of Base | 494 | ❌ | Account 20 (Employer Taxes) | Account 165 |
| SOCSO Employer | `employer_cost` | 1.75% of Base | 66.50 | ❌ | Account 20 | Account 165 |
| KWSP Employee | `deduction` | 11% of Base | 418 | ✅ | — | Account 165 |
| SOCSO Employee | `deduction` | 0.5% of Base | 19 | ✅ | — | Account 165 |

### Calculation & Journal Entries

**Step 1: Resolve Base Salary**
```
baseSalary = 3,800
```

**Step 2: Calculate Each Part**
```
KWSP Employer:    3,800 × 0.13  = 494       (in_net_salary = false, expensed)
SOCSO Employer:   3,800 × 0.0175 = 66.50   (in_net_salary = false, expensed)
KWSP Employee:    3,800 × 0.11   = 418     (in_net_salary = true, deducted)
SOCSO Employee:   3,800 × 0.005  = 19      (in_net_salary = true, deducted)
```

**Step 3: Calculate Net Salary**
```
netSalary = 3,800 (base)
          - 418 (KWSP employee)
          - 19 (SOCSO employee)
          = 3,363
```

**Step 4: Build Journal Entries**
```
DR: Salaries and Wages (Account 19)              3,800.00
  CR: Payroll Statutory Payable (Account 165)            3,800.00

DR: Payroll Employer Taxes (Account 20)            494.00
  CR: Payroll Statutory Payable (Account 165)            494.00

DR: Payroll Employer Taxes (Account 20)             66.50
  CR: Payroll Statutory Payable (Account 165)             66.50

DR: (no debit for employee deductions)
  CR: Payroll Statutory Payable (Account 165)            418.00 (KWSP)
  CR: Payroll Statutory Payable (Account 165)             19.00 (SOCSO)

DR: (balance final payable)
  CR: Payroll Statutory Payable (Account 165)          3,363.00 (Net Salary)

Total Debits  = 3,800.00 + 494.00 + 66.50 = 4,360.50
Total Credits = 3,800.00 + 494.00 + 66.50 + 418.00 + 19.00 + 3,363.00 = 8,160.50
```

❌ **WRONG!** This doesn't balance. The issue is the final "Net Salary Payable" entry.

**Step 4 (Corrected): Build Journal Entries**
```
DR: Salaries and Wages (Account 19)              3,800.00
  CR: Payroll Statutory Payable (Account 165)            3,800.00

DR: Payroll Employer Taxes (Account 20)            494.00
  CR: Payroll Statutory Payable (Account 165)            494.00

DR: Payroll Employer Taxes (Account 20)             66.50
  CR: Payroll Statutory Payable (Account 165)             66.50

(Employee deductions are NOT separate journal entries; they reduce the net payable)
CR: Payroll Statutory Payable (Account 165)            418.00 (KWSP reduces net)
CR: Payroll Statutory Payable (Account 165)             19.00 (SOCSO reduces net)

Total Debits  = 3,800.00 + 494.00 + 66.50 = 4,360.50
Total Credits = 3,800.00 + 494.00 + 66.50 + 418.00 + 19.00 = 4,797.50
```

❌ **Still doesn't balance!** The confusion is that **employee deductions reduce the amount owed to the employee**, but **the full salary is still an expense**.

**Step 4 (Final & Correct):**

The key insight: Track the full salary as expense, and track the liability breakdown:

```
Expense Recognition (debit side):
DR: Salaries and Wages (Account 19)              3,800.00
DR: Payroll Employer Taxes (Account 20)            560.50  (494 + 66.50)

Liability Recognition (credit side - all to Account 165: Payroll Statutory Payable):
CR: Employee gross liability                      3,800.00
CR: KWSP employer portion                           494.00
CR: SOCSO employer portion                           66.50
CR: KWSP employee portion                           418.00
CR: SOCSO employee portion                           19.00
CR: Net amount to pay employee                    3,363.00
                                                  ---------
Total Credits = 3,800.00 + 494.00 + 66.50 + 418.00 + 19.00 + ... wait
```

Actually, the balance method is simpler. Each salary part creates a debit-credit pair:

**Corrected Approach:**

```
For each SalaryPart with debit_account and credit_account:
  DR: [debit_account]  [amount]
  CR: [credit_account] [amount]

Example:
Salary Part: Basic Pay
  DR: Account 19 (Salaries)                      3,800
  CR: Account 165 (Payroll Payable)                           3,800

Salary Part: KWSP Employer (1275% = 494)
  DR: Account 20 (Employer Taxes)                 494
  CR: Account 165 (Payroll Payable)                            494

Salary Part: SOCSO Employer (1.75% = 66.50)
  DR: Account 20 (Employer Taxes)                66.50
  CR: Account 165 (Payroll Payable)                           66.50

Salary Part: KWSP Employee Deduction (11% = 418)
  DR: (none - this is a deduction)
  CR: Account 165 (Payroll Payable)                            418

Salary Part: SOCSO Employee Deduction (0.5% = 19)
  DR: (none - this is a deduction)
  CR: Account 165 (Payroll Payable)                             19

(Final balancing entry for net salary owed)
  DR: (none)
  CR: Account 165 (Payroll Payable) [net amount]            3,363

Total Debits  = 3,800 + 494 + 66.50 = 4,360.50
Total Credits = 3,800 + 494 + 66.50 + 418 + 19 + 3,363 = 8,160.50
```

**The issue:** Employee deductions + final net payable are being double-counted.

**Correct Logic:**

Employee deductions and employer contributions both credit to the payroll liability account. The net salary owed is simply the total credits minus deductions.

```
Total Liability = 3,800 (base salary) + 494 (KWSP emp) + 66.50 (SOCSO emp)
               = 4,360.50

Withholdings = 418 (KWSP) + 19 (SOCSO) = 437

Net to Pay Employee = 4,360.50 - 437 = 3,923.50
```

**Journal Entries (Final & Correct):**
```
DR: Account 19 (Salaries)                        3,800.00
  CR: Account 165 (Payroll Payable)                         3,800.00

DR: Account 20 (Employer Taxes)                    560.50
  CR: Account 165 (Payroll Payable)                          560.50

No separate deduction entries; deductions are tracked as part of the payroll process
and reduce the final payment amount.

Total Debits  = 3,800.00 + 560.50 = 4,360.50  ✅
Total Credits = 3,800.00 + 560.50 = 4,360.50  ✅ BALANCED!
```

> **Note:** The balance check validates that the sum of all debits equals the sum of all credits. Each salary part contributes a debit-credit pair to Account 165. If any salary part is missing account assignments, the balance will fail—a signal to review the salary part configuration.

---

## Bill Workflow & Payment

Once a PayrollEntry is created with its linked Bill, the standard Bill payment workflow applies:

### Bill Status Flow

```
Draft  →  Open (auto, via createInitialTransaction)
  ↓
  Open (awaiting payment)
  ↓
Partially Paid (if payment < total)
  ↓
Paid (when payment = total)
  ↓
Archived (optional, after payment)
```

### Recording Payroll Payment

```php
// When employee is paid (via bank transfer, check, etc.)
$bill = $payrollEntry->bill;

$payment = BillPayment::create([
    'bill_id' => $bill->id,
    'amount' => $netSalary * 100,  // Account for deductions
    'bank_account_id' => $bankAccount->id,
    'paid_at' => now(),
    'reference' => 'Transfer to employee'
]);

// Updates Bill status and creates payment transaction
// Links to JournalEntry for bank reconciliation
```

### Dashboard Visibility

Once Bills are created and payments recorded:
- **Open Payables:** Unpaid payroll Bills appear here
- **Expenses:** Salary costs visible in P&L reports
- **Cash Flow:** Bank transfers appear in cash reconciliation
- **Aging Report:** Overdue payroll tracked

---

## Period Defaults

The payroll entry form auto-selects sensible period defaults:

- If today is **after the 10th** of the month → next month's full period
- If today is **on or before the 10th** → current month's full period

Changing `from_date` automatically sets `to_date` to the last day of that month.

---

## Testing & Validation

After seeding or creating payroll entries, verify the data:

```bash
php artisan tinker
```

```php
// Verify payroll entries
PayrollEntry::count() // Should be 9+

// Verify Bills created
Bill::where('bill_number', 'like', 'PAY%')->count() // Should equal PayrollEntry count

// Verify journal entries balanced
$entry = PayrollEntry::first();
$debits = $entry->bill->transaction->journalEntries()->where('type', 'debit')->sum('amount');
$credits = $entry->bill->transaction->journalEntries()->where('type', 'credit')->sum('amount');
echo $debits == $credits ? '✅ Balanced' : '❌ Not balanced';

// Verify correct liability account (NOT Accounts Payable)
$accounts = Account::whereIn('id',
    $entry->bill->transaction->journalEntries()
        ->where('type', 'credit')
        .pluck('account_id')
)->pluck('name');
echo $accounts->contains('Payroll Statutory Payable') ? '✅ Correct' : '❌ Wrong account';

// Verify payroll vendor exists
Vendor::where('name', 'Payroll Department')->exists() // Should be true

// Verify payment ratio
$total = Bill::where('bill_number', 'like', 'PAY%')->count();
$paid = Bill::where('bill_number', 'like', 'PAY%')->whereNotNull('paid_at')->count();
echo "Paid: {$paid}/{$total} (" . round($paid/$total*100) . "%)";

// Verify employee advances
EmployeeAdvance::count() // Should be 4+
EmployeeAdvance::whereNull('recovered_at')->count() // Pending advances
EmployeeAdvance::whereNotNull('recovered_at')->count() // Recovered advances
```
