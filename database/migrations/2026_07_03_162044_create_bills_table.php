<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->unique(); // e.g. INV-0001 / QUO-0001 / PI-0001
            $table->string('bill_type');              // invoice | quotation | pi

            // Customer — either copied from a Lead or typed fresh. Stored as a
            // snapshot on the bill itself (not just a foreign key) so old bills
            // stay accurate even if the lead's info changes later.
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('customer_name');
            $table->string('company_name')->nullable();
            $table->string('gst_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('billing_address')->nullable();
            $table->text('shipping_address')->nullable();
            $table->boolean('ship_same_as_billing')->default(true);

            $table->date('billing_date');
            $table->date('valid_till')->nullable();

            $table->foreignId('billing_header_id')->nullable()->constrained('billing_headers')->nullOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();

            // Courier / shipping charge (its own single tax, e.g. IGST 18%)
            $table->string('courier_name')->nullable();
            $table->decimal('courier_price', 12, 2)->default(0);
            $table->string('courier_tax_type')->nullable(); // CGST/SGST/IGST/UGST
            $table->decimal('courier_tax_percent', 5, 2)->default(0);
            $table->decimal('courier_tax_amount', 12, 2)->default(0);

            $table->decimal('subtotal', 12, 2)->default(0);   // items + courier, before tax
            $table->decimal('total_tax', 12, 2)->default(0);  // items tax + courier tax
            $table->decimal('grand_total', 12, 2)->default(0);

            // Whether this bill has already deducted inventory stock (only
            // true for bill_type = invoice). Lets us safely reverse it on
            // edit/delete without double-counting.
            $table->boolean('stock_deducted')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};