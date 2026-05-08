# PDPA Searchable Encryption - Core Plugin

This package provides **Malaysia PDPA-compliant** encryption with searchable capabilities for sensitive personal data.

## 📁 Structure

```
plugins/erpsaas/core/
├── src/
│   ├── Concerns/
│   │   └── SearchableEncryption.php      # Main trait for models
│   └── Console/Commands/
│       └── RegenerateBlindIndexes.php    # Artisan command
├── database/migrations/
│   └── 2026_05_05_000001_create_sensitive_data_access_logs_table.php
├── SEARCHABLE_ENCRYPTION_GUIDE.md        # Complete implementation guide
└── SEARCHABLE_ENCRYPTION_QUICK_REFERENCE.md  # Quick reference
```

## 🚀 Quick Start

### 1. Configure Environment

Add to your `.env` file:
```env
BLIND_INDEX_SALT=your-secret-salt-here-change-this
SEARCHABLE_ENCRYPTION_AUDIT=true
PDPA_DPO_EMAIL=dpo@yourcompany.com
```

### 2. Run Migration

```bash
php artisan migrate
```

### 3. Add Fields to Your Model Migration

For example, in HR plugin's user migration:

```php
// plugins/zrm/hr/database/migrations/add_sensitive_fields_to_users.php
Schema::table('users', function (Blueprint $table) {
    // NRIC
    $table->text('nric')->nullable();
    $table->string('nric_index', 64)->nullable()->index();
    
    // Bank Account
    $table->text('bank_account_number')->nullable();
    $table->string('bank_account_index', 64)->nullable()->index();
    
    // Salary
    $table->text('salary')->nullable();
    $table->string('salary_index', 64)->nullable()->index();
});
```

### 4. Use in Your Model

```php
<?php

namespace Zrm\Hr\Models;

use Erpsaas\Core\Concerns\SearchableEncryption;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use SearchableEncryption;

    protected array $searchableEncrypted = [
        'nric',
        'bank_account_number',
        'salary',
    ];

    protected function casts(): array
    {
        return [
            'nric' => 'encrypted',
            'bank_account_number' => 'encrypted',
            'salary' => 'encrypted:decimal:2',
        ];
    }
}
```

### 5. Search Encrypted Data

```php
// Search by NRIC
$employee = Employee::searchByEncrypted('nric', '901234-56-7890')->first();

// Display masked version
echo $employee->masked_nric; // "90****-**-***0"
```

## 📚 Documentation

- **[Complete Guide](./SEARCHABLE_ENCRYPTION_GUIDE.md)** - Detailed implementation, security best practices, PDPA compliance
- **[Quick Reference](./SEARCHABLE_ENCRYPTION_QUICK_REFERENCE.md)** - Common operations, commands, troubleshooting

## 🔐 Security Features

- ✅ **AES-256-CBC Encryption** at rest
- ✅ **Blind Indexing** (HMAC-SHA256) for searchable encryption
- ✅ **Automatic Audit Logging** for all access
- ✅ **Data Masking** for display
- ✅ **Key Rotation** support
- ✅ **PDPA Compliance** for Malaysia

## 📋 PDPA Compliance

This implementation addresses Malaysia Personal Data Protection Act (PDPA) 2010 requirements:

| Requirement | Implementation |
|-------------|----------------|
| Encryption at Rest | AES-256-CBC |
| Searchable Data | Blind indexing |
| Access Control | Authorization checks |
| Audit Trail | Automatic logging |
| Data Minimization | Masked accessors |
| Retention | Last accessed timestamps |

## 🛠️ Commands

```bash
# Regenerate blind indexes (after salt rotation)
php artisan core:regenerate-blind-indexes

# Specific model only
php artisan core:regenerate-blind-indexes --model=User

# Dry run
php artisan core:regenerate-blind-indexes --dry-run
```

## 🧪 Testing

Tests are included in `tests/` directory:

```bash
php artisan test plugins/erpsaas/core/tests
```

## ⚙️ Configuration

Register your models in `config/searchable-encryption.php`:

```php
'models' => [
    'User' => \Erpsaas\Core\Models\User::class,
    'Employee' => \Zrm\Hr\Models\Employee::class,
],
```

## 📞 Support

- **PDPA Website**: [www.pdp.gov.my](https://www.pdp.gov.my)
- **Hotline**: 03-7456 3888
- **Email**: aduan@pdp.gov.my

## ⚠️ Important Notes

1. **Never commit `.env` file** - it contains encryption keys
2. **Backup APP_KEY securely** - lost keys = unrecoverable data
3. **Don't change BLIND_INDEX_SALT** - requires full index regeneration
4. **Use HTTPS in production** - encrypt data in transit
5. **Enable audit logging** - required for PDPA compliance

---

**Version**: 1.0.0  
**Last Updated**: May 5, 2026  
**Compliance**: Malaysia PDPA 2010 (Act 709)
