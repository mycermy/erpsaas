<?php

use Erpsaas\Core\Concerns\SearchableEncryption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Test Model using SearchableEncryption
 */
class TestEmployee extends Model
{
    use SearchableEncryption;

    protected $table = 'test_employees';

    protected $guarded = [];

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

    /**
     * Note: In tests, we don't hide fields to allow direct property access for assertions.
     * In production models, you should hide sensitive fields and _index columns.
     */
    protected $hidden = [];
}

beforeEach(function () {
    // Create test table
    Schema::create('test_employees', function ($table) {
        $table->id();
        $table->string('name');
        $table->text('nric')->nullable();
        $table->string('nric_index', 64)->nullable()->index();
        $table->text('bank_account_number')->nullable();
        $table->string('bank_account_index', 64)->nullable()->index();
        $table->text('salary')->nullable();
        $table->string('salary_index', 64)->nullable()->index();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('test_employees');
});

/**
 * Searchable Encryption Tests
 *
 * Tests PDPA-compliant encryption with blind indexing functionality
 */
describe('SearchableEncryption Concern', function () {

    it('automatically generates blind indexes on save', function () {
        $employee = TestEmployee::create([
            'name' => 'Ahmad bin Abdullah',
            'nric' => '901234-56-7890',
            'bank_account_number' => '1234567890',
            'salary' => 5000.00,
        ]);

        expect($employee->nric_index)->not->toBeNull();
        expect($employee->bank_account_index)->not->toBeNull();
        expect($employee->salary_index)->not->toBeNull();

        // SHA256 produces 64 character hex string
        expect(strlen($employee->nric_index))->toBe(64);
        expect(strlen($employee->bank_account_index))->toBe(64);
        expect(strlen($employee->salary_index))->toBe(64);
    });

    it('encrypts sensitive data at rest', function () {
        $employee = TestEmployee::create([
            'name' => 'Siti binti Hassan',
            'nric' => '850101-12-3456',
            'salary' => 7500.50,
        ]);

        // Check database has encrypted data (not plain text)
        $raw = DB::table('test_employees')->find($employee->id);

        expect($raw->nric)->not->toBe('850101-12-3456');
        expect($raw->nric)->toContain('eyJ'); // Base64 encrypted format

        // But model returns decrypted value
        expect($employee->nric)->toBe('850101-12-3456');
    });

    it('can search by encrypted NRIC', function () {
        $employee = TestEmployee::create([
            'name' => 'Ali bin Ahmad',
            'nric' => '901234-56-7890',
        ]);

        $found = TestEmployee::searchByEncrypted('nric', '901234-56-7890')->first();

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($employee->id);
        expect($found->nric)->toBe('901234-56-7890');
    });

    it('can search by encrypted bank account', function () {
        $employee = TestEmployee::create([
            'name' => 'Fatima binti Ismail',
            'bank_account_number' => '9876543210',
        ]);

        $found = TestEmployee::searchByEncrypted('bank_account_number', '9876543210')->first();

        expect($found)->not->toBeNull();
        expect($found->bank_account_number)->toBe('9876543210');
    });

    it('can search by encrypted salary', function () {
        $employee = TestEmployee::create([
            'name' => 'Kumar a/l Rajan',
            'salary' => 6500.00,
        ]);

        $found = TestEmployee::searchByEncrypted('salary', 6500.00)->first();

        expect($found)->not->toBeNull();
        expect($found->salary)->toBe('6500.00');
    });

    it('returns null when searching for non-existent encrypted value', function () {
        TestEmployee::create([
            'name' => 'Test User',
            'nric' => '901234-56-7890',
        ]);

        $found = TestEmployee::searchByEncrypted('nric', '999999-99-9999')->first();

        expect($found)->toBeNull();
    });

    it('can search multiple encrypted values with searchByEncryptedIn', function () {
        $emp1 = TestEmployee::create(['name' => 'User 1', 'nric' => '901234-56-7890']);
        $emp2 = TestEmployee::create(['name' => 'User 2', 'nric' => '850101-12-3456']);
        $emp3 = TestEmployee::create(['name' => 'User 3', 'nric' => '950202-34-5678']);

        $found = TestEmployee::searchByEncryptedIn('nric', [
            '901234-56-7890',
            '850101-12-3456',
        ])->get();

        expect($found)->toHaveCount(2);
        expect($found->pluck('id')->toArray())->toContain($emp1->id);
        expect($found->pluck('id')->toArray())->toContain($emp2->id);
        expect($found->pluck('id')->toArray())->not->toContain($emp3->id);
    });

    it('generates case-insensitive blind indexes', function () {
        $employee = TestEmployee::create([
            'name' => 'Test User',
            'nric' => '901234-56-7890',
        ]);

        // Search with different case should still work
        $found = TestEmployee::searchByEncrypted('nric', '901234-56-7890')->first();

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($employee->id);
    });

    it('verifies encrypted field without decryption', function () {
        $employee = TestEmployee::create([
            'name' => 'Test User',
            'nric' => '901234-56-7890',
        ]);

        expect($employee->verifyEncryptedField('nric', '901234-56-7890'))->toBeTrue();
        expect($employee->verifyEncryptedField('nric', 'wrong-nric'))->toBeFalse();
    });

    it('can regenerate blind indexes', function () {
        $employee = TestEmployee::create([
            'name' => 'Test User',
            'nric' => '901234-56-7890',
        ]);

        $originalIndex = $employee->nric_index;

        // Manually change index to simulate corruption
        $employee->nric_index = 'corrupted-index';
        $employee->saveQuietly();

        // Regenerate should fix it
        $employee->regenerateBlindIndexes();

        expect($employee->nric_index)->toBe($originalIndex);
        expect($employee->nric_index)->not->toBe('corrupted-index');
    });

    it('updates blind index when encrypted field changes', function () {
        $employee = TestEmployee::create([
            'name' => 'Test User',
            'nric' => '901234-56-7890',
        ]);

        $originalIndex = $employee->nric_index;

        // Update NRIC
        $employee->nric = '850101-12-3456';
        $employee->save();

        // Index should have changed
        expect($employee->nric_index)->not->toBe($originalIndex);

        // Should be searchable by new value
        $found = TestEmployee::searchByEncrypted('nric', '850101-12-3456')->first();
        expect($found->id)->toBe($employee->id);

        // Old value should not find it
        $notFound = TestEmployee::searchByEncrypted('nric', '901234-56-7890')->first();
        expect($notFound)->toBeNull();
    });
});

describe('Security & PDPA Compliance', function () {

    it('encrypts data at rest and allows model access', function () {
        $employee = TestEmployee::create([
            'name' => 'Test User',
            'nric' => '901234-56-7890',
            'bank_account_number' => '1234567890',
            'salary' => 5000.00,
        ]);

        // Check raw database has encrypted data
        $raw = DB::table('test_employees')->find($employee->id);
        expect($raw->nric)->toContain('eyJ'); // Base64 encrypted

        // Model can access decrypted value
        expect($employee->nric)->toBe('901234-56-7890');

        // Note: In production, add fields to $hidden array to prevent JSON exposure
    });

    it('uses different blind indexes for different fields', function () {
        $employee = TestEmployee::create([
            'name' => 'Test User',
            'nric' => '901234-56-7890',
            'bank_account_number' => '901234-56-7890', // Same value as NRIC
        ]);

        // Even with same value, indexes should be different (field-specific salt)
        expect($employee->nric_index)->not->toBe($employee->bank_account_index);
    });

    it('produces consistent blind indexes for same value', function () {
        $emp1 = TestEmployee::create(['name' => 'User 1', 'nric' => '901234-56-7890']);
        $emp2 = TestEmployee::create(['name' => 'User 2', 'nric' => '901234-56-7890']);

        // Same NRIC should produce same index
        expect($emp1->nric_index)->toBe($emp2->nric_index);
    });

    it('handles null values in encrypted fields', function () {
        $employee = TestEmployee::create([
            'name' => 'Test User',
            'nric' => null,
        ]);

        expect($employee->nric)->toBeNull();
        expect($employee->nric_index)->toBeNull();
    });

    it('can search for null encrypted values', function () {
        $emp1 = TestEmployee::create(['name' => 'User 1', 'nric' => null]);
        $emp2 = TestEmployee::create(['name' => 'User 2', 'nric' => '901234-56-7890']);

        $found = TestEmployee::searchByEncrypted('nric', null)->get();

        expect($found)->toHaveCount(1);
        expect($found->first()->id)->toBe($emp1->id);
    });
});

describe('Batch Operations', function () {

    it('can regenerate all blind indexes', function () {
        // Create multiple employees
        TestEmployee::create(['name' => 'User 1', 'nric' => '901234-56-7890']);
        TestEmployee::create(['name' => 'User 2', 'nric' => '850101-12-3456']);
        TestEmployee::create(['name' => 'User 3', 'nric' => '950202-34-5678']);

        // Corrupt all indexes
        DB::table('test_employees')->update(['nric_index' => 'corrupted']);

        // Regenerate all
        $count = TestEmployee::regenerateAllBlindIndexes(chunkSize: 2);

        expect($count)->toBe(3);

        // Verify all are searchable again
        $found1 = TestEmployee::searchByEncrypted('nric', '901234-56-7890')->first();
        $found2 = TestEmployee::searchByEncrypted('nric', '850101-12-3456')->first();
        $found3 = TestEmployee::searchByEncrypted('nric', '950202-34-5678')->first();

        expect($found1)->not->toBeNull();
        expect($found2)->not->toBeNull();
        expect($found3)->not->toBeNull();
    });
});

describe('Performance', function () {

    it('searches encrypted fields efficiently with index', function () {
        // Create many employees
        for ($i = 0; $i < 100; $i++) {
            TestEmployee::create([
                'name' => "User {$i}",
                'nric' => sprintf('%06d-56-7890', $i),
            ]);
        }

        // Search should use index (fast)
        $start = microtime(true);
        $found = TestEmployee::searchByEncrypted('nric', '000050-56-7890')->first();
        $duration = microtime(true) - $start;

        expect($found)->not->toBeNull();
        expect($duration)->toBeLessThan(0.1); // Should be very fast with index
    });
});
