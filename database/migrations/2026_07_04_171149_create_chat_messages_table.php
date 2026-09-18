<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();

            $table->enum('direction', ['inbound', 'outbound']);
            $table->enum('sender_type', ['customer', 'ai', 'admin', 'system']);
            $table->foreignId('sender_admin_id')->nullable()->constrained('admins')->nullOnDelete();

            $table->string('type')->default('text'); // text|image|document|audio|video|location|system
            $table->text('body')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_mime_type')->nullable();

            $table->string('wa_message_id')->nullable()->index();
            $table->string('wa_status')->nullable(); // sent|delivered|read|failed

            // Only ever set on sender_type = ai. This is what the
            // "AI confidence score" feature and escalation logic are built on.
            $table->unsignedTinyInteger('ai_confidence')->nullable();
            $table->json('kb_article_ids')->nullable(); // which KB articles grounded this reply
            $table->string('language', 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};