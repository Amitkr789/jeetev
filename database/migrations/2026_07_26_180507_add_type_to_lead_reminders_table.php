<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_reminders', function (Blueprint $table) {
            // 'reminder' = has an (optional) date/time + appointment type, shows up
            // in the alarm/bell poll and on the standalone Reminders page.
            // 'note'     = just text against the lead — no date/time, never alerts,
            // only shown in the lead detail panel's own "Notes" list.
            $table->string('type', 20)->default('reminder')->after('lead_id');
        });
    }

    public function down(): void
    {
        Schema::table('lead_reminders', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
