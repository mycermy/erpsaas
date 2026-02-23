<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DbEmptyCommand extends Command
{
    protected $signature = 'db:empty {--all : Empty all tables} {--tables= : Comma-separated list of tables to empty} {--force : Skip confirmation} {--preserve= : Comma-separated list of tables to skip} {--dry-run : Show tables that would be emptied without truncating}';

    protected $description = 'Empty (truncate) database tables. Use with caution.';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run in production without --force.');

            return Command::FAILURE;
        }

        $all = $this->option('all');
        $tablesOption = $this->option('tables');
        $preserveOption = $this->option('preserve');

        if (! $all && ! $tablesOption) {
            $this->error('You must provide --all or --tables.');

            return Command::FAILURE;
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        // Determine tables to truncate
        $tables = [];

        if ($all) {
            if ($driver === 'mysql') {
                $tables = array_map('current', DB::select('SHOW TABLES'));
            } elseif ($driver === 'sqlite') {
                $tables = array_map('current', DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';"));
            } elseif ($driver === 'pgsql') {
                $tables = array_column(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public';"), 'tablename');
            } else {
                $this->error('Unsupported database driver: ' . $driver);

                return Command::FAILURE;
            }
        } else {
            $tables = array_map('trim', explode(',', $tablesOption));
        }

        // Handle preserve list
        $preserve = $preserveOption ? array_map('trim', explode(',', $preserveOption)) : [];
        $tables = array_values(array_diff($tables, $preserve));

        if (empty($tables)) {
            $this->info('No tables to empty.');

            return Command::SUCCESS;
        }

        $this->info('Will empty the following tables: ' . implode(', ', $tables));

        if ($this->option('dry-run')) {
            $this->info('Dry-run: no tables were modified.');

            return Command::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->confirm('Are you sure you want to continue? This will delete ALL data from the above tables.')) {
                $this->info('Aborted.');

                return Command::SUCCESS;
            }

            $confirmation = $this->ask('Type "EMPTY" to permanently delete the data from these tables');
            if (strtoupper(trim((string) $confirmation)) !== 'EMPTY') {
                $this->info('Aborted. Confirmation token did not match.');

                return Command::SUCCESS;
            }
        }

        DB::beginTransaction();

        try {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            } elseif ($driver === 'pgsql') {
                // No global setting here; we'll truncate with cascade
            }

            foreach ($tables as $table) {
                $this->line('  - Emptying ' . $table);

                if ($driver === 'mysql') {
                    DB::table($table)->truncate();
                } elseif ($driver === 'sqlite') {
                    DB::table($table)->truncate();
                } elseif ($driver === 'pgsql') {
                    DB::statement("TRUNCATE TABLE \"{$table}\" RESTART IDENTITY CASCADE;");
                } else {
                    DB::table($table)->delete();
                }
            }

            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            $this->error('Failed: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $this->info('Done.');

        return Command::SUCCESS;
    }
}
