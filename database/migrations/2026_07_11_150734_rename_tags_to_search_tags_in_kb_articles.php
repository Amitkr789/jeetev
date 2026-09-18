<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::table('kb_articles', function (Blueprint $table) {
        if (Schema::hasColumn('kb_articles', 'tags')) {
            $table->renameColumn('tags', 'search_tags');
        }
    });
}

public function down(): void
{
    Schema::table('kb_articles', function (Blueprint $table) {
        if (Schema::hasColumn('kb_articles', 'search_tags')) {
            $table->renameColumn('search_tags', 'tags');
        }
    });
}
};
