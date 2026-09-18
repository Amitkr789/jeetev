<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TaskAttachment extends Model
{
    protected $fillable = ['task_id', 'admin_id', 'original_name', 'file_path', 'file_size', 'mime_type'];

    protected $appends = ['url', 'human_size'];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function getUrlAttribute(): string
{
    return asset('storage/' . ltrim($this->file_path, '/'));
}
    public function getHumanSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }
}
