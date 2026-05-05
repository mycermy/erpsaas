# 🔐 PDPA Searchable Encryption - Quick Reference

## 🚀 Quick Setup (5 Minutes)

### Step 1: Configure Environment
```bash
# Add to .env file
BLIND_INDEX_SALT=your-secret-salt-here-change-this-randomly
SEARCHABLE_ENCRYPTION_AUDIT=true
PDPA_DPO_EMAIL=dpo@yourcompany.com
```

### Step 2: Run Migration
```bash
php artisan migrate
```

### Step 3: Add Concern to Model
```php
use Erpsaas\Core\Concerns\SearchableEncryption;

class User extends Authenticatable
{
    use SearchableEncryption;

    protected array $searchableEncrypted = ['nric', 'bank_account_number', 'salary'];
    
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

### Step 4: Test It
```php
// Create
$user = User::create(['nric' => '901234-56-7890']);

// Search
$found = User::searchByEncrypted('nric', '901234-56-7890')->first();

// Display masked
echo $user->masked_nric; // "90****-**-***0"
```

✅ Done! You now have PDPA-compliant searchable encryption.

---

## 📖 Common Operations

### Creating Records
```php
// Blind indexes are auto-generated
User::create([
    'name' => 'Ahmad Abdullah',
    'nric' => '901234-56-7890',
    'bank_account_number' => '1234567890',
    'salary' => 5000.00,
]);
```

### Searching
```php
// Single value
$user = User::searchByEncrypted('nric', '901234-56-7890')->first();

// Multiple values (OR)
$users = User::searchByEncryptedIn('nric', ['901234-56-7890', '850101-12-3456'])->get();

// With other conditions
$user = User::where('email', 'ahmad@example.com')
    ->searchByEncrypted('nric', '901234-56-7890')
    ->first();
```

### Displaying Data
```php
// Full value (decrypted)
$nric = $user->nric; // "901234-56-7890"

// Masked for display
$masked = $user->masked_nric; // "90****-**-***0"
$masked = $user->masked_bank_account; // "******7890"
$masked = $user->masked_salary; // "RM 5,000 - 5,999"
```

### Verification
```php
// Verify without decrypting
if ($user->verifyEncryptedField('nric', '901234-56-7890')) {
    echo "NRIC matches!";
}
```

---

## 🛠️ Maintenance Commands

### Regenerate Blind Indexes
```bash
# All models
php artisan core:regenerate-blind-indexes

# Specific model
php artisan core:regenerate-blind-indexes --model=User

# Dry run (preview)
php artisan core:regenerate-blind-indexes --dry-run

# Custom chunk size
php artisan core:regenerate-blind-indexes --chunk=50
```

### When to Regenerate
- ✅ After changing `BLIND_INDEX_SALT`
- ✅ After corrupted indexes
- ✅ Migrating existing encrypted data
- ✅ Adding trait to existing models

---

## 🔑 Key Management

### Backup Your Keys
```bash
# Backup .env file (encrypted)
cp .env .env.backup.$(date +%Y%m%d)
gpg --encrypt .env.backup.20260505

# Store in secure location
# - Password manager
# - Encrypted vault
# - Secure key management system
```

### Key Rotation Process
```bash
# 1. Backup database
php artisan backup:run --only-db

# 2. Generate new key
php artisan key:generate --show

# 3. Update .env with new key
# APP_KEY=base64:NEW_KEY_HERE

# 4. Regenerate blind indexes
php artisan security:regenerate-blind-indexes

# 5. Restart application
php artisan config:cache
php artisan queue:restart
```

---

## 🧪 Testing

```php
use App\Models\User;

it('can search encrypted NRIC', function () {
    $user = User::factory()->create(['nric' => '901234-56-7890']);
    
    $found = User::searchByEncrypted('nric', '901234-56-7890')->first();
    
    expect($found->id)->toBe($user->id);
});

it('generates blind index automatically', function () {
    $user = User::factory()->create(['nric' => '901234-56-7890']);
    
    expect($user->nric_index)->not->toBeNull();
    expect($user->nric_index)->toHaveLength(64);
});

it('masks sensitive data', function () {
    $user = User::factory()->create(['nric' => '901234-56-7890']);
    
    expect($user->masked_nric)->toBe('90****-**-***0');
});
```

---

## 📊 Compliance Monitoring

### Access Audit Queries
```php
// Recent accesses (last 30 days)
$logs = DB::table('sensitive_data_access_logs')
    ->where('created_at', '>=', now()->subDays(30))
    ->get();

