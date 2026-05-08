<?php

namespace Zrm\Hr\Models;

use App\Models\User;
use Erpsaas\Core\Concerns\Blamable;
use Erpsaas\Core\Concerns\CompanyOwned;
use Erpsaas\Core\Concerns\SearchableEncryption;
use Erpsaas\Core\Enums\Common\AddressType;
use Erpsaas\Core\Models\Common\Address;
use Erpsaas\Core\Models\Common\Contact;
use Erpsaas\Core\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Employee extends Model
{
    use Blamable;
    use CompanyOwned;
    use SearchableEncryption;

    protected $table = 'employees';

    protected $fillable = [
        'company_id',
        'name',
        'employee_number',
        'job_title',
        'department',
        'separate_work_address',
        'nric',
        'passport_number',
        'bank_account_number',
        'bank_name',
        'bank_branch',
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

    protected function casts(): array
    {
        return [
            'nric' => 'encrypted',
            'passport_number' => 'encrypted',
            'bank_account_number' => 'encrypted',
            'encrypted_base_salary' => 'encrypted:decimal:4',
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
     * Note: Main encrypted fields are accessible for authorized admin users in Filament.
     * The data is still encrypted at rest. Only the blind index fields are hidden.
     */
    protected $hidden = [
        'nric_index',
        'passport_number_index',
        'bank_account_number_index',
        'encrypted_base_salary_index',
        'epf_number_index',
        'socso_number_index',
        'income_tax_number_index',
        'emergency_contact_phone_index',
    ];

    public static function createWithRelations(array $data): self
    {
        /** @var Employee $employee */
        $employee = self::create(Arr::except($data, ['contact', 'homeAddress', 'workAddress']));

        if (isset($data['contact'])) {
            $contactData = $data['contact'];
            $contactData['is_primary'] = true;
            $employee->contact()->create($contactData);
        }

        if (isset($data['homeAddress'], $data['homeAddress']['address_line_1'])) {
            $employee->homeAddress()->create([
                'type' => AddressType::Home,
                'address_line_1' => $data['homeAddress']['address_line_1'],
                'address_line_2' => $data['homeAddress']['address_line_2'] ?? null,
                'country_code' => $data['homeAddress']['country_code'] ?? null,
                'state_id' => $data['homeAddress']['state_id'] ?? null,
                'city' => $data['homeAddress']['city'] ?? null,
                'postal_code' => $data['homeAddress']['postal_code'] ?? null,
            ]);
        }

        if ($data['separate_work_address'] == true && isset($data['workAddress'])) {
            $workAddressData = $data['workAddress'];
            $employee->addresses()->create([
                'type' => AddressType::Work,
                'address_line_1' => $workAddressData['address_line_1'],
                'address_line_2' => $workAddressData['address_line_2'] ?? null,
                'country_code' => $workAddressData['country_code'] ?? null,
                'state_id' => $workAddressData['state_id'] ?? null,
                'city' => $workAddressData['city'] ?? null,
                'postal_code' => $workAddressData['postal_code'] ?? null,
            ]);
        }

        return $employee;
    }

    public function updateWithRelations(array $data): self
    {
        $this->update(Arr::except($data, ['contact', 'homeAddress', 'workAddress']));

        if (isset($data['contact'])) {
            $contactData = $data['contact'];
            $contactData['is_primary'] = true;

            if ($this->contact) {
                $this->contact->update($contactData);
            } else {
                $this->contact()->create($contactData);
            }
        }

        if (isset($data['homeAddress'], $data['homeAddress']['address_line_1'])) {
            if ($this->homeAddress) {
                $this->homeAddress->update($data['homeAddress']);
            } else {
                $this->homeAddress()->create([
                    'type' => AddressType::Home,
                    'address_line_1' => $data['homeAddress']['address_line_1'],
                    'address_line_2' => $data['homeAddress']['address_line_2'] ?? null,
                    'country_code' => $data['homeAddress']['country_code'] ?? null,
                    'state_id' => $data['homeAddress']['state_id'] ?? null,
                    'city' => $data['homeAddress']['city'] ?? null,
                    'postal_code' => $data['homeAddress']['postal_code'] ?? null,
                ]);
            }
        }

        if ($data['separate_work_address'] == true && isset($data['workAddress'], $data['workAddress']['address_line_1'])) {
            if ($this->workAddress) {
                $this->workAddress->update($data['workAddress']);
            } else {
                $workAddressData = $data['workAddress'];
                $workAddressData['type'] = AddressType::Work;
                $this->addresses()->create($workAddressData);
            }
        } elseif ($data['separate_work_address'] == false && $this->workAddress) {
            $this->workAddress->delete();
        }

        return $this;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function homeAddress(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable')
            ->where('type', AddressType::Home);
    }

    public function workAddress(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable')
            ->where('type', AddressType::Work);
    }

    public function contact(): MorphOne
    {
        return $this->morphOne(Contact::class, 'contactable')
            ->where('is_primary', true);
    }

    public function salaryRevisions(): HasMany
    {
        return $this->hasMany(EmployeeSalaryRevision::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(EmployeeAdvance::class);
    }

    public function lastAccessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sensitive_data_accessed_by');
    }

    /**
     * NOTE: Audit logging for sensitive data access should be done at the application layer
     * (controllers, services) rather than in model accessors to avoid interfering with
     * Laravel's encrypted cast functionality.
     *
     * The encrypted cast must be able to process values without interference.
     *
     * Previous Attribute accessors for nric, bankAccountNumber, and encryptedBaseSalary
     * have been removed to allow proper decryption via the 'encrypted' cast.
     */

    /**
     * Get masked NRIC for display (PDPA Data Minimization)
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
        return substr($nric, 0, 2) . str_repeat('*', max(0, strlen($nric) - 3)) . substr($nric, -1);
    }

    /**
     * Get masked bank account (last 4 digits only)
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
     * Get masked SOCSO number
     */
    public function getMaskedSocsoAttribute(): ?string
    {
        if (! $this->socso_number) {
            return null;
        }

        $socso = $this->socso_number;
        $length = strlen($socso);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($socso, 0, 2) . str_repeat('*', $length - 4) . substr($socso, -2);
    }

    /**
     * Get masked salary (rounded range)
     */
    public function getMaskedSalaryAttribute(): ?string
    {
        $salary = $this->encrypted_base_salary;

        if (! $salary) {
            return null;
        }

        $salary = (float) $salary;
        $rounded = round($salary / 1000) * 1000;

        return 'RM ' . number_format($rounded, 0) . ' - ' . number_format($rounded + 999, 0);
    }

    /**
     * Get masked phone number
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
     */
    public function scopeRecentlyAccessed($query, int $days = 30)
    {
        return $query->where('sensitive_data_last_accessed_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: Never accessed sensitive data
     */
    public function scopeNeverAccessed($query)
    {
        return $query->whereNull('sensitive_data_last_accessed_at');
    }

    /**
     * Get the most recent salary revision effective on or before a given date.
     */
    public function currentSalaryRevision(?string $asOfDate = null): HasOne
    {
        $asOfDate ??= now()->toDateString();

        return $this->hasOne(EmployeeSalaryRevision::class)
            ->where('effective_from', '<=', $asOfDate)
            ->latestOfMany('effective_from');
    }

    public static function getNextEmployeeNumber(?Company $company = null): string
    {
        $company ??= Auth::user()?->currentCompany;

        if (! $company) {
            throw new \RuntimeException('No current company is set for the user.');
        }

        $latestEmployee = static::query()
            ->whereNotNull('employee_number')
            ->latest('employee_number')
            ->first();

        $lastNumberNumericPart = $latestEmployee
            ? (int) substr($latestEmployee->employee_number, strlen('EMP-'))
            : 0;

        return 'EMP-' . str_pad($lastNumberNumericPart + 1, 4, '0', STR_PAD_LEFT);
    }
}
