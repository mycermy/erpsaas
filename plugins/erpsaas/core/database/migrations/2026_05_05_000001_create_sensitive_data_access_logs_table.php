<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add Searchable Encryption Support
 *
 * Creates audit log table for PDPA compliance tracking.
 *
 * Note: Fields should be added to specific models (User, Employee, etc.)
 * using separate migrations in their respective modules.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create audit log table for PDPA compliance
        Schema::create('sensitive_data_access_logs', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('field_name');
            $table->unsignedBigInteger('accessed_by_user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('action'); // 'read', 'write', 'search'
            $table->text('reason')->nullable(); // Business justification
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index('accessed_by_user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensitive_data_access_logs');
    }
};
