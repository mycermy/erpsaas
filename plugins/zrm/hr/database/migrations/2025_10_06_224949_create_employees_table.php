<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comprehensive Employees Table Migration
 *
 * Malaysia Personal Data Protection Act (PDPA) Compliance
 *
 * This migration creates the complete employees table with:
 * - Basic employee information
 * - PDPA-compliant encrypted fields with blind indexes
 * - Malaysian-specific fields (NRIC, EPF, SOCSO, tax numbers)
 * - Bank account details (encrypted)
 * - Emergency contact information (encrypted)
 * - Audit trail for sensitive data access
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Company & Employee Identification
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('employee_number');
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->string('name')->nullable();
            $table->boolean('separate_work_address')->default(false);

            // NRIC (Malaysian IC) - Highly Sensitive under PDPA
            $table->text('nric')->nullable();
            $table->string('nric_index', 64)->nullable()->index();

            // Passport Number (for foreign workers)
            $table->text('passport_number')->nullable();
            $table->string('passport_number_index', 64)->nullable()->index();

            // Bank Account Details - Financial Information (PDPA Sensitive)
            $table->text('bank_account_number')->nullable();
            $table->string('bank_account_number_index', 64)->nullable()->index();
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();

            // Salary Information (Encrypted for PDPA)
            $table->text('encrypted_base_salary')->nullable();
            $table->string('encrypted_base_salary_index', 64)->nullable()->index();
            $table->date('salary_effective_from')->nullable();

            // Malaysian Social Security Numbers
            // EPF Number (Employees Provident Fund / KWSP)
            $table->text('epf_number')->nullable();
            $table->string('epf_number_index', 64)->nullable()->index();

            // SOCSO Number (Social Security Organisation / PERKESO)
            $table->text('socso_number')->nullable();
            $table->string('socso_number_index', 64)->nullable()->index();

            // Income Tax Number (LHDN)
            $table->text('income_tax_number')->nullable();
            $table->string('income_tax_number_index', 64)->nullable()->index();

            // Emergency Contact Information
            $table->string('emergency_contact_name')->nullable();
            $table->text('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_phone_index', 64)->nullable()->index();
            $table->string('emergency_contact_relationship')->nullable();

            // Audit Trail
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // PDPA Audit Fields
            $table->timestamp('sensitive_data_last_accessed_at')->nullable();
            $table->foreignId('sensitive_data_accessed_by')->nullable()->constrained('users')->nullOnDelete();

            // Indexes for performance and compliance queries
            $table->index('sensitive_data_last_accessed_at');
            $table->index(['company_id', 'employee_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
