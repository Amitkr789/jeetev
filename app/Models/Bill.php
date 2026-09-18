<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    public const TYPES = ['invoice', 'quotation', 'pi'];

    protected $fillable = [
        'bill_number',
        'bill_type',
        'lead_id',
        'customer_name',
        'company_name',
        'gst_number',
        'phone',
        'email',
        'billing_address',
        'shipping_address',
        'ship_same_as_billing',
        'billing_date',
        'valid_till',
        'billing_header_id',
        'bank_id',
        'courier_name',
        'courier_price',
        'courier_tax_type',
        'courier_tax_percent',
        'courier_tax_amount',
        'subtotal',
        'total_tax',
        'grand_total',
        'stock_deducted',
        'created_by',
    ];

    protected $casts = [
        'billing_date' => 'date',
        'valid_till' => 'date',
        'ship_same_as_billing' => 'boolean',
        'stock_deducted' => 'boolean',
        'courier_price' => 'float',
        'courier_tax_percent' => 'float',
        'courier_tax_amount' => 'float',
        'subtotal' => 'float',
        'total_tax' => 'float',
        'grand_total' => 'float',
    ];

    /* ---------------- Relationships ---------------- */

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function billingHeader(): BelongsTo
    {
        return $this->belongsTo(BillingHeader::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /* ---------------- Accessors ---------------- */

    public function getTypeLabelAttribute(): string
    {
        return match ($this->bill_type) {
            'invoice' => 'Invoice',
            'quotation' => 'Quotation',
            'pi' => 'Proforma Invoice',
            default => ucfirst($this->bill_type),
        };
    }
}