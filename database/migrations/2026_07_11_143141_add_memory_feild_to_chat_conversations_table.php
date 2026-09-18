<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_conversations', 'state')) {
                $table->string('state', 60)->nullable();
            }
            if (! Schema::hasColumn('chat_conversations', 'state_data')) {
                $table->json('state_data')->nullable();
            }
            if (! Schema::hasColumn('chat_conversations', 'focus_product_id')) {
                $table->foreignId('focus_product_id')->nullable()->constrained('products')->nullOnDelete();
            }
            if (! Schema::hasColumn('chat_conversations', 'pending_disambiguation')) {
                $table->json('pending_disambiguation')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            foreach (['state', 'state_data', 'focus_product_id', 'pending_disambiguation'] as $col) {
                if (Schema::hasColumn('chat_conversations', $col)) {
                    if ($col === 'focus_product_id') {
                        $table->dropConstrainedForeignId($col);
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });
    }
};
