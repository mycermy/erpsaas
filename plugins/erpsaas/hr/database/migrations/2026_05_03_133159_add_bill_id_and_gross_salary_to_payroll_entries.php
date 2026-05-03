<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->foreignId('bill_id')->nullable()->after('transaction_id')->constrained('bills')->nullOnDelete();
            $table->decimal('gross_salary', 20, 4)->nullable()->after('bill_id');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bill_id');
            $table->dropColumn('gross_salary');
        });
    }
};
