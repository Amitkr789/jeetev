<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillItem extends Model
{
    protected $fillable = [
        'bill_id',
        'product_id',
        'product_name',
        'hsn_sku',
        'unit',
        'price',
        'quantity',
        'taxable_amount',
        'taxes',
        'tax_amount',
        'total',
    ];

    protected $casts = [
        'taxes' => 'array',
        'price' => 'float',
        'quantity' => 'float',
        'taxable_amount' => 'float',
        'tax_amount' => 'float',
        'total' => 'float',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // The inventory ledger entries this line generated when the bill was an
    // invoice (used to restore stock cleanly on edit/delete).
    public function inventoryDeductions(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}