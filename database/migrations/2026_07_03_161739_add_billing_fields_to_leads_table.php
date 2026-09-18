<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The Leads module never collected company/GST/email/address — but the
 * billing screen needs to auto-fill those from a lead when they exist and
 * just leave them blank (user fills manually) when they don't. So we add
 * them here as fully nullable columns. Nothing about the existing Leads
 * page breaks; these columns simply aren't touched by it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('customer_name');
            $table->string('gst_number')->nullable()->after('company_name');
            $table->string('email')->nullable()->after('phone');
            $table->text('billing_address')->nullable()->after('email');
            $table->text('shipping_address')->nullable()->after('billing_address');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'gst_number', 'email', 'billing_address', 'shipping_address']);
        });
    }
};