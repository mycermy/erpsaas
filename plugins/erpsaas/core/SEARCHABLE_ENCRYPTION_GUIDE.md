# PDPA-Compliant Searchable Encryption Implementation

## Overview

This implementation provides **Malaysia PDPA-compliant** encryption for sensitive personal data (NRIC, bank accounts, salary) with searchable capabilities using **blind indexing**.

## 🔐 Security Architecture

### How It Works

1. **Primary Encrypted Field**: Stores actual sensitive data encrypted using Laravel's `encrypted` cast
2. **Blind Index Field**: Stores a salted hash (HMAC-SHA256) for searching without decryption
3. **Automatic Index Generation**: Indexes are automatically created/updated when model is saved

```
Plain Text NRIC: "901234-56-7890"
     ↓
Encrypted Field (nric): "eyJpdiI6IlR..." (AES-256-CBC encrypted)
     +
Blind Index (nric_index): "a3f5c9..." (HMAC-SHA256 hash)
```

### Why Blind Indexing?

❌ **Without Blind Indexing**: Must decrypt entire table to search
✅ **With Blind Indexing**: Search using hash, only decrypt matching records

## 📋 Malaysia PDPA Requirements Addressed

### Seven Principles of PDPA (Sections 5-11)

| PDPA Principle | Section | Implementation in This System |
|----------------|---------|-------------------------------|
| **1. General Principle** | Section 5 | Data processed lawfully, fairly, with consent |
| **2. Notice & Choice** | Section 7 | Privacy policy + consent forms |
| **3. Disclosure** | Section 6 | Data only shared with authorized parties |
| **4. Security** | Section 8 | ✅ AES-256-CBC encryption at rest |
| **5. Retention** | Section 9 | ✅ Timestamps for lifecycle management |
| **6. Data Integrity** | Section 10 | ✅ Hash verification methods |
| **7. Access Principle** | Section 11 | ✅ Audit logs + access controls |

### Technical Implementation Mapping

| Requirement | Implementation | Code Location |
|-------------|----------------|---------------|
| **Encryption at Rest** | AES-256-CBC (Laravel encrypted cast) | Model casts |
| **Secure Search** | HMAC-SHA256 blind indexing | SearchableEncryption trait |
| **Data Minimization** | Masked accessors for display | `getMaskedNricAttribute()` |
| **Access Audit Trail** | Automatic logging on read | `logSensitiveDataAccess()` |
| **Retention Tracking** | Last accessed timestamps | `sensitive_data_last_accessed_at` |
| **Data Verification** | Hash comparison | `verifyEncryptedField()` |
| **Right to Erasure** | Soft deletes + secure purging | Model soft deletes |
| **Key Management** | Environment variables | `.env` file |

### Sensitive Personal Data Under PDPA

**High Priority (Must Encrypt):**
- ✅ NRIC / MyKad number (Malaysian IC)
- ✅ Passport number
- ✅ Bank account numbers
- ✅ Credit card information
- ✅ Salary/income information
- ✅ Health records / medical data
- ✅ Criminal history
- ✅ Biometric data (fingerprints, facial recognition)

**Medium Priority (Consider Encrypting):**
- ⚠️ Phone numbers
- ⚠️ Home addresses
- ⚠️ Date of birth
- ⚠️ Marital status
- ⚠️ Family information

**Lower Priority (Usually Not Encrypted):**
- ℹ️ Name (needed for queries)
- ℹ️ Email (authentication)
- ℹ️ Company name
- ℹ️ Job title

### PDPA Penalties for Non-Compliance

**Administrative Penalties:**
- First offense: Up to RM 100,000
- Repeat offense: Up to RM 500,000
- Criminal conviction: Up to RM 300,000 and/or 2 years imprisonment

**Data Breach Notification:**
- Must notify affected individuals within reasonable time
- Must notify Commissioner within 72 hours (if severe)
- Failure to notify: Additional penalties

## 🚀 Setup Instructions

### Step 1: Configure Salt in .env

Add to your `.env` file:

```env
# Optional: Additional salt for blind indexing
# If not set, APP_KEY will be used
BLIND_INDEX_SALT=your-secret-salt-here-change-this
```

⚠️ **CRITICAL**: Once set, DO NOT change this salt unless you regenerate all blind indexes!

### Step 2: Add to config/app.php

```php
return [
    // ... existing config
    
    /*
    |--------------------------------------------------------------------------
    | Blind Index Salt
    |--------------------------------------------------------------------------
    |
    | This salt is used for generating blind indexes for searchable encryption.
    | NEVER change this value after data has been encrypted, or you'll lose
    | the ability to search existing records.
    |
    */
    'blind_index_salt' => env('BLIND_INDEX_SALT', env('APP_KEY')),
];
```

