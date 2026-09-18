<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE lead_reminders
            MODIFY status ENUM('scheduled','missed','silent','completed')
            NOT NULL DEFAULT 'scheduled'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE lead_reminders
            MODIFY status ENUM('scheduled','missed','completed')
            NOT NULL DEFAULT 'scheduled'
        ");
    }
};