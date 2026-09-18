<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadReminder extends Model
{
    protected $fillable = [
        'lead_id',
        'type',
        'note',
        'reminder_date',
        'reminder_time',
        'appointment_type',
        'status',
        'created_by',
    ];

    protected $casts = [
        'reminder_date' => 'date',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Restrict reminders to what the given admin is allowed to see.
     * super_admin -> every reminder.
     * everyone else -> only reminders THEY personally created, even if
     * the parent lead itself is visible to them (own or assigned).
     */
    public function scopeVisibleTo(Builder $query, Admin $admin): Builder
    {
        if ($admin->role === 'super_admin') {
            return $query;
        }

        return $query->where('created_by', $admin->id);
    }

    /** Actual date/time follow-ups — these are the ones that alert and appear on the Reminders page. */
    public function scopeReminders(Builder $query): Builder
    {
        return $query->where('type', 'reminder');
    }

    /** Plain notes against a lead — no date/time, never alert. */
    public function scopeNotes(Builder $query): Builder
    {
        return $query->where('type', 'note');
    }

    public function getIsMissedAttribute(): bool
    {
        if (in_array($this->status, ['completed', 'silent'], true) || ! $this->reminder_date) {
            return false;
        }

        $dateTime = Carbon::parse(
            $this->reminder_date->format('Y-m-d') . ' ' . ($this->reminder_time ?? '23:59:59')
        );

        return $dateTime->isPast();
    }
}
