<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaLead extends Model
{
    public const STATUSES = ['new', 'converted', 'discarded'];

    protected $fillable = [
        'leadgen_id', 'page_id', 'form_id', 'form_name',
        'ad_id', 'ad_name', 'adset_id', 'adset_name',
        'campaign_id', 'campaign_name',
        'customer_name', 'product_name', 'whatsapp', 'phone', 'email',
        'raw_payload', 'status', 'converted_lead_id', 'received_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'received_at' => 'datetime',
    ];

    /**
     * The real Lead this row was turned into (once an admin converts it).
     * Null while status = 'new' or 'discarded'.
     */
    public function convertedLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'converted_lead_id');
    }
}