<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            $table->string('customer_name');
            $table->string('product_name');
            $table->string('whatsapp')->nullable();
            $table->string('phone');

            // Website, Referral, Cold Call, Social Media, Walk-in, Email Campaign, Trade Show, Other
            $table->string('source')->default('Website');
            // Facebook, Instagram, LinkedIn, WhatsApp, Other — only used when source = Social Media
            $table->string('social_platform')->nullable();

            $table->enum('lead_type', ['hot', 'cold', 'warm', 'converted', 'dealer'])->default('warm');

            $table->boolean('is_pinned')->default(false);

            // Adjust 'admins' below if your panel users table is named differently.
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();

            $table->timestamps();

            $table->index(['lead_type', 'source']);
            $table->index('is_pinned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};