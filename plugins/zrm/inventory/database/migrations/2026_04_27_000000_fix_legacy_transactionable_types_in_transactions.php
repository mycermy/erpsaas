<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('transactions')
            ->where('transactionable_type', 'App\\Models\\Accounting\\Invoice')
            ->update(['transactionable_type' => 'Erpsaas\\Accounts\\Models\\Accounting\\Invoice']);

        DB::table('transactions')
            ->where('transactionable_type', 'App\\Models\\Accounting\\Bill')
            ->update(['transactionable_type' => 'Erpsaas\\Accounts\\Models\\Accounting\\Bill']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('transactions')
            ->where('transactionable_type', 'Erpsaas\\Accounts\\Models\\Accounting\\Invoice')
            ->update(['transactionable_type' => 'App\\Models\\Accounting\\Invoice']);

        DB::table('transactions')
            ->where('transactionable_type', 'Erpsaas\\Accounts\\Models\\Accounting\\Bill')
            ->update(['transactionable_type' => 'App\\Models\\Accounting\\Bill']);
    }
};