### Step 3: Run Migration

```bash
php artisan migrate
```

### Step 4: Apply Concern to Your Model

```php
<?php

namespace App\Models;

use Erpsaas\Core\Concerns\SearchableEncryption;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use SearchableEncryption;

    // Define searchable encrypted fields
    protected array $searchableEncrypted = [
        'nric',
        'bank_account_number',
        'salary',
    ];

    // Cast to encrypted
    protected function casts(): array
    {
        return [
            'nric' => 'encrypted',
            'bank_account_number' => 'encrypted',
            'salary' => 'encrypted:decimal:2',
        ];
    }

    // Hide from JSON/Array output
    protected $hidden = [
        'nric',
        'nric_index',
        'bank_account_number',
        'bank_account_index',
        'salary',
        'salary_index',
    ];
}
```

## 💻 Usage Examples

### Creating Records

```php
use App\Models\User;

// Automatic blind index generation on save
$user = User::create([
    'name' => 'Ahmad bin Abdullah',
    'email' => 'ahmad@example.com',
    'nric' => '901234-56-7890',
    'bank_account_number' => '1234567890',
    'bank_name' => 'Maybank',
    'salary' => 5000.00,
]);

// The nric_index, bank_account_index, salary_index are automatically generated!
```

### Searching Encrypted Data

```php
// Search by NRIC (uses blind index - very fast!)
$user = User::searchByEncrypted('nric', '901234-56-7890')->first();

// Search by bank account
$users = User::searchByEncrypted('bank_account_number', '1234567890')->get();

// Search multiple values (OR condition)
$users = User::searchByEncryptedIn('nric', [
    '901234-56-7890',
    '850101-12-3456',
])->get();

// Combine with other queries
$user = User::where('email', 'ahmad@example.com')
    ->searchByEncrypted('nric', '901234-56-7890')
    ->first();
```

### Accessing Encrypted Data

```php
// Automatic decryption when accessing
$nric = $user->nric; // "901234-56-7890" (decrypted)

// Verify without decrypting entire field
if ($user->verifyEncryptedField('nric', '901234-56-7890')) {
    echo "NRIC matches!";
}
```

### Display Masked Data (PDPA Data Minimization)

```php
// Show masked version to non-authorized users
echo $user->masked_nric; // "90****-**-***0"
echo $user->masked_bank_account; // "******7890"
echo $user->masked_salary; // "RM 5,000 - 5,999"
```

### Regenerating Blind Indexes

```php
// Single record (after salt rotation or data corruption)
$user->regenerateBlindIndexes();

// All records (in a command or seeder)
User::regenerateAllBlindIndexes();

// With progress bar (in Artisan command)
$this->withProgressBar(
    User::count(),
    fn() => User::regenerateAllBlindIndexes(100)
);
```

## 🛡️ Security Best Practices

### PDPA Malaysia Compliance Requirements

#### 1. Secure Key Management ⚠️ CRITICAL

Your `APP_KEY` in `.env` is the **ONLY** key used for encryption:

```bash
# .env file (NEVER commit to Git!)
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxx
BLIND_INDEX_SALT=your-secret-salt-here
```

**Critical Rules:**
- ✅ **DO**: Keep `.env` in `.gitignore` (already done in Laravel)
- ✅ **DO**: Backup your `APP_KEY` securely (password manager, encrypted vault)
- ✅ **DO**: Use different keys for dev/staging/production
- ✅ **DO**: Restrict access to `.env` file (chmod 600)
- ❌ **DON'T**: Commit `.env` or keys to version control
- ❌ **DON'T**: Share keys via email, Slack, or unencrypted channels
- ❌ **DON'T**: Store keys in code or config files

**⚠️ WARNING**: If `APP_KEY` is lost, **encrypted data CANNOT be recovered**!

```bash
# Secure backup strategy
cp .env .env.backup.$(date +%Y%m%d)
gpg --encrypt .env.backup.20260505
```

#### 2. Searchable Hashing (Blind Indexing)

This implementation uses **HMAC-SHA256** for blind indexing (NOT bcrypt):

```php
// Our implementation - Fast, deterministic hashing
$blindIndex = hash_hmac('sha256', $value, $salt);

// ❌ DON'T use Hash::make() - too slow, non-deterministic
$hash = Hash::make($value); // Different hash each time!
```

