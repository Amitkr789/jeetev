<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'image_path',
        'sort_order',
    ];

    /**
     * Always include the public URL when this model is serialized to JSON —
     * the frontend gallery only ever deals with `url`, never the raw path.
     */
    protected $appends = ['url'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

  public function getUrlAttribute(): string
{
    return Storage::url($this->image_path);
}
}