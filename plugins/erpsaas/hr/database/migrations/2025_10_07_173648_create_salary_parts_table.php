<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_parts', function (Blueprint $table) {
            $table->id();
            $table->string('part_number');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('base_salary');
            $table->string('basis')->default('fixed');
            $table->boolean('in_net_salary')->default(true);
            $table->decimal('amount', 15, 3);
            $table->foreignId('debit_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('credit_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_parts');
    }
};
