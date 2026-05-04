<?php

namespace Erpsaas\Hr\Models;

use Erpsaas\Core\Concerns\Blamable;
use Erpsaas\Core\Concerns\CompanyOwned;
use Erpsaas\Core\Enums\Common\AddressType;
use Erpsaas\Core\Models\Common\Address;
use Erpsaas\Core\Models\Common\Contact;
use Erpsaas\Core\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class Employee extends Model
{
    use Blamable;
    use CompanyOwned;

    protected $table = 'employees';

    protected $fillable = [
        'company_id',
        'name',
        'employee_number',
        'job_title',
        'department',
        'separate_work_address',
        'base_salary_amount',
        'salary_effective_from',
    ];

    protected function casts(): array
    {
        return [
            'base_salary_amount' => 'decimal:4',
            'salary_effective_from' => 'date',
        ];
    }

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
