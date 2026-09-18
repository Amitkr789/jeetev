<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $bill->bill_number }}</title>
<style>
    @page { margin: 18px 28px 20px 28px; }
    * { box-sizing: border-box; }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10.5px;
        color: #1E2233;
        line-height: 1.5;
        margin: 0;
        padding: 0;
    }

    table { border-collapse: collapse; width: 100%; }
    .no-border td, .no-border th { border: none; }

    .muted { color: #6B7280; }
    .fw-700 { font-weight: 700; }
    .fw-800 { font-weight: 800; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .fs-9  { font-size: 8.5px; }
    .fs-10 { font-size: 9.5px; }

    /* ---- Header banner ---- */
    .banner { padding: 10px 16px; border-radius: 10px; }
    .banner-logo { width: 34px; height: 34px; background: #fff; border-radius: 8px; padding: 3px; text-align: center; }
    .banner-logo img { width: 100%; max-height: 28px; }
    .banner-company { color: #fff; font-size: 17px; font-weight: 800; letter-spacing: .01em; }
    .banner-doctype { color: #fff; font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; }

    .company-meta { font-size: 9.5px; color: #6B7280; padding-top: 3px; }

    .meta-box {
        border: 1px solid #E5E7EB; border-radius: 8px; padding: 8px 12px;
        background: #F8F9FC;
    }
    .meta-box table td { padding: 2px 0; font-size: 9.5px; }
    .meta-box .meta-label { color: #6B7280; }
    .meta-box .meta-value { font-weight: 700; text-align: right; color: #1E2233; }

    .divider { height: 1px; background: #E5E7EB; margin: 10px 0; }

    /* ---- Bill to / Ship to ---- */
    .addr-box {
        border: 1px solid #E5E7EB; border-radius: 8px; padding: 10px 12px; background: #FAFAFC;
        vertical-align: top;
    }
    .addr-label { font-size: 9px; font-weight: 800; letter-spacing: .06em; color: #4338CA; text-transform: uppercase; margin-bottom: 4px; }
    .addr-name { font-weight: 700; font-size: 12px; }
    .addr-line { font-size: 9.5px; color: #374151; margin-top: 1px; }

    /* ---- Items table ---- */
    .items-table thead th {
        background: #4338CA; color: #fff; font-size: 9px; text-transform: uppercase; letter-spacing: .03em;
        padding: 7px 8px; text-align: left; border: 1px solid #4338CA;
    }
    .items-table tbody td {
        padding: 7px 8px; border: 1px solid #E5E7EB; font-size: 9.5px; vertical-align: top;
    }
    .items-table tbody tr.alt td { background: #F8F9FC; }
    .item-name { font-weight: 700; color: #1E2233; }
    .item-sub { font-size: 8.5px; color: #6B7280; margin-top: 1px; }
    .courier-row td { background: #FFFDF5 !important; font-style: italic; }

    .item-thumb { width: 34px; height: 34px; object-fit: cover; border-radius: 6px; border: 1px solid #E5E7EB; }
    .item-thumb-ph { width: 34px; height: 34px; border-radius: 6px; background: #F3F4F6; border: 1px dashed #D1D5DB; }

    /* ---- Tax summary + totals ---- */
    .section-label { font-size: 9px; font-weight: 800; letter-spacing: .06em; color: #4338CA; text-transform: uppercase; margin-bottom: 5px; }
    .tax-summary-table th {
        background: #EEF2FF; color: #312E81; font-size: 8.5px; text-transform: uppercase;
        padding: 5px 8px; border: 1px solid #E0E3FA; text-align: left;
    }
    .tax-summary-table td { padding: 5px 8px; border: 1px solid #E5E7EB; font-size: 9px; }

    .totals-box { border: 1px solid #E5E7EB; border-radius: 8px; overflow: hidden; page-break-inside: avoid; }
    .totals-box table td { padding: 6px 12px; font-size: 10px; }
    .totals-box .t-label { color: #6B7280; }
    .totals-box .t-value { text-align: right; font-weight: 700; }
    .totals-box .grand-row td { font-size: 13px; font-weight: 700; border-top: 1px solid #C7D2FE; }

    .words-bar {
        background: #FEF3C7; border: 1px solid #FDE68A; border-radius: 8px; padding: 6px 12px;
        font-size: 9.5px; color: #78350F; margin-top: 8px;
        page-break-inside: avoid;
    }

    /* ---- Bank ---- */
    .bank-box { border: 1px solid #E5E7EB; border-radius: 8px; padding: 8px 12px; margin-top: 10px; background: #FAFAFC; page-break-inside: avoid; }
    .bank-box .bank-title { font-size: 9px; font-weight: 800; letter-spacing: .06em; color: #4338CA; text-transform: uppercase; margin-bottom: 6px; }
    .bank-box table td { font-size: 9.5px; padding: 2px 0; }
    .bank-box .bank-label { width: 100px; }
    .qr-cell { text-align: center; vertical-align: top; padding-left: 14px; }
    .qr-cell img { width: 72px; height: 72px; }
    .qr-cell .qr-caption { font-size: 8px; color: #6B7280; margin-top: 3px; }

    /* ---- Footer ---- */
    .sign-box { text-align: center; font-size: 9.5px; }
    .sign-line { border-top: 1px solid #9CA3AF; margin-top: 22px; padding-top: 4px; }
    .bottom-strip { margin-top: 12px; padding-top: 6px; border-top: 1px dashed #E5E7EB; font-size: 8px; color: #9CA3AF; text-align: center; }
</style>
</head>
<body>
@php
    $typeMeta = [
        'invoice'   => ['label' => 'Tax Invoice',      'color' => '#16A34A', 'bg' => '#DCFCE7'],
        'quotation' => ['label' => 'Quotation',        'color' => '#0891B2', 'bg' => '#CFFAFE'],
        'pi'        => ['label' => 'Proforma Invoice', 'color' => '#B45309', 'bg' => '#FEF3C7'],
    ];
    $meta = $typeMeta[$bill->bill_type] ?? ['label' => ucfirst($bill->bill_type), 'color' => '#4338CA', 'bg' => '#EEF2FF'];

    $money = fn ($n) => '₹' . number_format((float) $n, 2);
    $trimNum = function ($n) {
        $s = number_format((float) $n, 2, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '' ? '0' : $s;
    };

    // Reads an image from disk and returns a base64 data URI so it can be
    // embedded straight into the PDF. Tries the 'public' storage disk
    // first (works even without `storage:link`), then falls back to a
    // plain public_path() lookup, since different models on this project
    // store their image paths relative to different roots.
    $imgDataUri = function ($path) {
        if (! $path) return null;
        $full = null;
        try {
            $viaDisk = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
            if (file_exists($viaDisk)) $full = $viaDisk;
        } catch (\Throwable $e) {
            // ignore, fall through to the public_path() attempt below
        }
        if (! $full) {
            $viaPublic = public_path(ltrim($path, '/'));
            if (file_exists($viaPublic)) $full = $viaPublic;
        }
        if (! $full) return null;
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $ext = $ext === 'jpg' ? 'jpeg' : $ext;
        return 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($full));
    };

    $logoData = $bill->billingHeader ? $imgDataUri($bill->billingHeader->logo) : null;
    $qrData = $bill->bank ? $imgDataUri($bill->bank->qr_code) : null;

    // A bill item's product may have several images — the first one
    // (Product::images() is already ordered by sort_order/id) is the
    // catalog thumbnail, and that's the only one shown on the line item.
    // Custom items with no linked product, or products with no images
    // yet, simply render a blank placeholder box instead.
    $productImgData = function ($item) use ($imgDataUri) {
        $img = $item->product?->images?->first();
        return $img ? $imgDataUri($img->image_path) : null;
    };

    // Indian numbering system (crore / lakh / thousand) for "amount in words".
    $numberToWords = function ($number) use (&$numberToWords) {
        $number = (int) round($number);
        if ($number < 0) return 'Minus ' . $numberToWords(-$number);
        if ($number === 0) return 'Zero';

        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $twoDigits = function ($n) use ($ones, $tens) {
            if ($n < 20) return $ones[$n];
            return trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
        };
        $threeDigits = function ($n) use ($twoDigits, $ones) {
            $str = '';
            if ($n >= 100) { $str .= $ones[intdiv($n, 100)] . ' Hundred '; $n %= 100; }
            $str .= $twoDigits($n);
            return trim($str);
        };

        $crore = intdiv($number, 10000000); $number %= 10000000;
        $lakh = intdiv($number, 100000); $number %= 100000;
        $thousand = intdiv($number, 1000); $number %= 1000;
        $hundred = $number;

        $parts = [];
        if ($crore) $parts[] = $threeDigits($crore) . ' Crore';
        if ($lakh) $parts[] = $threeDigits($lakh) . ' Lakh';
        if ($thousand) $parts[] = $threeDigits($thousand) . ' Thousand';
        if ($hundred) $parts[] = $threeDigits($hundred);

        return trim(implode(' ', $parts)) ?: 'Zero';
    };

    $rupees = (int) floor($bill->grand_total);
    $paise = (int) round(($bill->grand_total - $rupees) * 100);
    $amountInWords = 'Indian Rupees ' . $numberToWords($rupees)
        . ($paise > 0 ? ' and ' . $numberToWords($paise) . ' Paise' : '')
        . ' Only';

    // Aggregate tax by type + rate across all line items (+ courier) — a proper GST-style tax summary.
    $taxSummary = [];
    foreach ($bill->items as $item) {
        foreach (($item->taxes ?? []) as $tax) {
            $key = $tax['type'] . '|' . $tax['percent'];
            if (! isset($taxSummary[$key])) {
                $taxSummary[$key] = ['type' => $tax['type'], 'percent' => $tax['percent'], 'amount' => 0];
            }
            $taxSummary[$key]['amount'] += $tax['amount'];
        }
    }
    if ($bill->courier_tax_type && $bill->courier_tax_amount > 0) {
        $key = $bill->courier_tax_type . '|' . $bill->courier_tax_percent;
        if (! isset($taxSummary[$key])) {
            $taxSummary[$key] = ['type' => $bill->courier_tax_type, 'percent' => $bill->courier_tax_percent, 'amount' => 0];
        }
        $taxSummary[$key]['amount'] += $bill->courier_tax_amount;
    }

    $companyName = $bill->billingHeader->company_name ?? config('app.name', 'Dalal Adda');
@endphp

<!-- ===================== HEADER BANNER ===================== -->
<table class="no-border">
    <tr>
        <td class="banner" style="background:{{ $meta['color'] }};">
            <table class="no-border">
                <tr>
                    <td style="width:65%; vertical-align:middle;">
                        <table class="no-border"><tr>
                            @if($logoData)
                            <td style="width:42px; vertical-align:middle; padding-right:10px;">
                                <div class="banner-logo"><img src="{{ $logoData }}"></div>
                            </td>
                            @endif
                            <td style="vertical-align:middle;">
                                <div class="banner-company">{{ $companyName }}</div>
                            </td>
                        </tr></table>
                    </td>
                    <td style="width:35%; text-align:right; vertical-align:middle;">
                        <div class="banner-doctype">{{ $meta['label'] }}</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="no-border" style="margin-top:10px;">
    <tr>
        <td style="width:58%; vertical-align:top;">
            <div class="company-meta">
                @if($bill->billingHeader && $bill->billingHeader->address)
                    {!! nl2br(e($bill->billingHeader->address)) !!}<br>
                @endif
                @if($bill->billingHeader && $bill->billingHeader->gstin)
                    GSTIN: {{ $bill->billingHeader->gstin }}@if($bill->billingHeader->phone)&nbsp;·&nbsp;@endif
                @endif
                @if($bill->billingHeader && $bill->billingHeader->phone)
                    Ph: {{ $bill->billingHeader->phone }}
                @endif
            </div>
        </td>
        <td style="width:42%; vertical-align:top;">
            <div class="meta-box">
                <table class="no-border">
                    <tr><td class="meta-label">Bill No.</td><td class="meta-value">{{ $bill->bill_number }}</td></tr>
                    <tr><td class="meta-label">Billing Date</td><td class="meta-value">{{ optional($bill->billing_date)->format('d M Y') }}</td></tr>
                    @if($bill->valid_till)
                    <tr><td class="meta-label">Valid Till</td><td class="meta-value">{{ optional($bill->valid_till)->format('d M Y') }}</td></tr>
                    @endif
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="divider"></div>

<!-- ===================== BILL TO / SHIP TO ===================== -->
<table class="no-border">
    <tr>
        <td style="width:50%; padding-right:8px;">
            <div class="addr-box">
                <div class="addr-label">Billed To</div>
                <div class="addr-name">{{ $bill->customer_name }}</div>
                @if($bill->company_name)<div class="addr-line">{{ $bill->company_name }}</div>@endif
                @if($bill->gst_number)<div class="addr-line">GSTIN: {{ $bill->gst_number }}</div>@endif
                <div class="addr-line">{{ $bill->phone }}@if($bill->email)&nbsp;·&nbsp;{{ $bill->email }}@endif</div>
                @if($bill->billing_address)<div class="addr-line">{!! nl2br(e($bill->billing_address)) !!}</div>@endif
            </div>
        </td>
        <td style="width:50%; padding-left:8px;">
            <div class="addr-box">
                <div class="addr-label">Shipped To</div>
                @if($bill->ship_same_as_billing)
                    <div class="addr-line fs-10 muted" style="margin-bottom:3px;">Same as billing address</div>
                    <div class="addr-name">{{ $bill->customer_name }}</div>
                    @if($bill->billing_address)<div class="addr-line">{!! nl2br(e($bill->billing_address)) !!}</div>@endif
                @else
                    <div class="addr-name">{{ $bill->customer_name }}</div>
                    @if($bill->shipping_address)
                        <div class="addr-line">{!! nl2br(e($bill->shipping_address)) !!}</div>
                    @else
                        <div class="addr-line muted">No shipping address provided</div>
                    @endif
                @endif
            </div>
        </td>
    </tr>
</table>

<!-- ===================== ITEMS ===================== -->
<table class="items-table" style="margin-top:16px;">
    <colgroup>
        <col style="width:5%"><col style="width:34%"><col style="width:8%">
        <col style="width:12%"><col style="width:13%"><col style="width:13%"><col style="width:15%">
    </colgroup>
    <thead>
        <tr>
            <th>#</th><th>Item</th><th>Qty</th><th class="text-right">Rate</th>
            <th class="text-right">Taxable</th><th class="text-right">Tax</th><th class="text-right">Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse($bill->items as $i => $item)
        @php $imgUri = $productImgData($item); @endphp
        <tr @if($i % 2 === 1) class="alt" @endif>
            <td>{{ $i + 1 }}</td>
            <td>
                <table class="no-border" style="border: none;"><tr>
                    <td style="width:38px; padding-right:8px; vertical-align:top;">
                        @if($imgUri)
                            <img src="{{ $imgUri }}" class="item-thumb" alt="">
                        @else
                            <div class="item-thumb-ph"></div>
                        @endif
                    </td>
                    <td style="vertical-align:top;">
                        <div class="item-name">{{ $item->product_name }}</div>
                        @if($item->hsn_sku)<div class="item-sub">HSN/SKU: {{ $item->hsn_sku }}</div>@endif
                    </td>
                </tr></table>
            </td>
            <td>{{ $trimNum($item->quantity) }} {{ $item->unit }}</td>
            <td class="text-right">{{ $money($item->price) }}</td>
            <td class="text-right">{{ $money($item->taxable_amount) }}</td>
            <td class="text-right">
                {{ $money($item->tax_amount) }}
                @if(!empty($item->taxes))
                    <div class="item-sub">{{ collect($item->taxes)->map(fn($t) => $t['type'].' '.$trimNum($t['percent']).'%')->join(' + ') }}</div>
                @endif
            </td>
            <td class="text-right fw-700">{{ $money($item->total) }}</td>
        </tr>
        @empty
        <tr><td colspan="7" class="text-center muted" style="padding:16px;">No items on this bill</td></tr>
        @endforelse

        @if($bill->courier_name || (float) $bill->courier_price > 0)
        <tr class="courier-row">
            <td></td>
            <td><div class="item-name">{{ $bill->courier_name ?: 'Courier Charges' }}</div><div class="item-sub">Courier / shipping</div></td>
            <td>—</td>
            <td class="text-right">{{ $money($bill->courier_price) }}</td>
            <td class="text-right">{{ $money($bill->courier_price) }}</td>
            <td class="text-right">
                {{ $money($bill->courier_tax_amount) }}
                @if($bill->courier_tax_type && $bill->courier_tax_percent)
                    <div class="item-sub">{{ $bill->courier_tax_type }} {{ $trimNum($bill->courier_tax_percent) }}%</div>
                @endif
            </td>
            <td class="text-right fw-700">{{ $money((float) $bill->courier_price + (float) $bill->courier_tax_amount) }}</td>
        </tr>
        @endif
    </tbody>
</table>

<!-- ===================== TAX SUMMARY + TOTALS ===================== -->
<table class="no-border" style="margin-top:14px;">
    <tr>
        <td style="width:56%; vertical-align:top; padding-right:10px;">
            @if(count($taxSummary))
            <div class="section-label">Tax Summary</div>
            <table class="tax-summary-table">
                <thead><tr><th>Tax Type</th><th class="text-right">Rate</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                    @foreach($taxSummary as $row)
                    <tr>
                        <td>{{ $row['type'] }}</td>
                        <td class="text-right">{{ $trimNum($row['percent']) }}%</td>
                        <td class="text-right">{{ $money($row['amount']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif

            <div class="words-bar"><span class="fw-700">Amount in words:</span> {{ $amountInWords }}</div>
        </td>
        <td style="width:44%; vertical-align:top;">
            <div class="totals-box">
                <table class="no-border">
                    <tr><td class="t-label">Subtotal</td><td class="t-value">{{ $money($bill->subtotal) }}</td></tr>
                    <tr><td class="t-label">Total Tax</td><td class="t-value">{{ $money($bill->total_tax) }}</td></tr>
                    <tr class="grand-row" style="background:{{ $meta['bg'] }}; color:{{ $meta['color'] }};"><td>Grand Total</td><td class="text-right">{{ $money($bill->grand_total) }}</td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<!-- ===================== BANK DETAILS ===================== -->
@if($bill->bank)
<div class="bank-box">
    <div class="bank-title">Payment Details</div>
    <table class="no-border">
        <tr>
            <td style="width:70%; vertical-align:top;">
                <table class="no-border">
                    <tr><td class="bank-label muted">Bank Name</td><td class="fw-700">{{ $bill->bank->bank_name }}</td></tr>
                    <tr><td class="bank-label muted">Account Holder</td><td class="fw-700">{{ $bill->bank->account_holder_name }}</td></tr>
                    <tr><td class="bank-label muted">Account Number</td><td class="fw-700">{{ $bill->bank->account_number }}</td></tr>
                    <tr><td class="bank-label muted">IFSC Code</td><td class="fw-700">{{ $bill->bank->ifsc_code }}</td></tr>
                    @if($bill->bank->branch)
                    <tr><td class="bank-label muted">Branch</td><td class="fw-700">{{ $bill->bank->branch }}</td></tr>
                    @endif
                </table>
            </td>
            @if($qrData)
            <td class="qr-cell" style="width:30%;">
                <img src="{{ $qrData }}">
                <div class="qr-caption">Scan to Pay</div>
            </td>
            @endif
        </tr>
    </table>
</div>
@endif

<!-- ===================== FOOTER ===================== -->
<table class="no-border" style="margin-top:14px; page-break-inside: avoid;">
    <tr>
        <td style="width:60%; vertical-align:bottom;">
            <div class="fs-9 muted">
                @if($bill->bill_type === 'quotation')
                    This is a quotation and not a demand for payment. Prices are indicative and may change without prior notice.
                @elseif($bill->bill_type === 'pi')
                    This is a Proforma Invoice for reference purposes only and does not constitute a tax invoice.
                @else
                    Goods once sold will only be taken back or exchanged as per company policy. This is a computer-generated invoice.
                @endif
            </div>
        </td>
        <td style="width:40%;">
            <div class="sign-box">
                <div>For {{ $companyName }}</div>
                <div class="sign-line">Authorized Signatory</div>
            </div>
        </td>
    </tr>
</table>

<div class="bottom-strip">
    Generated on {{ now()->format('d M Y, h:i A') }}
    @if($bill->creator)&nbsp;·&nbsp;Prepared by {{ $bill->creator->name }}@endif
    &nbsp;·&nbsp;{{ $companyName }}
</div>

</body>
</html>