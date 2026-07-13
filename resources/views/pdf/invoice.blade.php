<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: Helvetica, Arial, sans-serif;
    font-size: 11px;
    color: #212121;
    background: #ffffff;
}

/* ═══════════════════════════════════════
   HEADER
═══════════════════════════════════════ */
.header {
    background-color: #1a237e;
    width: 100%;
    padding: 0;
}
.header table { width: 100%; border-collapse: collapse; }
.header-left {
    background-color: #1a237e;
    padding: 22px 24px 18px 24px;
    vertical-align: middle;
    width: 58%;
}
.header-right {
    background-color: #283593;
    padding: 22px 24px 18px 24px;
    vertical-align: middle;
    text-align: right;
    width: 42%;
}
.shop-name {
    font-size: 18px;
    font-weight: bold;
    color: #ffffff;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    margin-bottom: 5px;
}
.branch-info {
    font-size: 9px;
    color: #9fa8da;
    letter-spacing: 0.3px;
}
.doc-type {
    font-size: 7px;
    font-weight: bold;
    letter-spacing: 3px;
    color: #9fa8da;
    text-transform: uppercase;
    margin-bottom: 6px;
}
.doc-title {
    font-size: 20px;
    font-weight: bold;
    color: #ffffff;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

/* ═══════════════════════════════════════
   REFERENCE BAR
═══════════════════════════════════════ */
.ref-bar {
    background-color: #e8eaf6;
    border-left: 4px solid #1a237e;
    padding: 10px 20px;
    margin-bottom: 0;
}
.ref-bar table { width: 100%; border-collapse: collapse; }
.ref-bar td { vertical-align: middle; font-size: 10px; }
.ref-number {
    font-size: 13px;
    font-weight: bold;
    color: #1a237e;
    letter-spacing: 1px;
}
.ref-label {
    font-size: 8px;
    font-weight: bold;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #7986cb;
    margin-bottom: 2px;
}
.ref-date {
    font-size: 10px;
    color: #546e7a;
    text-align: right;
}

/* ═══════════════════════════════════════
   STATUS BADGE
═══════════════════════════════════════ */
.badge {
    display: inline-block;
    padding: 3px 10px;
    font-size: 9px;
    font-weight: bold;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    border-radius: 2px;
}
.badge-confirmed  { background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9; }
.badge-picked_up  { background: #fff3e0; color: #e65100; border: 1px solid #ffcc80; }
.badge-returned   { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
.badge-cancelled  { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }

/* ═══════════════════════════════════════
   SECTION HEADING
═══════════════════════════════════════ */
.section-wrap { padding: 0 20px; }
.section-title {
    font-size: 8px;
    font-weight: bold;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #1a237e;
    border-bottom: 1.5px solid #c5cae9;
    padding-bottom: 4px;
    margin-bottom: 8px;
    margin-top: 16px;
}

/* ═══════════════════════════════════════
   INFO CARDS (customer / period)
═══════════════════════════════════════ */
.cards-table { width: 100%; border-collapse: collapse; }
.cards-table td {
    width: 50%;
    vertical-align: top;
    padding: 14px 16px;
    border: 1px solid #e0e0e0;
    background-color: #fafafa;
}
.cards-table td:first-child { border-right: none; }
.card-heading {
    font-size: 8px;
    font-weight: bold;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: #7986cb;
    margin-bottom: 10px;
}
.field-label { font-size: 8px; color: #90a4ae; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1px; }
.field-value { font-size: 11px; color: #212121; margin-bottom: 7px; }
.field-value-lg { font-size: 12px; font-weight: bold; color: #1a237e; margin-bottom: 7px; }

/* ═══════════════════════════════════════
   ITEMS TABLE
═══════════════════════════════════════ */
.items-table { width: 100%; border-collapse: collapse; }
.items-table thead tr { background-color: #1a237e; }
.items-table th {
    padding: 8px 12px;
    font-size: 9px;
    font-weight: bold;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    color: #ffffff;
    border: none;
    text-align: left;
}
.items-table th.center { text-align: center; }
.items-table td {
    padding: 8px 12px;
    font-size: 10px;
    color: #212121;
    border-bottom: 1px solid #eeeeee;
    vertical-align: middle;
}
.items-table td.center { text-align: center; }
.items-table td.code { font-size: 9px; color: #546e7a; font-weight: bold; }
.items-table .odd  { background-color: #ffffff; }
.items-table .even { background-color: #f5f6ff; }

/* ═══════════════════════════════════════
   PAYMENT SUMMARY
═══════════════════════════════════════ */
.pay-outer { width: 100%; }
.pay-table {
    width: 58%;
    border-collapse: collapse;
    margin-left: 0;
}
.pay-table td { padding: 8px 14px; font-size: 10px; border-bottom: 1px solid #eeeeee; }
.pay-table .pay-label { color: #546e7a; }
.pay-table .pay-val   { text-align: right; font-weight: bold; color: #212121; }
.pay-table .paid-val  { text-align: right; font-weight: bold; color: #2e7d32; }
.pay-due-has { background-color: #ffebee; border: 1.5px solid #ef9a9a !important; }
.pay-due-ok  { background-color: #e8f5e9; border: 1.5px solid #a5d6a7 !important; }
.pay-due-label-has { color: #c62828; font-weight: bold; font-size: 11px; }
.pay-due-label-ok  { color: #2e7d32; font-weight: bold; font-size: 11px; }
.pay-due-val-has   { text-align: right; color: #c62828; font-weight: bold; font-size: 13px; }
.pay-due-val-ok    { text-align: right; color: #2e7d32; font-weight: bold; font-size: 13px; }

/* ═══════════════════════════════════════
   SECURITY DEPOSIT
═══════════════════════════════════════ */
.deposit-table { width: 58%; border-collapse: collapse; }
.deposit-table td { padding: 8px 14px; font-size: 10px; border-bottom: 1px solid #eeeeee; }
.deposit-held     { color: #e65100; font-weight: bold; }
.deposit-returned { color: #2e7d32; font-weight: bold; }
.deposit-kept     { color: #c62828; font-weight: bold; }
.deposit-excess   { color: #b71c1c; font-weight: bold; }

/* ═══════════════════════════════════════
   CANCELLATION BOX
═══════════════════════════════════════ */
.cancel-box {
    background: #ffebee;
    border: 1.5px solid #ef9a9a;
    border-left: 4px solid #c62828;
    padding: 12px 16px;
    margin-bottom: 0;
}
.cancel-box-title {
    font-size: 9px; font-weight: bold; letter-spacing: 1.5px;
    text-transform: uppercase; color: #c62828; margin-bottom: 8px;
}
.cancel-table { width: 58%; border-collapse: collapse; }
.cancel-table td { padding: 6px 14px; font-size: 10px; border-bottom: 1px solid #ffcdd2; }
.cancel-label  { color: #546e7a; }
.cancel-val    { text-align: right; font-weight: bold; color: #212121; }
.cancel-refund { text-align: right; font-weight: bold; color: #2e7d32; }
.cancel-kept   { text-align: right; font-weight: bold; color: #c62828; font-size: 12px; }
.cancel-reason {
    font-size: 9px; color: #546e7a; font-style: italic;
    margin-top: 8px; padding-top: 6px; border-top: 1px solid #ffcdd2;
}

/* ═══════════════════════════════════════
   ACKNOWLEDGMENT SIGNATURE
═══════════════════════════════════════ */
.ack-table { width: 100%; border-collapse: collapse; }
.ack-table td {
    width: 50%;
    vertical-align: top;
    padding: 16px 18px;
    border: 1px solid #e0e0e0;
}
.ack-table td:first-child { border-right: none; }
.ack-who { font-size: 10px; font-weight: bold; color: #212121; margin-bottom: 2px; }
.ack-sub { font-size: 9px; color: #90a4ae; margin-bottom: 20px; }
.sig-line { border-bottom: 1.5px solid #546e7a; margin-bottom: 5px; height: 28px; }
.sig-caption { font-size: 8px; color: #90a4ae; font-style: italic; }

/* ═══════════════════════════════════════
   FOOTER
═══════════════════════════════════════ */
.footer {
    margin-top: 24px;
    padding: 10px 20px;
    border-top: 1px solid #e0e0e0;
    font-size: 8px;
    color: #bdbdbd;
    text-align: center;
}
</style>
</head>
<body>

{{-- ════════════════════════ HEADER ════════════════════════ --}}
<div class="header">
<table>
<tr>
    <td class="header-left">
        <div class="shop-name">{{ $booking->branch->shop->name }}</div>
        @php
            $branchParts = array_filter([
                $booking->branch->name,
                $booking->branch->address,
                $booking->branch->phone,
            ]);
        @endphp
        <div class="branch-info">{{ implode('  ·  ', $branchParts) }}</div>
    </td>
    <td class="header-right">
        <div class="doc-type">Official Document</div>
        <div class="doc-title">Rental Confirmation</div>
    </td>
</tr>
</table>
</div>

{{-- ════════════════════════ REFERENCE BAR ════════════════════════ --}}
<div class="ref-bar">
<table>
<tr>
    <td>
        <div class="ref-label">Confirmation Reference</div>
        <div class="ref-number">{{ $booking->invoice_number }}</div>
    </td>
    <td style="text-align:right; vertical-align:middle;">
        @php $statusClass = 'badge-' . strtolower($booking->status ?? 'confirmed'); @endphp
        <span class="badge {{ $statusClass }}">{{ $booking->status }}</span>
        <div class="ref-date" style="margin-top:5px;">
            Issued: {{ \Carbon\Carbon::parse($booking->booking_date)->format('F j, Y') }}
        </div>
    </td>
</tr>
</table>
</div>

<div class="section-wrap">

{{-- ════════════════════════ CUSTOMER & PERIOD ════════════════════════ --}}
<div class="section-title">Customer &amp; Rental Period</div>
<table class="cards-table">
<tr>
    <td>
        <div class="card-heading">Customer Details</div>
        <div class="field-label">Full Name</div>
        <div class="field-value">{{ $booking->first_name }} {{ $booking->last_name }}</div>
        <div class="field-label">Phone</div>
        <div class="field-value">{{ $booking->phone_number }}</div>
        @if($booking->alt_phone_number)
        <div class="field-label">Alternative Phone</div>
        <div class="field-value">{{ $booking->alt_phone_number }}</div>
        @endif
    </td>
    <td>
        <div class="card-heading">Rental Period</div>
        <div class="field-label">Pickup Date</div>
        <div class="field-value-lg">{{ \Carbon\Carbon::parse($booking->booking_date)->format('F j, Y') }}</div>
        <div class="field-label">Return Date</div>
        @php
            $hasDiffReturn = $booking->return_date && $booking->return_date != $booking->booking_date;
            $returnLabel   = $hasDiffReturn
                ? \Carbon\Carbon::parse($booking->return_date)->format('F j, Y')
                : \Carbon\Carbon::parse($booking->booking_date)->format('F j, Y') . ' (same day)';
        @endphp
        <div class="field-value-lg">{{ $returnLabel }}</div>
        @if($hasDiffReturn)
        @php
            $days = \Carbon\Carbon::parse($booking->booking_date)
                        ->diffInDays(\Carbon\Carbon::parse($booking->return_date)) + 1;
        @endphp
        <div class="field-label">Duration</div>
        <div class="field-value">{{ $days }} {{ $days == 1 ? 'day' : 'days' }}</div>
        @endif
    </td>
</tr>
</table>

{{-- ════════════════════════ ITEMS ════════════════════════ --}}
<div class="section-title">Items Rented</div>
@php
    $grouped = collect($booking->items)->groupBy(fn($bi) => $bi->item->id);
    $rowNum  = 1;
@endphp
<table class="items-table">
<thead>
    <tr>
        <th class="center" style="width:6%">#</th>
        <th style="width:20%">Code</th>
        <th>Item Name</th>
        <th class="center" style="width:10%">Qty</th>
    </tr>
</thead>
<tbody>
@foreach($grouped as $itemId => $group)
@php $bi = $group->first(); @endphp
<tr class="{{ $rowNum % 2 !== 0 ? 'odd' : 'even' }}">
    <td class="center">{{ $rowNum }}</td>
    <td class="code">{{ $bi->item->unique_code }}</td>
    <td>{{ $bi->item->name }}</td>
    <td class="center">{{ $group->count() }}</td>
</tr>
@php $rowNum++; @endphp
@endforeach
</tbody>
</table>

{{-- ════════════════════════ PAYMENT SUMMARY ════════════════════════ --}}
@php
    $isCancelled = $booking->status === 'CANCELLED';
    $refund      = (float)($booking->refund_amount ?? 0);
    $advance     = (float)$booking->total_advance_payment;
    $netKept     = max(0, $advance - $refund);
    $balance     = (float)$booking->total_agreed_price - $advance;
    $hasDue      = !$isCancelled && $balance > 0.001;
@endphp
<div class="section-title">Payment Summary</div>
@if($isCancelled)
<table class="cancel-table">
<tr>
    <td class="cancel-label">Advance Paid</td>
    <td class="cancel-val">{{ number_format($advance, 2) }} ETB</td>
</tr>
@if($refund > 0)
<tr>
    <td class="cancel-label">Refund Given</td>
    <td class="cancel-refund">- {{ number_format($refund, 2) }} ETB</td>
</tr>
@endif
<tr style="border-top: 1.5px solid #ef9a9a;">
    <td class="cancel-label" style="font-weight:bold;">Net Kept by Shop</td>
    <td class="cancel-kept">{{ number_format($netKept, 2) }} ETB</td>
</tr>
</table>
@else
<table class="pay-table">
<tr>
    <td class="pay-label">Total Agreed Price</td>
    <td class="pay-val">{{ number_format($booking->total_agreed_price, 2) }} ETB</td>
</tr>
<tr>
    <td class="pay-label">Total Paid</td>
    <td class="paid-val">{{ number_format($advance, 2) }} ETB</td>
</tr>
<tr class="{{ $hasDue ? 'pay-due-has' : 'pay-due-ok' }}">
    <td class="{{ $hasDue ? 'pay-due-label-has' : 'pay-due-label-ok' }}">Balance Due</td>
    <td class="{{ $hasDue ? 'pay-due-val-has' : 'pay-due-val-ok' }}">{{ number_format($balance, 2) }} ETB</td>
</tr>
</table>
@endif

{{-- ════════════════════════ CANCELLATION DETAILS ════════════════════════ --}}
@if($isCancelled)
@php $cancelledAt = $booking->cancelled_at ? \Carbon\Carbon::parse($booking->cancelled_at)->format('F j, Y  H:i') : null; @endphp
<div class="section-title">Cancellation Details</div>
<div class="cancel-box">
    <div class="cancel-box-title">Booking Cancelled</div>
    @if($cancelledAt)
    <div style="font-size:9px;color:#546e7a;margin-bottom:4px;">Cancelled on: {{ $cancelledAt }}</div>
    @endif
    @if($booking->cancellation_reason)
    <div class="cancel-reason">Reason: {{ $booking->cancellation_reason }}</div>
    @endif
</div>
@endif

{{-- ════════════════════════ SECURITY DEPOSIT & DAMAGE ════════════════════════ --}}
@php
    $deposit      = (float)($booking->security_deposit ?? 0);
    $deduction    = (float)($booking->deposit_deduction ?? 0);
    $excess       = (float)($booking->excess_damage_charge ?? 0);
    $depReason    = $booking->deposit_deduction_reason;
    $showDeposit  = $deposit > 0 || $excess > 0;
@endphp
@if($showDeposit)
<div class="section-title">{{ $deposit > 0 ? 'Security Deposit' : 'Damage Charge' }}</div>
<table class="deposit-table">
@if($deposit > 0)
<tr>
    <td class="pay-label">Deposit Collected</td>
    <td class="pay-val">{{ number_format($deposit, 2) }} ETB</td>
</tr>
@endif
@if($deduction > 0)
<tr>
    <td class="pay-label">Kept for Damage</td>
    <td class="deposit-kept">- {{ number_format($deduction, 2) }} ETB</td>
</tr>
@endif
@if($deposit > 0 && $deduction == 0 && $excess == 0)
<tr>
    <td class="pay-label">Status</td>
    @if($booking->security_deposit_returned)
    <td class="deposit-returned">Returned to customer ✓</td>
    @else
    <td class="deposit-held">Held</td>
    @endif
</tr>
@endif
@if($deposit > 0 && $deduction > 0 && $excess == 0)
@php $depositBack = max(0, $deposit - $deduction); @endphp
@if($depositBack > 0)
<tr>
    <td class="pay-label">Remainder Returned</td>
    <td class="deposit-returned">{{ number_format($depositBack, 2) }} ETB ✓</td>
</tr>
@endif
@endif
@if($excess > 0)
<tr style="border-top: 1.5px solid #ffcdd2;">
    <td class="pay-label" style="font-weight:bold;">{{ $deposit > 0 ? 'Extra Damage Charge' : 'Damage Charge' }}</td>
    <td class="deposit-excess">{{ number_format($excess, 2) }} ETB — collected at return</td>
</tr>
@endif
@if($depReason)
<tr>
    <td class="pay-label">Damage Reason</td>
    <td style="color:#546e7a;font-style:italic;">{{ $depReason }}</td>
</tr>
@endif
</table>
@endif

{{-- ════════════════════════ SIGNATURE LINES ════════════════════════ --}}
@if($withAck)
<div class="section-title" style="margin-top:24px;">Acknowledgment &amp; Signatures</div>
<table class="ack-table">
<tr>
    <td>
        <div class="ack-who">{{ $booking->branch->shop->name }}</div>
        <div class="ack-sub">Authorized Representative</div>
        <div class="sig-line"></div>
        <div class="sig-caption">Signature &amp; Stamp</div>
    </td>
    <td>
        <div class="ack-who">{{ $booking->first_name }} {{ $booking->last_name }}</div>
        <div class="ack-sub">{{ $booking->phone_number }}</div>
        <div class="sig-line"></div>
        <div class="sig-caption">I acknowledge receipt of the above items in good condition</div>
    </td>
</tr>
</table>
@endif

</div>{{-- end section-wrap --}}

{{-- ════════════════════════ FOOTER ════════════════════════ --}}
<div class="footer">
    This is an official rental confirmation from {{ $booking->branch->shop->name }}
    &nbsp;·&nbsp;
    Generated on {{ now()->format('F j, Y  H:i') }}
</div>

</body>
</html>
