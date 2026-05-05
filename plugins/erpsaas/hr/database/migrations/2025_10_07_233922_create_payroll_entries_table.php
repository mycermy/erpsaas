<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comprehensive Payroll Entries Table Migration
 *
 * Creates the complete payroll entries table with all fields
 * for managing employee payroll processing and records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_entries', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Entry Identification
            $table->string('entry_number');

            // Payroll Period
            $table->date('from_date');
            $table->date('to_date');

            // Employee & Salary Structure
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('salary_structure_id')->constrained('salary_structures')->onDelete('cascade');

            // Accounting Integration
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->onDelete('cascade');
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();

            // Salary Amount (Encrypted for PDPA)
            $table->text('gross_salary')->nullable();
            $table->string('gross_salary_index', 64)->nullable()->index();

            // Company Reference
            $table->foreignId('company_id')->constrained()->onDelete('cascade');

            // Audit Trail
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // PDPA Audit Fields
            $table->timestamp('sensitive_data_last_accessed_at')->nullable();
            $table->foreignId('sensitive_data_accessed_by')->nullable()->constrained('users')->nullOnDelete();

            // Indexes for performance
            $table->index(['company_id', 'entry_number']);
            $table->index(['employee_id', 'from_date', 'to_date']);
            $table->index('sensitive_data_last_accessed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
    }
};
