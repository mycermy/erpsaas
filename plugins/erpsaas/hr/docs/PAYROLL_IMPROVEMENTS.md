# Payroll Improvements Proposal

## Objective

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
   - employee salary columns (or profile table)
   - `employee_salary_revisions`
   - payroll snapshot fields

2. Backfill revisions from existing data:
   - infer current base salary from employee's commonly-used structure
   - create initial revision with an appropriate effective date

3. Keep all existing salary structures and payroll entries untouched.

4. Add integrity checks:
   - one active base salary revision per employee and date
   - no overlapping revision ranges (if using ranged model)

---

## Testing Plan

### Unit Tests

- salary resolution by effective date
- percentage calculation based on resolved base salary
- pivot override priority behavior

### Feature Tests

- payroll before and after increment date
- structure reuse across employees with different base salaries
- one-off override behavior and audit fields

### Regression Tests

- existing payroll entry creation still balanced
- journal totals unchanged for legacy scenarios

---

## Acceptance Criteria

1. Same salary structure can be used by many employees with different base salaries.
2. Yearly increments are modeled via effective-dated revisions.
3. Payroll entries are historically stable through snapshot fields.
4. Journal entries remain balanced and category mappings remain correct.
5. Existing records continue to work during phased rollout.

---

## Suggested Implementation Sequence

1. Add schema + models for salary revisions and snapshots.
2. Implement salary resolver service.
3. Update payroll processor to use resolver with fallback.
4. Add employee salary revision UI.
5. Add migration/backfill command.
6. Update docs and seeders to match new approach.

---

## Seeder Guidance (Interim)

Until full redesign lands:

- continue using a small number of reusable structures by department/grade
- avoid one structure per employee where possible
- for demo data, choose realistic default base salary templates

After redesign:

- seed one structure per department/grade
- seed employee-specific salary revisions separately

---

## Conclusion

The best long-term model is:

- SalaryPart = accounting/formula building block
- SalaryStructure = reusable template
- EmployeeSalaryRevision = personal salary timeline
- PayrollEntry = immutable execution snapshot

This supports real HR operations (increments and unique salary) without sacrificing template reuse or accounting integrity.
