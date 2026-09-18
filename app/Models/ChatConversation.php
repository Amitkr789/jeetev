<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    protected $fillable = [
        'wa_phone_number',
        'contact_name',
        'language',
        'ai_active',
        'handling_admin_id',
        'assigned_admin_id',
        'locked_by_admin_id',
        'locked_at',
        'escalated_at',
        'lead_id',
        'last_message_at',
        'last_message_preview',
        'unread_count',
        'last_customer_message_at',
    ];

    protected $casts = [
        'ai_active' => 'boolean',
        'locked_at' => 'datetime',
        'escalated_at' => 'datetime',
        'last_message_at' => 'datetime',
        'last_customer_message_at' => 'datetime',
        'unread_count' => 'integer',
        'state_data'=>'array',
    ];



    /* ---------------- Relationships ---------------- */

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')->orderBy('created_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ChatNote::class, 'conversation_id')->orderByDesc('created_at');
    }

    public function handlingAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'handling_admin_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }

    public function lockedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'locked_by_admin_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /* ---------------- Scopes ---------------- */

    public function scopeEscalated(Builder $query): Builder
    {
        return $query->whereNotNull('escalated_at');
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_admin_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('wa_phone_number', 'like', "%{$term}%")
                ->orWhere('contact_name', 'like', "%{$term}%")
                ->orWhere('last_message_preview', 'like', "%{$term}%");
        });
    }

    /* ---------------- Accessors ---------------- */

    /**
     * Single source of truth for what badge the admin UI shows. Derived
     * from state flags rather than a separate status column so it can
     * never drift out of sync with ai_active / handling_admin_id / etc.
     */
    public function getDisplayStatusAttribute(): string
    {
        if ($this->escalated_at && $this->ai_active === false && ! $this->handling_admin_id) {
            return 'escalated';
        }
        if ($this->handling_admin_id) {
            return 'human';
        }
        if ($this->ai_active) {
            return 'ai';
        }

        return 'paused';
    }

    public function getIsLockedAttribute(): bool
    {
        return ! is_null($this->locked_by_admin_id);
    }
}
