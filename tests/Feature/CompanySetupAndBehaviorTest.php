<?php

use App\Models\User;
use Erpsaas\Accounts\Models\Accounting\Transaction;

test('initially assigns a personal company to the test user', function () {
    $testUser = User::first();
    $testCompany = $testUser->ownedCompanies->first();

    expect($testUser)->not->toBeNull()
        ->and($testCompany)->not->toBeNull()
        ->and($testCompany->personal_company)->toBeTrue()
        ->and($testUser->currentCompany->id)->toBe($testCompany->id);
})->group('company');

test('can create a new company and switches to it automatically', function () {
    $testUser = User::first();
    $testCompany = $testUser->ownedCompanies->first();

    $newCompany = $testUser->ownedCompanies()->create([
        'name' => 'New Company',
        'user_id' => $testUser->id,
        'personal_company' => false,
    ]);

    $testUser->switchCompany($newCompany);

    expect($newCompany)->not->toBeNull()
        ->and($newCompany->name)->toBe('New Company')
        ->and($newCompany->personal_company)->toBeFalse()
        ->and($testUser->currentCompany->id)->toBe($newCompany->id)
        ->and($newCompany->id)->not->toBe($testCompany->id);
})->group('company');

test('returns data for the current company based on the CurrentCompanyScope', function () {
    $testUser = User::first();
    $testCompany = $testUser->ownedCompanies->first();

    // Create transactions for the test company
    $initialCount = Transaction::count();

    Transaction::factory()
        ->forCompanyAndBankAccount($testCompany, $testCompany->default->bankAccount)
        ->count(10)
        ->create();

    // Verify we see the new transactions for the current company
    expect(Transaction::count())->toBe($initialCount + 10);

    // Store the company ID to verify scope is working
    $currentCompanyId = $testUser->currentCompany->id;

    // Verify all visible transactions belong to the current company
    $allTransactions = Transaction::all();
    foreach ($allTransactions as $transaction) {
        expect($transaction->company_id)->toBe($currentCompanyId);
    }
})->group('company');

test('validates that company default settings are non-null', function () {
    $testUser = User::first();
    $testCompany = $testUser->ownedCompanies->first();

    expect($testCompany->profile->address->country_code)->not->toBeNull()
        ->and($testCompany->profile->email)->not->toBeNull()
        ->and($testCompany->default->currency_code)->toBe('USD')
        ->and($testCompany->locale->language)->toBe('en')
        ->and($testCompany->default->bankAccount->account->name)->toBe('Cash on Hand');
})->group('company');
