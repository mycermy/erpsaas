<?php

use App\Models\Accounting\Transaction;

it('initially assigns a personal company to the test user', function () {
    $testUser = $this->testUser;
    $testCompany = $this->testCompany;

    expect($testUser)->not->toBeNull()
        ->and($testCompany)->not->toBeNull()
        ->and($testCompany->personal_company)->toBeTrue()
        ->and($testUser->currentCompany->id)->toBe($testCompany->id);
});

it('can create a new company and switches to it automatically', function () {
    $testUser = $this->testUser;
    $testCompany = $this->testCompany;

    $newCompany = createCompany('New Company');

    expect($newCompany)->not->toBeNull()
        ->and($newCompany->name)->toBe('New Company')
        ->and($newCompany->personal_company)->toBeFalse()
        ->and($testUser->currentCompany->id)->toBe($newCompany->id)
        ->and($newCompany->id)->not->toBe($testCompany->id);
});

it('returns data for the current company based on the CurrentCompanyScope', function () {
    $testUser = $this->testUser;
    $testCompany = $this->testCompany;

    $defaultBankAccount = $testCompany->default?->bankAccount ?? \App\Models\Banking\BankAccount::factory()->create([
        'company_id' => $testCompany->id,
        'enabled' => true,
    ]);

    Transaction::factory()
        ->forCompanyAndBankAccount($testCompany, $defaultBankAccount)
        ->count(10)
        ->create();

    $newCompany = createCompany('New Company');

    expect($testUser->currentCompany->id)
        ->toBe($newCompany->id)
        ->not->toBe($testCompany->id);

    $newDefaultBankAccount = $newCompany->default?->bankAccount ?? \App\Models\Banking\BankAccount::factory()->create([
        'company_id' => $newCompany->id,
        'enabled' => true,
    ]);

    Transaction::factory()
        ->count(5)
        ->create([
            'company_id' => $newCompany->id,
            'bank_account_id' => $newDefaultBankAccount->id,
            'created_by' => $newCompany->user_id,
            'updated_by' => $newCompany->user_id,
        ]);

    expect(Transaction::withoutGlobalScopes()->where('company_id', $newCompany->id)->count())->toBe(5);

    $testUser->switchCompany($testCompany);

    expect($testUser->currentCompany->id)->toBe($testCompany->id)
        ->and(Transaction::withoutGlobalScopes()->where('company_id', $testCompany->id)->count())->toBe(10);
});

it('validates that company default settings are non-null', function () {
    $testCompany = $this->testCompany;

    expect($testCompany->profile->address->country_code)->not->toBeNull()
        ->and($testCompany->profile->email)->not->toBeNull()
        ->and($testCompany->default->currency_code)->toBe('USD')
        ->and($testCompany->locale->language)->toBe('en')
        ->and($testCompany->default->bankAccount->account->name)->toBe('Cash on Hand');
});
