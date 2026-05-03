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
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('base_salary_amount', 20, 4)->nullable()->after('department');
            $table->date('salary_effective_from')->nullable()->after('base_salary_amount');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['base_salary_amount', 'salary_effective_from']);
        });
    }
};
