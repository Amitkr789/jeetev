<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NOTE: this file wasn't shared with me, so this is a reconstruction based
 * only on how InventoryManageController and BillingManageController already
 * use the model (product_id, dealer_id, bill_item_id, quantity, unit,
 * price, entry_date, description, created_by — plus the new `type` field).
 * If your real InventoryItem.php has extra accessors, scopes, or casts not
 * listed here, merge them in rather than overwriting wholesale.
 */
class InventoryItem extends Model
{
    protected $fillable = [
        'product_id',
        'dealer_id',
        'bill_item_id',
        'quantity',
        'unit',
        'price',
        'type',
        'entry_date',
        'description',
        'created_by',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * The bill line item this entry was auto-generated for, when this is a
     * stock-OUT movement created by BillingManageController::deductStock().
     * Null for ordinary dealer stock-IN entries. Adjust the related model
     * name below if your bill line-item model isn't called BillItem.
     */
    public function billItem(): BelongsTo
    {
     
        return $this->belongsTo(BillItem::class, 'bill_item_id');
    }
    /**
     * True once total stock for this product has been drawn down to zero
     * (or it never had any stock entries logged at all).
     */
    public function getIsOutOfStockAttribute(): bool
    {
        return $this->total_quantity <= 0;
    }
}