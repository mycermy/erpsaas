<?php

namespace Erpsaas\Core\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * SearchableEncryption Concern
 *
 * Implements PDPA-compliant encryption with blind indexing for sensitive personal data.
 *
 * PDPA Compliance:
 * - Encrypts sensitive fields (NRIC, bank accounts, salary) at rest
 * - Uses blind indexing (salted hash) to enable searching without decryption
 * - Supports Malaysia PDPA requirements for data security
 *
 * Usage:
 * 1. Add concern to model: use SearchableEncryption;
 * 2. Define searchable encrypted fields in $searchableEncrypted property
 * 3. Create index columns in migration (fieldname_index)
 * 4. Use searchByEncrypted() method to search encrypted fields
 *
 * @see https://www.pdp.gov.my Malaysia Personal Data Protection Act
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait SearchableEncryption
{
    /**
     * Boot the trait - automatically generate blind indexes on save
     */
    protected static function bootSearchableEncryption(): void
    {
        static::saving(function ($model) {
            $model->updateBlindIndexes();
        });
    }

    /**
     * Get the list of searchable encrypted fields
     * Override this property in your model
     *
     * Example:
     * protected array $searchableEncrypted = ['nric', 'bank_account', 'salary'];
     */
    protected function getSearchableEncryptedFields(): array
    {
        return $this->searchableEncrypted ?? [];
    }

    /**
     * Update blind indexes for all searchable encrypted fields
     * Called automatically on model save
     */
    protected function updateBlindIndexes(): void
    {
        foreach ($this->getSearchableEncryptedFields() as $field) {
            // Use magic getter to get decrypted value (handles encrypted cast properly)
            $value = $this->$field ?? null;

            if ($value !== null && ! empty($value)) {
                $indexColumn = "{$field}_index";
                $this->attributes[$indexColumn] = $this->generateBlindIndex($field, $value);
            }
        }
    }

    /**
     * Generate a blind index (salted hash) for searchable encryption
     *
     * Uses HMAC-SHA256 with application-specific salt for security
     * The blind index allows searching without exposing encrypted data
     *
     * @param  string  $field  Field name (used in salt)
     * @param  mixed  $value  Plain text value to index
     * @return string Hex-encoded hash for blind index
     */
    protected function generateBlindIndex(string $field, mixed $value): string
    {
        // Normalize the value (trim, lowercase for case-insensitive search)
        $normalizedValue = strtolower(trim((string) $value));

        // Get field-specific salt
        $salt = $this->getBlindIndexSalt($field);

        // Generate HMAC-SHA256 hash
        return hash_hmac('sha256', $normalizedValue, $salt);
    }

    /**
     * Get the salt for blind index generation
     *
     * Salt Management Strategy:
     * 1. Uses APP_KEY as base (already secure and rotated)
     * 2. Combines with field name for field-specific salts
     * 3. Optional: Add BLIND_INDEX_SALT to .env for extra security layer
     *
     * Security Notes:
     * - Salt must remain constant for existing data to be searchable
     * - If salt changes, all blind indexes must be regenerated
     * - Store salt securely in .env file, never in version control
     *
     * @param  string  $field  Field name
     * @return string Combined salt for this field
     */
    protected function getBlindIndexSalt(string $field): string
    {
        // Get salt from configuration
        $blindIndexSalt = config('searchable-encryption.blind_index_salt', config('app.key'));
        $appKey = config('app.key');

        // Cache the salt for performance
        if (config('searchable-encryption.performance.cache_salt', true)) {
            $cacheKey = 'blind_index_salt_' . md5($field);

            return cache()->remember($cacheKey, config('searchable-encryption.performance.cache_duration', 3600), function () use ($blindIndexSalt, $field, $appKey) {
                return hash('sha256', $blindIndexSalt . $field . $appKey);
            });
        }

        // Combine salts with field name for field-specific salt
        return hash('sha256', $blindIndexSalt . $field . $appKey);
    }

    /**
     * Search for records by encrypted field value
     *
     * Uses blind index to find matching records without decrypting data
     *
     * Example:
     * User::searchByEncrypted('nric', '901234-56-7890')->first();
     *
     * @param  Builder  $query  Query builder instance
     * @param  string  $field  Encrypted field name
     * @param  mixed  $value  Plain text value to search for
     */
    public function scopeSearchByEncrypted(Builder $query, string $field, mixed $value): Builder
    {
        if (empty($value)) {
            return $query->whereNull($field);
        }

        $indexColumn = "{$field}_index";
        $blindIndex = $this->generateBlindIndex($field, $value);

        return $query->where($indexColumn, $blindIndex);
    }

    /**
     * Search for multiple encrypted field values (OR condition)
     *
     * Example:
     * User::searchByEncryptedIn('bank_account', ['123456', '789012'])->get();
     *
     * @param  Builder  $query  Query builder instance
     * @param  string  $field  Encrypted field name
     * @param  array  $values  Array of plain text values to search for
     */
    public function scopeSearchByEncryptedIn(Builder $query, string $field, array $values): Builder
    {
        $indexColumn = "{$field}_index";
        $blindIndexes = array_map(
            fn (mixed $value): string => $this->generateBlindIndex($field, $value),
            $values
        );

        return $query->whereIn($indexColumn, $blindIndexes);
    }

    /**
     * Regenerate all blind indexes for this model
     *
     * Use this method when:
     * - Salt has been rotated
     * - Migrating existing data
     * - Fixing corrupted indexes
     *
     * Example:
     * $user->regenerateBlindIndexes();
     */
    public function regenerateBlindIndexes(): bool
    {
        $this->updateBlindIndexes();

        return $this->saveQuietly(); // Save without firing events
    }

    /**
     * Batch regenerate blind indexes for all records
     *
     * Usage in Artisan command or seeder:
     * User::regenerateAllBlindIndexes();
     *
     * @param  int  $chunkSize  Process records in chunks to avoid memory issues
     * @return int Number of records updated
     */
    public static function regenerateAllBlindIndexes(int $chunkSize = 100): int
    {
        $count = 0;

        static::query()->chunk($chunkSize, function ($records) use (&$count) {
            foreach ($records as $record) {
                $record->regenerateBlindIndexes();
                $count++;
            }
        });

        return $count;
    }

    /**
     * Verify if a plain text value matches the encrypted field
     *
     * More secure than decrypting and comparing
     *
     * Example:
     * if ($user->verifyEncryptedField('nric', '901234-56-7890')) {
     *     // NRIC matches
     * }
     *
     * @param  string  $field  Encrypted field name
     * @param  mixed  $value  Plain text value to verify
     * @return bool True if value matches
     */
    public function verifyEncryptedField(string $field, mixed $value): bool
    {
        $indexColumn = "{$field}_index";
        $expectedIndex = $this->generateBlindIndex($field, $value);

        return hash_equals(
            $this->attributes[$indexColumn] ?? '',
            $expectedIndex
        );
    }
}
