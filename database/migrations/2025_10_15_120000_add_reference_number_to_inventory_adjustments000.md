<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_adjustments', 'reference_number')) {
                $table->string('reference_number')->nullable()->after('adjustment_number');
            }
        });

        // Backfill existing rows
        DB::table('inventory_adjustments')
            ->whereNotNull('adjustment_number')
            ->update(['reference_number' => DB::raw('adjustment_number')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_adjustments', 'reference_number')) {
                $table->dropColumn('reference_number');
            }
        });
    }
};
