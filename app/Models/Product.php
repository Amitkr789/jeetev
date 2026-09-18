<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'model',
        'unit',
        'description',
        'image',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    /**
     * The product's image gallery, in display order. images()->first() is
     * treated as the thumbnail everywhere in the UI (table rows, cards,
     * the product detail panel).
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Running current stock for this product: every `in` (dealer purchase)
     * entry adds, every `out` (sold via a bill) entry subtracts. Quantity
     * itself is always stored positive — `type` is what controls
     * direction — so this is a type-aware sum, not a plain SUM(quantity).
     *
     * Note: this always runs a live query rather than relying on a cached
     * column, so callers that need it on a *list* of products should
     * expect one extra query per product (see InventoryManageController —
     * it's only appended where the value is actually shown).
     */
public function getTotalQuantityAttribute()
{
    $in = $this->inventoryItems()
        ->where('type', 'in')
        ->sum('quantity');

    $out = $this->inventoryItems()
        ->where('type', 'out')
        ->sum('quantity');

    return $in - $out;
}
    /**
     * True once total stock for this product has been drawn down to zero
     * (or it never had any stock entries logged at all).
     */
    public function getIsOutOfStockAttribute()
{
    return $this->total_quantity <= 0;
}
    
}