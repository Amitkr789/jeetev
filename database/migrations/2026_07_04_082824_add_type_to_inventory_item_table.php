<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why this migration exists:
     *
     * Stock direction was previously encoded purely by the SIGN of
     * `quantity` — dealer stock-ins were positive, and
     * BillingManageController::deductStock() inserted a NEGATIVE quantity
     * to represent a sale. That's fragile: anything downstream that
     * normalizes quantity to a positive number (an unsigned column, a
     * model mutator, a cast) silently turns the deduction back into an
     * addition — which is exactly the "it inserted the product instead of
     * reducing it" bug being fixed here.
     *
     * From now on `quantity` is ALWAYS stored positive, and this `type`
     * column is the single source of truth for direction. Product::
     * getTotalQuantityAttribute() adds `in` rows and subtracts `out` rows.
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->enum('type', ['in', 'out'])->default('in')->after('quantity');
        });

        // Normalize any rows already created by the old buggy logic. We
        // deliberately key off `bill_item_id IS NOT NULL` rather than
        // `quantity < 0` — if the bug already flattened those rows to a
        // positive number, checking the sign wouldn't find them, but every
        // bill-generated row always has a bill_item_id, buggy or not.
        DB::table('inventory_items')
            ->whereNotNull('bill_item_id')
            ->update([
                'quantity' => DB::raw('ABS(quantity)'),
                'type' => 'out',
            ]);
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};