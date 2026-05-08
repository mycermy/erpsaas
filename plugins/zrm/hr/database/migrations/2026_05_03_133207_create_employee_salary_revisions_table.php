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
        Schema::create('employee_salary_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->text('base_salary_amount');
            $table->string('base_salary_amount_index', 64)->nullable()->index();
            $table->date('effective_from');
            $table->string('reason')->nullable(); // kpi_increment, promotion, adjustment, correction
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // PDPA Audit Fields
            $table->timestamp('sensitive_data_last_accessed_at')->nullable();
            $table->foreignId('sensitive_data_accessed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['employee_id', 'effective_from']);
            $table->index('sensitive_data_last_accessed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_revisions');
    }
};
