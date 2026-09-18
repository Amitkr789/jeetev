<?php

// database/migrations/2026_07_10_000001_add_product_kb_fields.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            // "Hard description" — detailed spec/overview text, separate
            // from the short Q&A `answer`. Nullable so non-product types
            // are unaffected.
            $table->text('detailed_description')->nullable()->after('answer');
        });

        Schema::table('kb_media', function (Blueprint $table) {
            // Lets the KB tell a catalog/brochure PDF apart from any other
            // attachment, so the AI context can call it out by name.
            $table->string('purpose')->default('general')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->dropColumn('detailed_description');
        });
        Schema::table('kb_media', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
