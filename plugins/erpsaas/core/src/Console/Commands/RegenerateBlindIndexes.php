<?php

namespace Erpsaas\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Regenerate Blind Indexes Command
 *
 * Regenerates blind indexes for all searchable encrypted fields.
 *
 * Use Cases:
 * - After rotating BLIND_INDEX_SALT
 * - Fixing corrupted indexes
 * - Migrating existing unindexed encrypted data
 * - After adding SearchableEncryption concern to existing models
 *
 * Usage:
 * php artisan core:regenerate-blind-indexes
 * php artisan core:regenerate-blind-indexes --model=User
 * php artisan core:regenerate-blind-indexes --chunk=50
 */
class RegenerateBlindIndexes extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'core:regenerate-blind-indexes
                            {--model= : Specific model to regenerate (e.g., User, Employee)}
                            {--chunk=100 : Number of records to process per batch}
                            {--dry-run : Preview what would be done without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Regenerate blind indexes for searchable encrypted fields (PDPA compliance)';

    /**
     * Models that use SearchableEncryption concern
     */
    protected array $models = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔐 PDPA Searchable Encryption - Blind Index Regeneration');
        $this->newLine();

        // Get registered models from config
        $this->models = config('searchable-encryption.models', []);

        // Determine which models to process
        $modelsToProcess = $this->getModelsToProcess();

        if (empty($modelsToProcess)) {
            $this->error('No models found with SearchableEncryption concern.');
            $this->info('Register models in config/searchable-encryption.php');

            return self::FAILURE;
        }

        // Show what will be processed
        $this->table(
            ['Model', 'Table', 'Count'],
            collect($modelsToProcess)->map(function ($class, $name) {
                $model = new $class;

                return [
                    $name,
                    $model->getTable(),
                    $class::count(),
                ];
            })
        );

        // Confirm before proceeding
        if (! $this->option('dry-run')) {
            if (! $this->confirm('This will regenerate blind indexes for all records. Continue?', true)) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $this->newLine();

        // Process each model
        $totalProcessed = 0;
        $totalErrors = 0;

        foreach ($modelsToProcess as $name => $class) {
            $result = $this->processModel($name, $class);
            $totalProcessed += $result['processed'];
            $totalErrors += $result['errors'];
        }

        // Summary
        $this->newLine();
        $this->info("✓ Total records processed: {$totalProcessed}");

        if ($totalErrors > 0) {
            $this->error("✗ Total errors: {$totalErrors}");

            return self::FAILURE;
        }

        $this->info('✓ All blind indexes regenerated successfully!');

        if ($this->option('dry-run')) {
            $this->warn('This was a dry run - no changes were made.');
        }

        return self::SUCCESS;
    }

    /**
     * Get models to process based on options
     */
    protected function getModelsToProcess(): array
    {
        $modelOption = $this->option('model');

        if ($modelOption) {
            if (! isset($this->models[$modelOption])) {
                $this->error("Model '{$modelOption}' not found in registered models.");
                $this->info('Available models: ' . implode(', ', array_keys($this->models)));

                return [];
            }

            return [$modelOption => $this->models[$modelOption]];
        }

        return $this->models;
    }

    /**
     * Process a single model
     */
    protected function processModel(string $name, string $class): array
    {
        $this->info("Processing {$name}...");

        $count = $class::count();

        if ($count === 0) {
            $this->warn("  No {$name} records found. Skipping.");

            return ['processed' => 0, 'errors' => 0];
        }

        $chunkSize = (int) $this->option('chunk');
        $processed = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        // Check if model uses SearchableEncryption concern
        if (! $this->usesSearchableEncryption($class)) {
            $this->error("  {$name} does not use SearchableEncryption concern. Skipping.");

            return ['processed' => 0, 'errors' => 0];
        }

        // Process in chunks
        DB::transaction(function () use ($class, $chunkSize, &$processed, &$errors, $progressBar) {
            $class::chunk($chunkSize, function ($records) use (&$processed, &$errors, $progressBar) {
                foreach ($records as $record) {
                    try {
                        if (! $this->option('dry-run')) {
                            // Regenerate blind indexes
                            $record->regenerateBlindIndexes();
                        }

                        $processed++;
                        $progressBar->advance();
                    } catch (\Exception $e) {
                        $errors++;
                        $this->error("\n  Error processing {$record->id}: {$e->getMessage()}");
                    }
                }
            });
        });

        $progressBar->finish();
        $this->newLine();

        $this->info("  ✓ Processed {$processed} {$name} records");

        if ($errors > 0) {
            $this->error("  ✗ Failed to process {$errors} records");
        }

        return ['processed' => $processed, 'errors' => $errors];
    }

    /**
     * Check if model uses SearchableEncryption concern
     */
    protected function usesSearchableEncryption(string $class): bool
    {
        $traits = class_uses_recursive($class);

        return in_array(\Erpsaas\Core\Concerns\SearchableEncryption::class, $traits);
    }
}
