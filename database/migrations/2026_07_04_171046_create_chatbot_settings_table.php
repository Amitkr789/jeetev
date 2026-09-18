<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_settings', function (Blueprint $table) {
            $table->id();

            // ---- Master switch ----
            // When false, the AI NEVER auto-replies on any conversation,
            // system-wide, regardless of per-conversation ai_active.
            $table->boolean('ai_globally_enabled')->default(true);

            // ---- OpenAI ----
            $table->text('openai_api_key')->nullable();
            $table->string('openai_model')->default('gpt-4o-mini');
            $table->string('openai_embedding_model')->default('text-embedding-3-small');
            $table->unsignedTinyInteger('confidence_threshold')->default(60);
            $table->text('system_prompt')->nullable();

            // ---- Guaranteed fallback replies (never model-generated) ----
            $table->string('fallback_message_en')->default('Our executive will contact you.');
            $table->string('fallback_message_hi')->default('हमारा प्रतिनिधि आपसे संपर्क करेगा।');
            $table->string('fallback_message_bn')->default('আমাদের প্রতিনিধি আপনার সাথে যোগাযোগ করবেন।');

            // ---- WhatsApp Cloud API ----
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->string('whatsapp_business_account_id')->nullable();
            $table->text('whatsapp_access_token')->nullable();
            $table->string('whatsapp_webhook_verify_token')->nullable();
            $table->string('whatsapp_api_version')->default('v20.0');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_settings');
    }
};