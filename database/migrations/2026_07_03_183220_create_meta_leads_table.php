<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_leads', function (Blueprint $table) {
            $table->id();

            // ---- Meta / Facebook identifiers ----
            $table->string('leadgen_id')->unique();   // Meta's lead ID — also our idempotency key
            $table->string('page_id')->nullable();
            $table->string('form_id')->nullable();
            $table->string('form_name')->nullable();
            $table->string('ad_id')->nullable();
            $table->string('ad_name')->nullable();
            $table->string('adset_id')->nullable();
            $table->string('adset_name')->nullable();
            $table->string('campaign_id')->nullable();
            $table->string('campaign_name')->nullable();

            // ---- Best-effort mapped fields ----
            // Kept in the same shape as leads.* so the meta-leads table
            // looks/works exactly like the normal Leads page.
            $table->string('customer_name')->nullable();
            $table->string('product_name')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // Everything Meta actually sent, untouched — covers custom
            // form questions that don't map to a known column above.
            $table->json('raw_payload')->nullable();

            $table->enum('status', ['new', 'converted', 'discarded'])->default('new');

            // Set once an admin converts this into a real lead.
            $table->foreignId('converted_lead_id')->nullable()->constrained('leads')->nullOnDelete();

            $table->timestamp('received_at')->nullable(); // Meta's created_time for the submission

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_leads');
    }
};