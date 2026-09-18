<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KbArticle extends Model
{
    public const TYPES = ['faq', 'policy', 'pricing', 'product', 'service'];
    public const STATUSES = ['draft', 'pending_approval', 'published', 'archived'];

    protected $fillable = [
    'category_id',
    'type',
    'title',
    'question',
    'answer',
    'detailed_description',   // NEW
    'product_id',
    'price_override',
    'status',
    'version',
    'ai_ready',
    'priority',
    'created_by',
    'approved_by',
    'approved_at',
    'published_at',
    'search_tags',
    'keywords'
];

    protected $casts = [
        'ai_ready' => 'boolean',
        'price_override' => 'float',
        'version' => 'integer',
        'priority' => 'integer',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'keywords' => 'array',
        'search_tags' => 'array',

    ];


    /* ---------------- Relationships ---------------- */

    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'category_id');
    }

    // type=product articles link to the real catalog item — same Product
    // model InventoryItem already belongs to — instead of duplicating data.
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(KbTag::class, 'kb_article_tag');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KbArticleVersion::class, 'article_id')->orderByDesc('version_number');
    }

    public function media(): HasMany
    {
        return $this->hasMany(KbMedia::class, 'article_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    /* ---------------- Scopes ---------------- */

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('question', 'like', "%{$term}%")
                ->orWhere('answer', 'like', "%{$term}%")
                ->orWhereHas('tags', fn (Builder $t) => $t->where('name', 'like', "%{$term}%"));
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * What the AI chatbot's retrieval step should ever be allowed to see —
     * published AND explicitly marked ai_ready. Draft/pending/archived
     * content, or anything an admin has opted out with ai_ready=false,
     * never reaches the model.
     */
    public function scopeAiReady(Builder $query): Builder
    {
        return $query->where('status', 'published')->where('ai_ready', true);
    }

    /* ---------------- Accessors ---------------- */

    public function getLivePriceAttribute(): ?float
    {
        if ($this->type === 'product' && $this->product_id) {
            $latest = InventoryItem::where('product_id', $this->product_id)
                ->orderByDesc('entry_date')
                ->value('price');

            if ($latest !== null) {
                return (float) $latest;
            }
        }

        return $this->price_override;
    }

    // live_stock fix — net "in - out", same source of truth as Product model,
// instead of a raw sum that ignores direction.
public function getLiveStockAttribute(): ?float
{
    if ($this->type !== 'product' || ! $this->product_id) {
        return null;
    }

    $this->loadMissing('product');

    return $this->product ? (float) $this->product->total_quantity : null;
}

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'pending_approval' => 'Pending Approval',
            'published' => 'Published',
            'archived' => 'Archived',
            default => ucfirst($this->status),
        };
    }

    /* ---------------- AI helpers ---------------- */

    /**
     * Flattens this article into plain, structured text suitable for
     * handing to a chatbot prompt as retrieval context. Deliberately
     * simple — the model should never see raw HTML or admin-only fields,
     * just the facts a customer-facing answer would need.
     */
   /**
 * Flattens this article into plain, structured text for the chatbot
 * prompt. loadMissing() so this is safe to call from anywhere — including
 * the `saved` hook below, where relations usually aren't pre-loaded yet.
 */
public function toAiContext(): string
{
    $this->loadMissing(['product', 'tags', 'media']);

    $lines = ["Title: {$this->title}"];

    if ($this->question) {
        $lines[] = "Question: {$this->question}";
    }

    $lines[] = "Answer: {$this->answer}";

    if ($this->detailed_description) {
        $lines[] = "Detailed description: {$this->detailed_description}";
    }

    if ($this->type === 'product') {
        if ($this->product) {
            $lines[] = "Product: {$this->product->name}";
            if ($this->product->description) {
                $lines[] = "Product overview: {$this->product->description}";
            }
        }
        if (! is_null($this->live_price)) {
            $lines[] = 'Price: ₹' . number_format($this->live_price, 2);
        }
        if (! is_null($this->live_stock)) {
            $lines[] = "Stock available: {$this->live_stock}";
        }

        $catalogFiles = $this->media->where('purpose', 'catalog')->pluck('file_name');
        if ($catalogFiles->isNotEmpty()) {
            $lines[] = 'Catalog document available: ' . $catalogFiles->implode(', ');
        }
    }

    if ($this->tags->isNotEmpty()) {
        $lines[] = 'Tags: ' . $this->tags->pluck('name')->implode(', ');
    }

    return implode("\n", $lines);
}
  protected static function booted(): void
{
    static::saved(function (KbArticle $article) {
        app(\App\Services\KbEmbeddingService::class)->syncArticle($article);
    });
}
}
