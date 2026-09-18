<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class KbMedia extends Model
{
    protected $fillable = [
    'article_id',
    'type',
    'purpose',        // NEW: 'general' | 'catalog'
    'file_name',
    'file_path',
    'mime_type',
    'file_size',
    'uploaded_by',
];

    protected $appends = ['url'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(KbArticle::class, 'article_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