**Why HMAC over Hash::make()?**
- ✅ Deterministic - same input = same hash (required for searching)
- ✅ Fast - thousands of hashes per second
- ✅ Salted - prevents rainbow table attacks
- ❌ Hash::make() uses bcrypt - designed to be SLOW (password hashing)
- ❌ Hash::make() is non-deterministic - can't use for searching

#### 3. Selective Encryption Strategy

**Only encrypt high-risk PII** to balance security and performance:

| Data Type | Encrypt? | Reason |
|-----------|----------|--------|
| NRIC | ✅ YES | Direct identifier under PDPA |
| Bank Account | ✅ YES | Financial data - high risk |
| Salary | ✅ YES | Sensitive employment data |
| Passport Number | ✅ YES | Government ID |
| Credit Card | ✅ YES | Payment card industry data |
| Name | ❌ NO | Often needed for queries/display |
| Email | ❌ NO | Used for authentication |
| Phone | ⚠️ MAYBE | Consider masking instead |
| Address | ⚠️ MAYBE | Based on risk assessment |

**Performance Impact:**
```php
// Encrypt selectively - good performance
protected function casts(): array
{
    return [
        'nric' => 'encrypted',           // Must encrypt
        'salary' => 'encrypted',         // Must encrypt
        'name' => 'string',              // Don't encrypt
        'email' => 'string',             // Don't encrypt
    ];
}
```

#### 4. Key Rotation Process

Rotate encryption keys **annually** or after security incidents:

**Step 1: Create Rotation Command**

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class RotateEncryptionKeys extends Command
{
    protected $signature = 'security:rotate-keys {--old-key=}';
    protected $description = 'Rotate encryption keys and re-encrypt data';

    public function handle()
    {
        $oldKey = $this->option('old-key') ?: config('app.key');
        
        if (!$this->confirm('This will re-encrypt all data. Backup database first. Continue?')) {
            return;
        }

        $this->info('Starting key rotation...');
        
        DB::transaction(function () use ($oldKey) {
            User::chunk(100, function ($users) use ($oldKey) {
                foreach ($users as $user) {
                    // Decrypt with old key
                    config(['app.key' => $oldKey]);
                    $decryptedNric = $user->nric;
                    $decryptedSalary = $user->salary;
                    $decryptedBank = $user->bank_account_number;
                    
                    // Re-encrypt with new key
                    config(['app.key' => env('APP_KEY')]);
                    $user->nric = $decryptedNric;
                    $user->salary = $decryptedSalary;
                    $user->bank_account_number = $decryptedBank;
                    $user->saveQuietly();
                    
                    // Regenerate blind indexes
                    $user->regenerateBlindIndexes();
                }
                
                $this->info('Processed ' . $users->count() . ' users');
            });
        });
        
        $this->info('✓ Key rotation complete!');
    }
}
```

**Step 2: Execute Rotation (with backup!)**

```bash
# 1. Backup database
php artisan backup:run --only-db

# 2. Generate new key (don't overwrite yet!)
php artisan key:generate --show
# Output: base64:NEW_KEY_HERE

# 3. Run rotation with old key
php artisan core:rotate-keys --old-key="base64:OLD_KEY_HERE"

# 4. Update .env with new key
# APP_KEY=base64:NEW_KEY_HERE

# 5. Restart application
php artisan config:cache
php artisan queue:restart
```

**Step 3: Verify Rotation**

```php
// Test decryption works
$user = User::first();
$nric = $user->nric; // Should decrypt successfully

// Test searching works
$found = User::searchByEncrypted('nric', '901234-56-7890')->first();
expect($found)->not->toBeNull();
```

### 1. Salt Management

```php
// ✅ DO: Use environment variable
'blind_index_salt' => env('BLIND_INDEX_SALT', env('APP_KEY')),

// ❌ DON'T: Hard-code salt
'blind_index_salt' => 'my-secret-salt',
```

### 2. Index Column Security

```php
// ✅ DO: Hide index columns from output
protected $hidden = [
    'nric',
    'nric_index', // Hide the index too!
];

// ❌ DON'T: Expose index columns
```

### 3. Access Control

```php
// ✅ DO: Check authorization before accessing
public function show(User $user)
{
    $this->authorize('view-sensitive-data', $user);
    return $user->nric;
}

// ❌ DON'T: Expose sensitive data without checks
```

### 4. API Responses

```php
// ✅ DO: Use resource classes with conditional fields
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nric' => $this->when(
                $request->user()->can('view-sensitive-data'),
                $this->masked_nric // Or full NRIC if authorized
            ),
        ];
    }
}

