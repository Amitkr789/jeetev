<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LeadAttachment extends Model
{
    protected $fillable = [
        'lead_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    // Frontend JSON me seedhe available rehne ke liye (a.file_url, a.file_size_label).
    protected $appends = ['file_url', 'file_size_label'];

    /* ---------------- Relationships ---------------- */

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by');
    }

    /* ---------------- Scopes ----------------
       super_admin -> lead ke saare attachments dikhte hai.
       baaki sab admins -> sirf apne khud ke upload kiye hue attachments. */
    public function scopeVisibleTo(Builder $query, Admin $admin): Builder
    {
        if ($admin->role === 'super_admin') {
            return $query;
        }

        return $query->where('uploaded_by', $admin->id);
    }

    /* ---------------- Accessors ---------------- */

    public function getFileUrlAttribute(): string
{
    return Storage::url($this->file_path);
}

    public function getFileSizeLabelAttribute(): string
    {
        $bytes = (float) $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}