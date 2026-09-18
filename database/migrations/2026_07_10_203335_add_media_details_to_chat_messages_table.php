<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_messages', 'media_filename')) {
                $table->string('media_filename')->nullable()->after('media_mime_type');
            }
            if (!Schema::hasColumn('chat_messages', 'media_size')) {
                $table->unsignedBigInteger('media_size')->nullable()->after('media_filename');
            }
        });

        // yaha add karna hai — ENUM me naye values allow karne ke liye
        DB::statement("ALTER TABLE chat_messages MODIFY type ENUM('text','system','image','audio','video','document') NOT NULL DEFAULT 'text'");
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['media_filename', 'media_size']);
        });

        // rollback me ENUM wapas purane values pe (agar chahiye)
        DB::statement("ALTER TABLE chat_messages MODIFY type ENUM('text','system') NOT NULL DEFAULT 'text'");
    }
};
