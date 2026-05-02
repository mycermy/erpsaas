<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_part_salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_structure_id')->constrained('salary_structures')->onDelete('cascade');
            $table->foreignId('salary_part_id')->constrained('salary_parts')->onDelete('cascade');
            $table->decimal('amount', 15, 3)->nullable();
            $table->string('group')->default('base_salary');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_part_salary_structures');
    }
};
