# Payroll Processing

This document explains how `PayrollEntry::createWithTransaction()` calculates pay, builds journal entries, validates balance, and persists the result.

---

## Workflow

```
User submits Payroll Entry form
        │
        ▼
PayrollEntry::createWithTransaction($data)
        │
        ├── 1. Load SalaryStructure + its SalaryParts (via spss pivot)
        ├── 2. Identify Base Salary amount
        ├── 3. Loop each SalaryPart → resolve amount (fixed or % of base)
        │         ├── Accumulate netSalary (add/subtract based on type)
        │         └── Build journal entry pairs (debit + credit per account)
        ├── 4. Append Net Salary Payable credit entry
        ├── 5. Assert debits === credits (throw Exception if not)
        ├── 6. Create PayrollEntry record
        ├── 7. Create Transaction record (type = journal)
        ├── 8. Create JournalEntry rows (one per journal entry line)
        └── 9. Link transaction_id back to PayrollEntry → save
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
    'account_id' => $salaryStructure->payrollLiabilitiesAccount->id,
    'type'       => 'credit',
    'amount'     => $netSalary * 100,
    'description'=> 'Net Salary Payable',
];
```

This final credit entry records the liability to pay the employee. It uses the payroll liabilities account configured on the salary structure.

### 6. Balance Check

```php
$totalDebit  = collect($journalEntries)->where('type', 'debit')->sum('amount');
$totalCredit = collect($journalEntries)->where('type', 'credit')->sum('amount');

if ($totalDebit !== $totalCredit) {
    throw new \Exception("Journal entries do not balance. Debit = $totalDebit, Credit = $totalCredit");
}
```

If the entries do not balance, an exception is thrown **before** any record is persisted. This protects ledger integrity. The UI will surface this as a validation error.

### 7. Persist

```php
$payrollEntry = self::create($data);

$transaction = Transaction::create([
    'transactionable_type' => self::class,
    'transactionable_id'   => $payrollEntry->id,
    'type'                 => 'journal',
    'amount'               => $totalDebit,
    'posted_at'            => now(),
    'description'          => "Payroll for {$firstName} {$lastName} for period {$from} to {$to}",
]);

foreach ($journalEntries as $entry) {
    $entry['transaction_id'] = $transaction->id;
    $transaction->journalEntries()->create($entry);
}

$payrollEntry->transaction_id = $transaction->id;
$payrollEntry->save();
```

The `Transaction` is polymorphically related (`transactionable_type = PayrollEntry::class`) so it appears correctly in the accounting transaction list alongside invoices and bills.

---

## Example

Given a salary structure with these parts:

| Part | Type | Basis | Amount | In Net | Debit | Credit |
|---|---|---|---|---|---|---|
| Basic Pay | `base_salary` | Fixed | 5,000 | ✅ | Salary Expense | Salaries Payable |
| Social Security | `employer_cost` | % of Base | 10% | ❌ | SS Expense | SS Payable |
| Income Tax | `deduction` | % of Base | 15% | ✅ | — | Tax Payable |
| Transport | `addition` | Fixed | 200 | ✅ | Transport Expense | Transport Payable |

Calculations:

```
baseSalary  = 5,000
netSalary   = 5,000 (base) - 750 (tax 15%) + 200 (transport) = 4,450

Journal entries:
  DR Salary Expense      5,000
  CR Salaries Payable    5,000
  DR SS Expense            500
  CR SS Payable            500
  CR Tax Payable           750
  DR Transport Expense     200
  CR Transport Payable     200
  CR Payroll Liabilities 4,450   ← Net Salary Payable

Total Debits  = 5,000 + 500 + 200 = 5,700
Total Credits = 5,000 + 500 + 750 + 200 + 4,450 = ...
```

> **Note:** The balance check validates that the sum of all debits equals the sum of all credits across all journal lines. If any salary part is missing an account assignment it simply produces no journal entry lines for that part, which may cause the balance check to fail — a signal to review the salary part configuration.

---

## Period Defaults

The payroll entry form auto-selects sensible period defaults:

- If today is **after the 10th** of the month → next month's full period
- If today is **on or before the 10th** → current month's full period

Changing `from_date` automatically sets `to_date` to the last day of that month.
