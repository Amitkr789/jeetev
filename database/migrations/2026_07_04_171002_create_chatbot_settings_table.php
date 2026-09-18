<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();

            $table->string('wa_phone_number')->unique(); // E.164, e.g. 9198xxxxxxx
            $table->string('contact_name')->nullable();
            $table->string('language', 2)->nullable(); // en | hi | bn — last detected

            // ---- AI / human takeover state ----
            $table->boolean('ai_active')->default(true);
            $table->foreignId('handling_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('locked_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('escalated_at')->nullable();

            // Manual conversion only — set by admin action, never automatically.
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();

            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview')->nullable();
            $table->unsignedInteger('unread_count')->default(0);

            // WhatsApp's 24h free-form messaging window starts from the
            // customer's last inbound message — needed if you later add
            // template messages for re-engagement outside that window.
            $table->timestamp('last_customer_message_at')->nullable();

            $table->timestamps();

            $table->index(['ai_active', 'escalated_at']);
            $table->index('assigned_admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};