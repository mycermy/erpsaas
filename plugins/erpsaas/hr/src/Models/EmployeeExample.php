<?php

namespace Erpsaas\Hr\Models;

use Erpsaas\Core\Concerns\SearchableEncryption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Employee Model with PDPA-Compliant Searchable Encryption
 *
 * This model demonstrates the implementation of SearchableEncryption concern
 * for handling sensitive personal data under Malaysia PDPA requirements.
 *
 * Encrypted Fields:
 * - NRIC (MyKad number)
 * - Passport number
 * - Bank account number
 * - Base salary
 * - EPF number
 * - SOCSO number
 * - Income tax number
 * - Emergency contact phone
 *
 * @property int $id
 * @property int $company_id
 * @property string $employee_number
 * @property string $job_title
 * @property string|null $department
 * @property string|null $nric Encrypted NRIC
 * @property string|null $nric_index Auto-generated blind index
 * @property string|null $passport_number Encrypted passport
 * @property string|null $bank_account_number Encrypted bank account
 * @property string|null $bank_name
 * @property float|null $base_salary_amount Unencrypted (legacy)
 * @property string|null $encrypted_base_salary Encrypted salary
 * @property string|null $epf_number Encrypted EPF
 * @property string|null $socso_number Encrypted SOCSO
 * @property string|null $income_tax_number Encrypted tax number
 * @property string|null $emergency_contact_phone Encrypted phone
 */
