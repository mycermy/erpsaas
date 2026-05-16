<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustment_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('adjustment_id')->nullable()->constrained('inventory_adjustments')->nullOnDelete();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name');
            $table->enum('status', ['processing', 'completed', 'completed_with_errors', 'failed'])->default('processing');
            $table->enum('unknown_sku_strategy', ['flag', 'auto_create'])->default('flag');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('error_summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'inv_adj_import_comp_status_idx');
            $table->index(['warehouse_id', 'started_at'], 'inv_adj_import_wh_started_idx');
        });

        Schema::create('inventory_adjustment_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('inventory_adjustment_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('sku')->nullable();
            $table->string('item_name')->nullable();
            $table->integer('quantity_before')->nullable();
            $table->integer('quantity_counted')->nullable();
            $table->integer('quantity_adjusted')->nullable();
            $table->enum('action', ['adjusted', 'created_item', 'flagged', 'skipped'])->default('adjusted');
            $table->enum('status', ['success', 'failed', 'skipped'])->default('success');
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['import_id', 'status'], 'inv_adj_import_row_import_status_idx');
            $table->index(['import_id', 'row_number'], 'inv_adj_import_row_import_row_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_import_rows');
        Schema::dropIfExists('inventory_adjustment_imports');
    }
};
