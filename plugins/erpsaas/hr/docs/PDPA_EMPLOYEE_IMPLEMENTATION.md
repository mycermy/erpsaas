# HR Plugin - PDPA Employee Data Implementation

## 🎯 Overview

This guide shows how to implement PDPA-compliant encryption for employee data in the HR plugin using the core `SearchableEncryption` concern.

## 📋 Sensitive Data Fields Added

The migration adds these encrypted fields to the `employees` table:

### Malaysian-Specific Fields (PDPA High Priority)
| Field | Type | Description | PDPA Classification |
|-------|------|-------------|---------------------|
| `nric` | encrypted | Malaysian IC number (MyKad) | **Highly Sensitive** |
| `passport_number` | encrypted | Passport number (foreign workers) | **Highly Sensitive** |
| `epf_number` | encrypted | EPF (KWSP) number | **Highly Sensitive** |
| `socso_number` | encrypted | SOCSO (PERKESO) number | **Highly Sensitive** |
| `income_tax_number` | encrypted | LHDN tax number | **Highly Sensitive** |

### Financial Information (PDPA Sensitive)
| Field | Type | Description |
|-------|------|-------------|
| `bank_account_number` | encrypted | Bank account number |
| `bank_name` | string | Bank name (not encrypted) |
| `bank_branch` | string | Bank branch (not encrypted) |
| `encrypted_base_salary` | encrypted:decimal | Encrypted salary amount |

### Emergency Contact
| Field | Type | Description |
|-------|------|-------------|
| `emergency_contact_name` | string | Contact name |
| `emergency_contact_phone` | encrypted | Phone number (encrypted) |
| `emergency_contact_relationship` | string | Relationship |

### PDPA Audit Fields
| Field | Type | Description |
|-------|------|-------------|
| `sensitive_data_last_accessed_at` | timestamp | Last access time |
| `sensitive_data_accessed_by` | foreign_id | User who accessed |

## 🚀 Quick Implementation

### Step 1: Run Migration

```bash
cd /Users/zrm/Documents/GitHub/erpsaas
php artisan migrate --path=plugins/erpsaas/hr/database/migrations
```

### Step 2: Update Your Employee Model

```php
<?php

namespace Erpsaas\Hr\Models;

use Erpsaas\Core\Concerns\SearchableEncryption;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use SearchableEncryption;

    // Define searchable encrypted fields
    protected array $searchableEncrypted = [
        'nric',
        'passport_number',
        'bank_account_number',
        'encrypted_base_salary',
        'epf_number',
        'socso_number',
        'income_tax_number',
        'emergency_contact_phone',
    ];

    // Cast to encrypted
    protected function casts(): array
    {
        return [
            'nric' => 'encrypted',
            'passport_number' => 'encrypted',
            'bank_account_number' => 'encrypted',
            'encrypted_base_salary' => 'encrypted:decimal:2',
            'epf_number' => 'encrypted',
            'socso_number' => 'encrypted',
            'income_tax_number' => 'encrypted',
            'emergency_contact_phone' => 'encrypted',
        ];
    }

    // Hide from JSON responses
    protected $hidden = [
        'nric', 'nric_index',
        'passport_number', 'passport_index',
        'bank_account_number', 'bank_account_index',
        'encrypted_base_salary', 'salary_index',
        'epf_number', 'epf_index',
        'socso_number', 'socso_index',
        'income_tax_number', 'tax_number_index',
        'emergency_contact_phone', 'emergency_contact_phone_index',
    ];
}
```

### Step 3: Register Model in Config

Add to `config/searchable-encryption.php`:

```php
'models' => [
    'Employee' => \Erpsaas\Hr\Models\Employee::class,
],
```

## 💻 Usage Examples

### Creating Employee with Encrypted Data

```php
use Erpsaas\Hr\Models\Employee;

$employee = Employee::create([
    'company_id' => 1,
    'employee_number' => 'EMP001',
    'job_title' => 'Software Engineer',
    'department' => 'IT',
    
    // Sensitive data - automatically encrypted
    'nric' => '901234-56-7890',
    'bank_account_number' => '1234567890',
    'bank_name' => 'Maybank',
    'encrypted_base_salary' => 5000.00,
    'epf_number' => '12345678',
    'socso_number' => 'A1234567',
    'income_tax_number' => 'SG1234567890',
    'emergency_contact_phone' => '0123456789',
]);

// Blind indexes are automatically generated!
```

### Searching by Encrypted Fields

```php
// Search by NRIC
$employee = Employee::searchByEncrypted('nric', '901234-56-7890')->first();

// Search by EPF number
$employee = Employee::searchByEncrypted('epf_number', '12345678')->first();

// Search by bank account
$employee = Employee::searchByEncrypted('bank_account_number', '1234567890')->first();

// Multiple values
$employees = Employee::searchByEncryptedIn('nric', [
    '901234-56-7890',
    '850101-12-3456',
])->get();
```

### Displaying Masked Data

```php
// Full data (requires authorization)
$nric = $employee->nric; // "901234-56-7890"

// Masked for display (safe for non-admin users)
echo $employee->masked_nric;              // "90****-**-***0"
echo $employee->masked_bank_account;      // "******7890"
echo $employee->masked_salary;            // "RM 5,000 - 5,999"
echo $employee->masked_epf;               // "12****78"
echo $employee->masked_emergency_phone;   // "012****789"
```

### Authorization Example (Filament)

