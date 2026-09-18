<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            // JSON-encoded float vector from OpenAI embeddings, used for
            // semantic retrieval by the WhatsApp chatbot. Only populated for
            // articles that are published + ai_ready (see KbEmbeddingService).
            $table->longText('embedding')->nullable()->after('ai_ready');
            // md5 of the article's toAiContext() output — lets us skip
            // re-embedding (and paying for it) when content hasn't changed.
            $table->string('embedding_hash', 32)->nullable()->after('embedding');
            $table->timestamp('embedding_updated_at')->nullable()->after('embedding_hash');
        });
    }

    public function down(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'embedding_hash', 'embedding_updated_at']);
        });
    }
};