// ❌ DON'T: Return full models with sensitive data
```

## 📊 PDPA Compliance Monitoring

### Audit Trail Query Examples

```php
// Who accessed sensitive data recently?
$logs = DB::table('sensitive_data_access_logs')
    ->where('field_name', 'nric')
    ->where('created_at', '>=', now()->subDays(30))
    ->get();

// Users with never-accessed sensitive data (can be purged)
$staleUsers = User::neverAccessed()
    ->where('created_at', '<', now()->subYears(2))
    ->get();

// Recently accessed records (for compliance reports)
$activeUsers = User::recentlyAccessed(days: 90)->get();
```

### Creating Compliance Reports

```php
// Generate access report for PDPA audits
$report = DB::table('sensitive_data_access_logs')
    ->select('field_name', DB::raw('COUNT(*) as access_count'))
    ->where('created_at', '>=', now()->subMonth())
    ->groupBy('field_name')
    ->get();
```

## 🔄 Migration and Maintenance

### Rotating Encryption Keys

```bash
# 1. Generate new APP_KEY
php artisan key:generate --show

# 2. Create rotation command
php artisan make:command RotateEncryptionKeys

# 3. In command: decrypt with old key, encrypt with new key
# 4. Update .env with new key
# 5. Regenerate blind indexes
php artisan app:regenerate-blind-indexes
```

### Creating Regeneration Command

```php
<?php

namespace Erpsaas\Core\Console\Commands;

use Erpsaas\Core\Models\User;
use Illuminate\Console\Command;

class RegenerateBlindIndexes extends Command
{
    protected $signature = 'core:regenerate-blind-indexes';
    protected $description = 'Regenerate all blind indexes for encrypted fields';

    public function handle()
    {
        $this->info('Regenerating blind indexes...');
        
        $count = User::regenerateAllBlindIndexes(100);
        
        $this->info("Successfully regenerated {$count} records.");
    }
}
```

## 🧪 Testing

```php
use App\Models\User;

it('can search encrypted NRIC', function () {
    $user = User::factory()->create([
        'nric' => '901234-56-7890',
    ]);
    
    $found = User::searchByEncrypted('nric', '901234-56-7890')->first();
    
    expect($found->id)->toBe($user->id);
});

it('generates blind index automatically', function () {
    $user = User::factory()->create([
        'nric' => '901234-56-7890',
    ]);
    
    expect($user->nric_index)->not->toBeNull();
    expect($user->nric_index)->toHaveLength(64); // SHA256 hex
});

it('verifies encrypted field without decryption', function () {
    $user = User::factory()->create([
        'nric' => '901234-56-7890',
    ]);
    
    expect($user->verifyEncryptedField('nric', '901234-56-7890'))->toBeTrue();
    expect($user->verifyEncryptedField('nric', 'wrong-nric'))->toBeFalse();
});

