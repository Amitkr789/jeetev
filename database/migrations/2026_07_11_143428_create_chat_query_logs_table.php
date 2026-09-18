<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_query_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained('chat_conversations')->nullOnDelete();
            $table->text('question');
            $table->text('normalized_question')->nullable();
            $table->text('answer')->nullable();
            $table->float('final_score')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('confidence_tier', 10)->nullable(); // high | medium | low
            $table->json('kb_article_ids')->nullable();
            $table->json('product_ids')->nullable();
            $table->boolean('used_ai')->default(false);
            $table->boolean('escalated')->default(false);
            $table->string('language', 5)->nullable();
            $table->string('intent', 40)->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->timestamps();

            // Dashboard queries filter/group by these constantly.
            $table->index(['created_at']);
            $table->index(['escalated']);
            $table->index(['intent']);
            $table->index(['confidence_tier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_query_logs');
    }
};
