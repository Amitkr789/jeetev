<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbArticleVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'article_id',
        'version_number',
        'title',
        'question',
        'answer',
        'status_at_time',
        'edited_by',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(KbArticle::class, 'article_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'edited_by');
    }
}
