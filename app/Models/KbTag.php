<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class KbTag extends Model
{
    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::creating(function (KbTag $tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name) ?: 'tag';
            }
        });
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(KbArticle::class, 'kb_article_tag');
    }

    /**
     * Find an existing tag by name (case-insensitive) or create a new one.
     * Used when saving free-form tag chips typed into the article form.
     */
    public static function findOrCreateByName(string $name): self
    {
        $name = trim($name);

        return static::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? static::create(['name' => $name]);
    }
}
