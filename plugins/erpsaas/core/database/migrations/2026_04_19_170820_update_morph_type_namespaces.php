<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rename old App\Models\* morph type strings to the current Erpsaas\Core\* namespace
     * across all polymorphic tables after the app/ → plugins/erpsaas/core/ namespace migration.
     */
    public function up(): void
    {
        $replacements = [
            'App\\Models\\Common\\Client' => 'Erpsaas\\Core\\Models\\Common\\Client',
            'App\\Models\\Common\\Vendor' => 'Erpsaas\\Core\\Models\\Common\\Vendor',
            'App\\Models\\Setting\\CompanyProfile' => 'Erpsaas\\Core\\Models\\Setting\\CompanyProfile',
        ];

        $morphColumns = [
            'transactions' => 'payeeable_type',
            'contacts' => 'contactable_type',
            'addresses' => 'addressable_type',
        ];

        foreach ($morphColumns as $table => $column) {
            foreach ($replacements as $old => $new) {
                DB::table($table)
                    ->where($column, $old)
                    ->update([$column => $new]);
            }
        }
    }

    public function down(): void
    {
        $replacements = [
            'Erpsaas\\Core\\Models\\Common\\Client' => 'App\\Models\\Common\\Client',
            'Erpsaas\\Core\\Models\\Common\\Vendor' => 'App\\Models\\Common\\Vendor',
            'Erpsaas\\Core\\Models\\Setting\\CompanyProfile' => 'App\\Models\\Setting\\CompanyProfile',
        ];

        $morphColumns = [
            'transactions' => 'payeeable_type',
            'contacts' => 'contactable_type',
            'addresses' => 'addressable_type',
        ];

        foreach ($morphColumns as $table => $column) {
            foreach ($replacements as $old => $new) {
                DB::table($table)
                    ->where($column, $old)
                    ->update([$column => $new]);
            }
        }
    }
};
