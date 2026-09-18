<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
/**
 * Additive-only migration — safe to run even if some of these columns
 * already exist elsewhere in your schema (each is guarded individually).
 * `keywords` / `tags` power Phase 1 hybrid search's 20%/10% weights.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            if (! Schema::hasColumn('kb_articles', 'keywords')) {
                $table->json('keywords')->nullable()->after('title');
            }
            if (! Schema::hasColumn('kb_articles', 'tags')) {
                $table->json('tags')->nullable()->after('keywords');
            }
        });

        // Composite index used by KbEmbeddingService::topMatches() —
        // matches the existing aiReady() scope (status + ai_ready).
        Schema::table('kb_articles', function (Blueprint $table) {
            if (! $this->indexExists('kb_articles', 'kb_articles_status_ai_ready_index')) {
                $table->index(['status', 'ai_ready'], 'kb_articles_status_ai_ready_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            if (Schema::hasColumn('kb_articles', 'keywords')) {
                $table->dropColumn('keywords');
            }
            if (Schema::hasColumn('kb_articles', 'tags')) {
                $table->dropColumn('tags');
            }
            if ($this->indexExists('kb_articles', 'kb_articles_status_ai_ready_index')) {
                $table->dropIndex('kb_articles_status_ai_ready_index');
            }
        });
    }

   private function indexExists(string $table, string $indexName): bool
{
    $database = DB::getDatabaseName();

    $result = DB::select("
        SELECT COUNT(*) AS cnt
        FROM information_schema.statistics
        WHERE table_schema = ?
          AND table_name = ?
          AND index_name = ?
    ", [
        $database,
        $table,
        $indexName,
    ]);

    return isset($result[0]) && $result[0]->cnt > 0;
}
};
