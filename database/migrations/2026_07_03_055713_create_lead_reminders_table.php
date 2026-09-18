<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();

            $table->text('note');
            $table->date('reminder_date')->nullable();
            $table->time('reminder_time')->nullable();

            // whatsapp | meeting
            $table->enum('appointment_type', ['whatsapp', 'meeting'])->nullable();

            // scheduled -> missed (auto, once date/time passes) -> completed (manual)
            $table->enum('status', ['scheduled', 'missed', 'completed'])->default('scheduled');

            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['reminder_date', 'reminder_time', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_reminders');
    }
};