class Employee extends Model
{
    use SearchableEncryption;
    use SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'company_id',
        'employee_number',
        'job_title',
        'department',
        'nric',
        'passport_number',
        'bank_account_number',
        'bank_name',
        'bank_branch',
        'base_salary_amount',
        'encrypted_base_salary',
        'salary_effective_from',
        'epf_number',
        'socso_number',
        'income_tax_number',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
    ];

    /**
     * Define which encrypted fields should be searchable via blind indexing
     *
     * CRITICAL: For each field listed, ensure migration creates '{field}_index' column
     */
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

    /**
     * Cast attributes with encryption
     *
     * Laravel's 'encrypted' cast automatically:
     * - Encrypts when saving to database (AES-256-CBC)
     * - Decrypts when retrieving from database
     * - Uses APP_KEY for encryption/decryption
     */
    protected function casts(): array
    {
        return [
            'nric' => 'encrypted',
            'passport_number' => 'encrypted',
            'bank_account_number' => 'encrypted',
            'encrypted_base_salary' => 'encrypted:decimal:2',
            'base_salary_amount' => 'decimal:2', // Legacy unencrypted
            'salary_effective_from' => 'date',
            'epf_number' => 'encrypted',
            'socso_number' => 'encrypted',
            'income_tax_number' => 'encrypted',
            'emergency_contact_phone' => 'encrypted',
            'sensitive_data_last_accessed_at' => 'datetime',
        ];
    }

    /**
     * Hidden fields - prevent accidental exposure in JSON/Array
     *
     * PDPA Best Practice: Never expose sensitive data or blind indexes
     * in API responses unless explicitly authorized
     */
    protected $hidden = [
        'nric',
        'nric_index',
        'passport_number',
        'passport_index',
        'bank_account_number',
        'bank_account_index',
        'encrypted_base_salary',
        'salary_index',
        'epf_number',
        'epf_index',
        'socso_number',
        'socso_index',
        'income_tax_number',
        'tax_number_index',
        'emergency_contact_phone',
        'emergency_contact_phone_index',
    ];

    /**
     * Relationships
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    public function lastAccessedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'sensitive_data_accessed_by');
    }

    /**
     * NRIC Accessor with Audit Logging
     *
     * Automatically logs access for PDPA compliance
     */
    protected function nric(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value) {
                $this->logSensitiveDataAccess('nric', 'read');

                return $value;
            }
        );
    }

    /**
     * Bank Account Accessor with Audit Logging
     */
    protected function bankAccountNumber(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value) {
                $this->logSensitiveDataAccess('bank_account_number', 'read');

                return $value;
            }
        );
    }

    /**
     * Encrypted Salary Accessor with Audit Logging
     */
    protected function encryptedBaseSalary(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value) {
                $this->logSensitiveDataAccess('encrypted_base_salary', 'read');

                return $value;
            }
        );
    }

    /**
     * Get masked NRIC for display (PDPA Data Minimization)
     *
     * Format: 901234-56-7890 → 90****-**-***0
     */
    public function getMaskedNricAttribute(): ?string
    {
        if (! $this->nric) {
            return null;
        }

        $nric = $this->nric;

        // Mask Malaysian NRIC format: YYMMDD-PB-###G
        if (preg_match('/^(\d{2})\d{4}-(\d{2})-\d{3}(\d)$/', $nric, $matches)) {
            return $matches[1] . '****-**-***' . $matches[3];
        }

        // Fallback masking
        return substr($nric, 0, 2) . str_repeat('*', strlen($nric) - 3) . substr($nric, -1);
    }

    /**
     * Get masked bank account (last 4 digits only)
     *
     * Example: 1234567890 → ******7890
     */
    public function getMaskedBankAccountAttribute(): ?string
    {
        if (! $this->bank_account_number) {
            return null;
        }

        $account = $this->bank_account_number;
        $length = strlen($account);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . substr($account, -4);
    }

    /**
     * Get masked EPF number
     *
     * Example: 12345678 → 12****78
     */
    public function getMaskedEpfAttribute(): ?string
    {
        if (! $this->epf_number) {
            return null;
        }

        $epf = $this->epf_number;
        $length = strlen($epf);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($epf, 0, 2) . str_repeat('*', $length - 4) . substr($epf, -2);
    }

    /**
     * Get masked salary (rounded range)
     *
     * Example: RM 5,234.50 → RM 5,000 - 6,000
     */
    public function getMaskedSalaryAttribute(): ?string
    {
        $salary = $this->encrypted_base_salary ?? $this->base_salary_amount;

        if (! $salary) {
            return null;
        }

        $salary = (float) $salary;
        $rounded = round($salary / 1000) * 1000;

        return 'RM ' . number_format($rounded, 0) . ' - ' . number_format($rounded + 999, 0);
    }

    /**
     * Get masked phone number
     *
     * Example: 0123456789 → 012****789
     */
    public function getMaskedEmergencyPhoneAttribute(): ?string
    {
        if (! $this->emergency_contact_phone) {
            return null;
        }

        $phone = $this->emergency_contact_phone;
        $length = strlen($phone);

        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return substr($phone, 0, 3) . str_repeat('*', $length - 6) . substr($phone, -3);
    }

    /**
     * Log sensitive data access for PDPA audit trail
     *
     * @param  string  $field  Field name that was accessed
     * @param  string  $action  Action performed (read, write, search)
     */
    protected function logSensitiveDataAccess(string $field, string $action = 'read'): void
    {
        if (! $this->exists || ! config('searchable-encryption.audit_logging.enabled', true)) {
            return;
        }

        // Update last accessed timestamp
        $this->timestamps = false;
        $this->sensitive_data_last_accessed_at = now();
        $this->sensitive_data_accessed_by = Auth::id();
        $this->saveQuietly();
        $this->timestamps = true;

        // Log to audit table
        if (config('searchable-encryption.audit_logging.database', true)) {
            DB::table('sensitive_data_access_logs')->insert([
                'model_type' => static::class,
                'model_id' => $this->id,
                'field_name' => $field,
                'accessed_by_user_id' => Auth::id(),
                'ip_address' => request()->ip(),
                'action' => $action,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Scope: Recently accessed sensitive data
     *
     * For PDPA compliance monitoring and reporting
     */
    public function scopeRecentlyAccessed($query, int $days = 30)
    {
        return $query->where('sensitive_data_last_accessed_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: Never accessed sensitive data
     *
     * Helps identify stale data for PDPA retention policy
     */
    public function scopeNeverAccessed($query)
    {
        return $query->whereNull('sensitive_data_last_accessed_at');
    }

    /**
     * Scope: Active employees only
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }
}
