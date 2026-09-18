<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->unsignedBigInteger('bill_item_id')->nullable()->after('dealer_id');

            $table->foreign('bill_item_id')
                  ->references('id')
                  ->on('bill_items')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropForeign(['bill_item_id']);
            $table->dropColumn('bill_item_id');
        });
    }
};