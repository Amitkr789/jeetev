<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained('bills')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            // Snapshot fields — kept even if the product is edited/removed later.
            $table->string('product_name');
            $table->string('hsn_sku')->nullable();
            $table->string('unit')->nullable();

            $table->decimal('price', 12, 2);
            $table->decimal('quantity', 12, 2);
            $table->decimal('taxable_amount', 12, 2); // price * quantity

            // Multiple taxes per line, each with its own %, e.g.
            // [{"type":"CGST","percent":9,"amount":45},{"type":"SGST","percent":9,"amount":45}]
            $table->json('taxes')->nullable();
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2); // taxable_amount + tax_amount

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_items');
    }
};