@extends('finance.layout')

@section('content')
@php
    $rp = fn ($v) => number_format((float) $v, 0, ',', '.');
    $fd = fn ($d) => $d ? \Carbon\Carbon::parse($d)->translatedFormat('d M Y') : '—';
    $typeLabels = [
        'hotel' => 'Hotel', 'transport' => 'Transport', 'guide' => 'Guide',
        'restaurant' => 'Restaurant', 'attraction' => 'Attraction',
        'venue' => 'Venue', 'equipment' => 'Equipment', 'other' => 'Lainnya',
    ];
    $nonIdr = ($invoice->currency ?? 'IDR') !== 'IDR';
@endphp

<p class="muted" style="margin:0 0 10px;">
    <b style="color:#c0272d;">DOKUMEN INTERNAL</b> — rincian modal vs jual untuk Keuangan, bukan untuk customer.
</p>

{{-- Info invoice & tour --}}
<table class="rpt">
    <tbody>
        <tr>
            <td class="sub" style="width:110px;">No. Invoice</td>
            <td style="width:38%;"><b>{{ $invoice->finance_number ?? $invoice->number }}</b>@if($invoice->finance_number) <span class="sub">({{ $invoice->number }})</span>@endif</td>
            <td class="sub" style="width:110px;">Customer</td>
            <td>{{ $tour->customer?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="sub">Tour</td>
            <td>{{ $tour->code }} — {{ $tour->title ?? '—' }}</td>
            <td class="sub">Pax</td>
            <td>{{ $tour->pax }}</td>
        </tr>
        <tr>
            <td class="sub">Tanggal Tour</td>
            <td>{{ $fd($tour->start_date) }}@if($tour->end_date) s/d {{ $fd($tour->end_date) }}@endif</td>
            <td class="sub">Disetujui</td>
            <td>{{ $fd($invoice->approved_at) }}@if($invoice->approvedBy) oleh {{ $invoice->approvedBy->name }}@endif</td>
        </tr>
    </tbody>
</table>

{{-- Item rincian profit --}}
<div class="section-title">Rincian Item (IDR)</div>
<table class="rpt">
    <thead>
        <tr>
            <th class="c" style="width:26px;">No</th>
            <th>Deskripsi</th>
            <th class="c" style="width:34px;">Qty</th>
            <th class="c" style="width:34px;">Mlm</th>
            <th class="r" style="width:78px;">Cost/unit</th>
            <th class="r" style="width:78px;">Sell/unit</th>
            <th class="r" style="width:86px;">Total Cost</th>
            <th class="r" style="width:86px;">Total Jual</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->items as $i => $item)
        <tr @if($i % 2 === 1) class="alt" @endif>
            <td class="c">{{ $i + 1 }}</td>
            <td>
                {{ $item->description }}
                <div class="sub">
                    {{ $typeLabels[$item->product_type] ?? $item->product_type ?? '—' }}@if($item->start_date) · {{ $fd($item->start_date) }}@if($item->end_date && ! $item->end_date->equalTo($item->start_date)) – {{ $fd($item->end_date) }}@endif @endif
                </div>
            </td>
            <td class="c">{{ $item->qty }}</td>
            <td class="c">{{ $item->nights }}</td>
            <td class="r">{{ $rp($item->unit_cost) }}</td>
            <td class="r">{{ $rp($item->unit_sell) }}</td>
            <td class="r">{{ $rp($item->line_cost) }}</td>
            <td class="r">{{ $rp($item->line_sell) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6" class="r">Total</td>
            <td class="r">{{ $rp($totalCost) }}</td>
            <td class="r">{{ $rp($totalSell) }}</td>
        </tr>
    </tfoot>
</table>

{{-- Ringkasan profit --}}
<div class="section-title">Ringkasan Profit</div>
<table class="rpt" style="width:60%;">
    <tbody>
        <tr>
            <td class="sub">Total Tagihan Customer</td>
            <td class="r">
                {{ $invoice->currency ?? 'IDR' }} {{ $rp($invoice->total) }}
                @if($nonIdr)<div class="sub">kurs {{ rtrim(rtrim(number_format((float) $invoice->exchange_rate, 2, ',', '.'), '0'), ',') }} ≈ Rp {{ $rp($invoice->total_idr) }}</div>@endif
            </td>
        </tr>
        <tr>
            <td class="sub">Total Cost (Modal)</td>
            <td class="r">Rp {{ $rp($totalCost) }}</td>
        </tr>
        @if(! $isTour)
        <tr>
            <td class="sub">Total Jual Item</td>
            <td class="r">Rp {{ $rp($totalSell) }}</td>
        </tr>
        @endif
    </tbody>
    <tfoot>
        <tr>
            <td>Profit {{ $isTour ? '(Tagihan − Cost)' : '(Jual − Cost)' }}</td>
            <td class="r" style="color:{{ $profit >= 0 ? '#166534' : '#c0272d' }};">
                Rp {{ $rp($profit) }} ({{ $margin }}%)
            </td>
        </tr>
    </tfoot>
</table>
@endsection
