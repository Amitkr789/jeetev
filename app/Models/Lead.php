<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Lead extends Model
{
    public const LEAD_TYPES = ['hot', 'cold', 'warm', 'converted', 'dealer'];
    protected $fillable = [
        'customer_name',
        'company_name',
        'gst_number',
        'product_name',
        'whatsapp',
        'phone',
        'email',
        'billing_address',
        'shipping_address',
        'source',
        'social_platform',
        'lead_type',
        'is_pinned',
        'created_by',
    ];
    protected $casts = [
        'is_pinned' => 'boolean',
    ];
    /* ---------------- Relationships ---------------- */
    public function creator(): BelongsTo
    {
        // Swap Admin::class for your actual users/admins model if different.
        return $this->belongsTo(Admin::class, 'created_by');
    }
    public function assignedAdmins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'lead_admin')->withTimestamps();
    }
    public function reminders(): HasMany
    {
        return $this->hasMany(LeadReminder::class)->orderByDesc('id');
    }
    public function attachments(): HasMany
    {
        return $this->hasMany(LeadAttachment::class)->orderByDesc('id');
    }
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }
    /* ---------------- Scopes ---------------- */
    /**
     * Restrict leads to what the given admin is allowed to see.
     * super_admin -> everything.
     * everyone else -> leads they created OR leads assigned to them.
     */
    public function scopeVisibleTo(Builder $query, Admin $admin): Builder
    {
        if ($admin->role === 'super_admin') {
            return $query;
        }
        return $query->where(function (Builder $q) use ($admin) {
            $q->where('created_by', $admin->id)
                ->orWhereHas('assignedAdmins', fn (Builder $q2) => $q2->where('admins.id', $admin->id));
        });
    }
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }
        return $query->where(function (Builder $q) use ($term) {
            $q->where('customer_name', 'like', "%{$term}%")
                ->orWhere('product_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('whatsapp', 'like', "%{$term}%");
        });
    }
    /* ---------------- Accessors ---------------- */
    public function getLeadTypeLabelAttribute(): string
    {
        return match ($this->lead_type) {
            'hot' => 'Hot',
            'cold' => 'Cold',
            'warm' => 'Warm',
            'converted' => 'Converted',
            'dealer' => 'Dealer',
            default => ucfirst($this->lead_type),
        };
    }
}