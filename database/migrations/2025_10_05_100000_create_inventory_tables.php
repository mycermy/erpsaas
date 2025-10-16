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
        // Inventory Items (extends Offerings)
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offering_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->nullable()->index();
            $table->enum('track_method', ['fifo', 'lifo', 'average'])->default('fifo');
            $table->integer('reorder_level')->default(0);
            $table->integer('reorder_quantity')->default(0);
            $table->foreignId('asset_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('track_batches')->default(true);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'offering_id']);
            $table->index(['company_id', 'active']);
        });

        // Warehouses/Locations
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'active']);
        });

        // Inventory Batches (for FIFO/LIFO tracking)
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number', 100)->nullable();
            $table->string('lot_number', 100)->nullable();
            $table->integer('quantity_received')->default(0);
            $table->integer('quantity_remaining')->default(0);
            $table->bigInteger('unit_cost')->default(0); // In cents for Money
            $table->date('received_date');
            $table->date('expiry_date')->nullable();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'inventory_item_id', 'warehouse_id'], 'inv_batch_comp_item_wh_idx');
            $table->index(['inventory_item_id', 'received_date', 'id'], 'inv_batch_fifo_idx');
            $table->index(['inventory_item_id', 'received_date', 'id'], 'inv_batch_lifo_idx');
        });

        // Current Stock Levels (computed/cached)
        Schema::create('inventory_stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->integer('quantity_available')->default(0);
            $table->bigInteger('average_cost')->default(0); // Weighted average
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'inventory_item_id', 'warehouse_id'], 'inv_stock_unique_idx');
            $table->index(['company_id', 'inventory_item_id'], 'inv_stock_comp_item_idx');
            $table->index(['company_id', 'warehouse_id'], 'inv_stock_comp_wh_idx');
        });

        // Movement History (audit trail)
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->string('movement_type'); // purchase, sale, adjustment, transfer_in, transfer_out, return
            $table->integer('quantity'); // Positive for IN, negative for OUT
            $table->bigInteger('unit_cost')->default(0);
            $table->bigInteger('total_cost')->default(0);
            $table->string('reference_type')->nullable(); // Polymorphic
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->date('movement_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'inventory_item_id', 'movement_date'], 'inv_mov_comp_item_date_idx');
            $table->index(['reference_type', 'reference_id'], 'inv_mov_reference_idx');
            $table->index(['movement_type', 'movement_date'], 'inv_mov_type_date_idx');
        });

        // Stock Adjustments
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('adjustment_number')->nullable();
            $table->date('adjustment_date');
            $table->enum('status', ['draft', 'approved', 'cancelled'])->default('draft');
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'adjustment_date']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('inventory_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adjustment_id')->constrained('inventory_adjustments')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_before')->default(0);
            $table->integer('quantity_after')->default(0);
            $table->integer('quantity_adjusted')->default(0); // Can be positive or negative
            $table->bigInteger('unit_cost')->default(0);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['adjustment_id', 'inventory_item_id'], 'inv_adj_item_idx');
        });

        // Warehouse Transfers
        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('transfer_number')->nullable();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->date('transfer_date');
            $table->enum('status', ['pending', 'in_transit', 'received', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'transfer_date']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('inventory_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transfer_id')->constrained('inventory_transfers')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_requested')->default(0);
            $table->integer('quantity_shipped')->default(0);
            $table->integer('quantity_received')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['transfer_id', 'inventory_item_id'], 'inv_trans_item_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_items');
        Schema::dropIfExists('inventory_transfers');
        Schema::dropIfExists('inventory_adjustment_items');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_stock_levels');
        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('inventory_items');
    }
};