// Top accessors
$topUsers = DB::table('sensitive_data_access_logs')
    ->select('accessed_by_user_id', DB::raw('COUNT(*) as count'))
    ->groupBy('accessed_by_user_id')
    ->orderByDesc('count')
    ->limit(10)
    ->get();

// Access by field
$byField = DB::table('sensitive_data_access_logs')
    ->select('field_name', DB::raw('COUNT(*) as count'))
    ->groupBy('field_name')
    ->get();
```

### Retention Management
```php
// Records never accessed (can be purged)
$stale = User::neverAccessed()
    ->where('created_at', '<', now()->subYears(2))
    ->get();

// Recently accessed
$active = User::recentlyAccessed(days: 90)->get();

// Old audit logs (ready for archival)
$oldLogs = DB::table('sensitive_data_access_logs')
    ->where('created_at', '<', now()->subYear())
    ->get();
```

---

## ⚠️ Troubleshooting

### Problem: Can't find records after searching
**Solution**: Regenerate blind indexes
```bash
php artisan security:regenerate-blind-indexes
```

### Problem: "Payload is invalid" error
**Cause**: APP_KEY has changed
**Solution**: Restore original APP_KEY or re-encrypt data

### Problem: Slow searches
**Solution**: Ensure indexes exist
```php
$table->string('nric_index', 64)->index(); // ← Must have index()
```

### Problem: Blind indexes not generating
**Solution**: Check trait is used and fields are in $searchableEncrypted
```php
class User extends Model
{
    use SearchableEncryption; // ← Check this
    
    protected array $searchableEncrypted = ['nric']; // ← Check this
}
```

---

## 🔒 Security Checklist

### Critical Security Rules
- [x] ✅ Keep `.env` in `.gitignore`
- [x] ✅ Never commit APP_KEY to version control
- [x] ✅ Backup APP_KEY securely
- [x] ✅ Use different keys for dev/staging/production
- [x] ✅ Restrict .env file access (chmod 600)
- [x] ✅ Enable HTTPS in production
- [x] ✅ Hide sensitive fields from JSON output
- [x] ✅ Use authorization before accessing sensitive data
- [x] ✅ Enable audit logging
- [x] ✅ Rotate keys annually

---

## 📋 PDPA Requirements Met

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| Encryption at Rest | ✅ | AES-256-CBC |
| Searchable Data | ✅ | Blind Indexing (HMAC-SHA256) |
| Access Control | ✅ | Authorization checks |
| Audit Trail | ✅ | Automatic logging |
| Data Minimization | ✅ | Masked accessors |
| Retention | ✅ | Last accessed timestamps |
| Right to Erasure | ✅ | Soft deletes |
| Data Integrity | ✅ | Hash verification |

---

## 📞 Support & Resources

### Official PDPA
- Website: [www.pdp.gov.my](https://www.pdp.gov.my)
- Hotline: 03-7456 3888
- Email: aduan@pdp.gov.my

### Documentation
- [Full Guide](./SEARCHABLE_ENCRYPTION_GUIDE.md)
- [Laravel Encryption](https://laravel.com/docs/encryption)
- [HMAC Security](https://en.wikipedia.org/wiki/HMAC)

---

## 💡 Pro Tips

### Performance
```php
// ✅ Use eager loading
$users = User::with('department')->searchByEncrypted('nric', $nric)->get();

// ✅ Cache frequently accessed data
$user = Cache::remember("user_nric_{$nric}", 3600, function () use ($nric) {
    return User::searchByEncrypted('nric', $nric)->first();
});

// ✅ Use chunk for batch operations
User::chunk(100, function ($users) {
    foreach ($users as $user) {
        // Process
    }
});
```

### API Responses
```php
// ✅ Use resource classes
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nric' => $this->when(
                $request->user()->can('view-sensitive-data'),
                $this->masked_nric
            ),
        ];
    }
}
```

### Authorization
```php
// ✅ Gate for sensitive data access
Gate::define('view-sensitive-data', function ($user) {
    return $user->hasRole(['admin', 'hr-manager']);
});

// ✅ Use in controllers
public function show(User $user)
{
    $this->authorize('view-sensitive-data', $user);
    return $user->nric;
}
```

---

**Last Updated**: May 5, 2026  
**Version**: 1.0.0  
**Compliance**: Malaysia PDPA 2010 (Act 709)