```php
// In EmployeeResource.php
use Filament\Tables\Columns\TextColumn;

public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('employee_number'),
            TextColumn::make('job_title'),
            
            // Show masked NRIC to all, full to admins only
            TextColumn::make('masked_nric')
                ->label('NRIC')
                ->visible(fn() => !auth()->user()->can('view-full-nric')),
            
            TextColumn::make('nric')
                ->label('NRIC')
                ->visible(fn() => auth()->user()->can('view-full-nric')),
            
            // Same for salary
            TextColumn::make('masked_salary')
                ->label('Salary')
                ->visible(fn() => !auth()->user()->can('view-salary')),
        ]);
}
```

## 🔍 Compliance Queries

### Access Audit Reports

```php
// Who accessed employee data recently?
$accessLogs = DB::table('sensitive_data_access_logs')
    ->where('model_type', 'Erpsaas\\Hr\\Models\\Employee')
    ->where('created_at', '>=', now()->subMonth())
    ->join('users', 'sensitive_data_access_logs.accessed_by_user_id', '=', 'users.id')
    ->select('users.name', 'field_name', 'created_at', 'ip_address')
    ->orderByDesc('created_at')
    ->get();

// Employees with never-accessed sensitive data
$staleEmployees = Employee::neverAccessed()
    ->where('created_at', '<', now()->subYears(2))
    ->get();

// Recently accessed employees
$activeEmployees = Employee::recentlyAccessed(days: 90)->get();
```

### PDPA Retention Policy

```php
// Employees eligible for data purge (7 years after termination)
$forPurge = Employee::onlyTrashed()
    ->where('deleted_at', '<', now()->subYears(7))
    ->get();

foreach ($forPurge as $employee) {
    // Purge sensitive data
    $employee->nric = null;
    $employee->bank_account_number = null;
    $employee->encrypted_base_salary = null;
    // ... other fields
    $employee->save();
    
    // Or permanently delete
    $employee->forceDelete();
}
```

## 🧪 Testing

```php
use Erpsaas\Hr\Models\Employee;

it('encrypts employee NRIC', function () {
    $employee = Employee::factory()->create([
        'nric' => '901234-56-7890',
    ]);
    
    // Check it's encrypted in database
    $raw = DB::table('employees')->find($employee->id);
    expect($raw->nric)->not->toBe('901234-56-7890');
    expect($raw->nric)->toContain('eyJ'); // Base64 format
    
    // But model returns decrypted
    expect($employee->nric)->toBe('901234-56-7890');
});

it('can search by encrypted NRIC', function () {
    $employee = Employee::factory()->create([
        'nric' => '901234-56-7890',
    ]);
    
    $found = Employee::searchByEncrypted('nric', '901234-56-7890')->first();
    
    expect($found->id)->toBe($employee->id);
});

it('masks NRIC for display', function () {
    $employee = Employee::factory()->create([
        'nric' => '901234-56-7890',
    ]);
    
    expect($employee->masked_nric)->toBe('90****-**-***0');
});

it('logs sensitive data access', function () {
    $employee = Employee::factory()->create([
        'nric' => '901234-56-7890',
    ]);
    
    $this->actingAs($user = User::factory()->create());
    
    // Access NRIC (triggers logging)
    $nric = $employee->nric;
    
    // Check log entry exists
    $this->assertDatabaseHas('sensitive_data_access_logs', [
        'model_type' => 'Erpsaas\\Hr\\Models\\Employee',
        'model_id' => $employee->id,
        'field_name' => 'nric',
        'accessed_by_user_id' => $user->id,
    ]);
});
```

## ⚠️ Migration Notes

### Existing Data Migration

If you have existing employee data, you need to:

1. **Backup database first!**
2. Migrate existing `base_salary_amount` to `encrypted_base_salary`:

```php
// In a seeder or migration
Employee::chunk(100, function ($employees) {
    foreach ($employees as $employee) {
        if ($employee->base_salary_amount) {
            $employee->encrypted_base_salary = $employee->base_salary_amount;
            $employee->save(); // Blind indexes auto-generated
        }
    }
});
```

### Deprecation Plan

Consider deprecating unencrypted `base_salary_amount` field:

```php
// Add to Employee model
protected function baseSalaryAmount(): Attribute
{
    return Attribute::make(
        get: fn($value) => $this->encrypted_base_salary ?? $value,
        set: fn($value) => ['encrypted_base_salary' => $value],
    );
}
```

## 📞 Malaysian Compliance Resources

- **PDPA Malaysia**: [www.pdp.gov.my](https://www.pdp.gov.my)
- **EPF (KWSP)**: [www.kwsp.gov.my](https://www.kwsp.gov.my)
- **SOCSO (PERKESO)**: [www.perkeso.gov.my](https://www.perkeso.gov.my)
- **LHDN (Inland Revenue)**: [www.hasil.gov.my](https://www.hasil.gov.my)

## ✅ Compliance Checklist

- [x] NRIC encrypted at rest
- [x] Bank account encrypted
- [x] Salary encrypted
- [x] EPF/SOCSO numbers encrypted
- [x] Searchable via blind indexes
- [x] Audit logging enabled
- [x] Data masking for display
- [x] Hidden from JSON responses
- [x] Retention policy support
- [x] Right to erasure (soft deletes)

---

**Version**: 1.0.0  
**Last Updated**: May 5, 2026  
**Compliance**: Malaysia PDPA 2010 (Act 709)