it('masks sensitive data for display', function () {
    $user = User::factory()->create([
        'nric' => '901234-56-7890',
    ]);
    
    expect($user->masked_nric)->toBe('90****-**-***0');
});
```

## 🚨 Troubleshooting

### Problem: Can't find records after salt change

**Solution**: Regenerate blind indexes

```bash
php artisan tinker
>>> User::regenerateAllBlindIndexes();
```

### Problem: "Payload is invalid" error

**Cause**: APP_KEY has changed or data was encrypted with different key

**Solution**: Restore original APP_KEY or decrypt/re-encrypt data

### Problem: Slow searches on encrypted fields

**Solution**: Ensure index columns have database indexes

```php
$table->string('nric_index', 64)->index(); // ← index() is crucial!
```

## 📚 Additional Resources

- [Malaysia PDPA Official Site](https://www.pdp.gov.my/)
- [Laravel Encryption Documentation](https://laravel.com/docs/encryption)
- [HMAC-SHA256 Explanation](https://en.wikipedia.org/wiki/HMAC)
- [Blind Indexing Best Practices](https://paragonie.com/blog/2017/05/building-searchable-encrypted-databases-with-php-and-sql)

## ✅ PDPA Malaysia Compliance Checklist

### Technical Security (Implemented in This System)

- [x] **Encryption at Rest**: AES-256-CBC for NRIC, bank accounts, salary
- [x] **Searchable Encryption**: Blind indexing (HMAC-SHA256) for queries
- [x] **Access Audit Logging**: Every read/write tracked with user ID, IP, timestamp
- [x] **Data Masking**: Display masked versions for non-authorized users
- [x] **Retention Tracking**: Last accessed timestamps for lifecycle management
- [x] **Secure Key Management**: Environment variables, never in version control
- [x] **Data Integrity**: Hash verification without full decryption
- [x] **Selective Encryption**: Only high-risk PII encrypted (performance optimized)
- [x] **Key Rotation Support**: Commands for periodic key rotation

### Organizational Policies (Your Responsibility)

- [ ] **Privacy Policy**: Document data collection, usage, storage practices
- [ ] **Consent Management**: Obtain explicit, informed consent before collection
- [ ] **Data Retention Policy**: Define retention periods (e.g., 7 years for employment)
- [ ] **Incident Response Plan**: Procedures for data breach notification (72 hours)
- [ ] **Staff Training**: Educate employees on PDPA requirements annually
- [ ] **Data Protection Officer (DPO)**: Appoint if processing large volumes
- [ ] **Regular Security Audits**: Quarterly review of access logs and controls
- [ ] **Vendor Management**: Ensure third-party processors are PDPA compliant
- [ ] **Data Processing Agreement**: Contracts with processors specifying obligations
- [ ] **Cross-Border Transfer**: Ensure adequate protection for data sent overseas

### Application Security Hardening

- [ ] **HTTPS Enforcement**: Force SSL/TLS in production (web server config)
- [ ] **Database Encryption**: Enable encryption at rest in MySQL/PostgreSQL
- [ ] **Backup Encryption**: Encrypt database backups
- [ ] **Access Controls**: Role-based permissions for sensitive data
- [ ] **Two-Factor Authentication**: For users accessing sensitive data
- [ ] **IP Whitelisting**: Restrict admin panel to office IPs
- [ ] **Rate Limiting**: Prevent brute force attacks on search APIs
- [ ] **Security Headers**: CSP, HSTS, X-Frame-Options configured
- [ ] **Dependency Updates**: Regular updates for security patches
- [ ] **Penetration Testing**: Annual third-party security assessment

### Monitoring & Compliance

- [ ] **Access Monitoring**: Alert on unusual access patterns
- [ ] **Retention Compliance**: Automated deletion of expired data
- [ ] **Compliance Reporting**: Quarterly reports for management
- [ ] **Data Subject Requests**: Process for access, correction, deletion requests
- [ ] **Breach Detection**: Monitoring for unauthorized access attempts
- [ ] **Audit Trail Review**: Monthly review of sensitive data access logs

### Documentation Required

- [ ] **Data Flow Diagrams**: Map how personal data moves through system
- [ ] **Risk Assessment**: Document risks and mitigation strategies
- [ ] **Security Policies**: Written policies for data handling
- [ ] **Training Records**: Evidence of staff PDPA training
- [ ] **Consent Forms**: Templates and records of user consent
- [ ] **Incident Response Procedures**: Step-by-step breach response plan

---

## 📞 PDPA Resources & Support

### Official Malaysia PDPA Contacts

- **Official Website**: [www.pdp.gov.my](https://www.pdp.gov.my)
- **Complaint Hotline**: 03-7456 3888 (Pusat Panggilan JPDP)
- **MyGCC**: 03-8000 8000
- **Email**: aduan@pdp.gov.my
- **Address**: Aras 8, Galeria PjH, Presint 4, Putrajaya

### Registration Requirements

**Who Must Register:**
- Organizations processing personal data of living individuals
- Entities with operations in Malaysia
- Exemptions: Individual, domestic processing

**Registration Fee**: RM 1,000 - RM 50,000 (based on annual turnover)

**How to Register**: [https://daftar.pdp.gov.my/p_register](https://daftar.pdp.gov.my/p_register)

### Useful Resources

- [PDPA Guidelines](https://www.pdp.gov.my/ppdpv1/panduan/) - Official guidelines
- [FAQ](https://www.pdp.gov.my/ppdpv1/soalan-lazim/) - Common questions
- [Data Breach Notification](https://www.pdp.gov.my/ppdpv1/lapor-dbn/) - Report breaches
- [Lodge Complaint](https://daftar.pdp.gov.my/p_aduan) - File PDPA complaint

---

**⚠️ Legal Disclaimer**: 

This implementation provides **technical security measures** for PDPA compliance. However, full compliance requires:

1. **Legal Framework**: Privacy policies, terms of service, consent forms
2. **Organizational Policies**: Data retention, incident response, staff training
3. **Governance**: Data Protection Officer, regular audits, documentation
4. **Vendor Management**: Third-party processor agreements
5. **Ongoing Monitoring**: Access reviews, security assessments, compliance reports

**Consult with legal professionals specializing in Malaysian data protection law for complete PDPA compliance.**

This code is provided "as-is" for educational purposes. The authors are not responsible for legal consequences of improper implementation or non-compliance with PDPA regulations.
