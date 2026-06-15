<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Invoice {{ $invoice->number }}</title>
<style>
    @page { footer: html_pf; }

    body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #1f2937; }

    /* ── Brand palette: navy #0f3460 · navy-dark #16213e · merah #c0272d · emas #e0b667 ── */

    .topbar { background: #0f3460; color: #fff; padding: 14px 18px 12px; border-radius: 8px 8px 0 0; }
    .topbar-table { width: 100%; }
    .topbar-table td { vertical-align: middle; }
    .brand-logo { width: 54px; height: 54px; background: #000; border-radius: 8px; text-align: center; }
    .brand-logo img { width: 44px; height: 44px; margin-top: 5px; }
    .brand-name { font-size: 20pt; font-weight: bold; color: #fff; }
    .brand-tagline { font-size: 7pt; color: #e0b667; letter-spacing: 3px; }
    .doc-box { text-align: right; }
    .doc-title { font-size: 16pt; font-weight: bold; color: #e0b667; letter-spacing: 2px; }
    .doc-code { font-size: 8.5pt; color: #fff; }
    .doc-date { font-size: 7.5pt; color: #aebfd6; }
    .accent-stripe { height: 4px; background: #c0272d; font-size: 0; line-height: 4px; }
    .topbar-contact { background: #16213e; color: #aebfd6; font-size: 7pt; padding: 5px 18px; border-radius: 0 0 8px 8px; }

    .section-title {
        font-size: 9pt; font-weight: bold; letter-spacing: 1.5px; color: #0f3460;
        border-bottom: 2px solid #0f3460; padding-bottom: 5px; margin: 14px 0 11px; page-break-after: avoid;
    }
    .section-title .tick { color: #c0272d; }

    /* ── Items table ── */
    table.items { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.items thead td {
        background: #0f3460; color: #fff; padding: 8px 10px; font-size: 8pt; font-weight: bold;
        border: 1px solid #1e406e;
    }
    table.items thead td.r { text-align: right; }
    table.items thead td.c { text-align: center; }
    table.items tbody td { padding: 8px 10px; font-size: 8.5pt; border: 1px solid #e2e8f0; vertical-align: top; }
    table.items tbody td.r { text-align: right; }
    table.items tbody td.c { text-align: center; }
    table.items tbody td .sub { color: #64748b; font-size: 7.5pt; }

    /* ── Totals box ── */
    .totals { width: 56%; margin-left: 44%; border-collapse: collapse; margin-top: 8px; margin-bottom: 6px; }
    .totals td { padding: 6px 12px; font-size: 9pt; }
    .totals td.lbl { color: #64748b; }
    .totals td.amt { text-align: right; font-weight: bold; color: #0f3460; white-space: nowrap; }
    .totals tr.sub td { border-bottom: 1px solid #eef2f7; }
    .totals tr.total td { border-top: 2px solid #0f3460; font-size: 9.5pt; }
    .totals tr.total td.lbl { color: #0f3460; font-weight: bold; }
    .totals tr.due td { background: #0f3460; color: #fff; font-size: 11pt; font-weight: bold; }
    .totals tr.due td.lbl { color: #c9d6ea; }
    .totals tr.due td.amt { color: #fff; }
    .totals tr.due.paid td { background: #16a34a; }
    .totals tr.paidrow td.amt { color: #16a34a; }

    .terbilang { font-size: 8pt; color: #475569; font-style: italic; margin: 2px 0 14px; }
    .terbilang b { color: #0f3460; font-style: normal; }

    /* ── Bank / payment box ── */
    .pay-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .pay-table td { vertical-align: top; }
    .bank-box { border: 1px solid #e2e8f0; border-top: 3px solid #0f3460; border-radius: 5px; background: #f8fafc; padding: 10px 13px; }
    .bank-row { margin-bottom: 6px; }
    .bank-name { font-size: 8.5pt; font-weight: bold; color: #0f3460; }
    .bank-acc { font-size: 11pt; font-weight: bold; color: #c0272d; letter-spacing: 1px; }
    .bank-holder { font-size: 7.5pt; color: #64748b; }

    .terms-box {
        border-left: 3px solid #e0b667; background: #fffbeb; padding: 10px 13px;
        border-radius: 0 5px 5px 0; font-size: 7.8pt; color: #78350f; line-height: 1.55;
    }
    .terms-box.note { border-left-color: #0f3460; background: #f0f4fb; color: #1e3a5f; margin-bottom: 14px; }

    /* ── Signature ── */
    .sign-wrap { border-top: 1px solid #e2e8f0; margin-top: 18px; padding-top: 14px; }
    .sign-table { width: 100%; }
    .sign-table td { vertical-align: bottom; }
    .legal-name { font-size: 8.5pt; font-weight: bold; color: #0f3460; }
    .legal-meta { font-size: 7pt; color: #64748b; line-height: 1.8; margin-top: 3px; }
    .sign-line { border-top: 1px solid #94a3b8; width: 170px; margin: 42px 0 5px auto; font-size: 0; }
    .sign-label { font-size: 7.5pt; color: #64748b; text-align: right; }
    .sign-name { font-size: 8.5pt; color: #0f3460; font-weight: bold; text-align: right; }

    /* ── Footer ── */
    .pagefoot { background: #16213e; border-radius: 8px; padding: 5px 12px; }
    .pagefoot-table { width: 100%; }
    .pagefoot-table td { vertical-align: middle; }
    .pf-logo { width: 30px; height: 30px; background: #000; border-radius: 5px; text-align: center; }
    .pf-logo img { width: 24px; height: 24px; margin-top: 3px; }
    .pf-brand { font-size: 7.5pt; font-weight: bold; color: #fff; }
    .pf-meta { font-size: 6.5pt; color: #8ea2c2; }
    .pf-page { text-align: right; font-size: 6.5pt; color: #aebfd6; }
</style>
</head>
<body>

@php
    $fmt  = fn ($v) => number_format((float) $v, 0, ',', '.');
    $tour = $invoice->tour;

    $statusMap = [
        'draft'   => ['Draft',          '#64748b', '#f1f5f9'],
        'sent'    => ['Terkirim',       '#1d4ed8', '#dbeafe'],
        'partial' => ['Dibayar Sebagian','#b45309', '#fef3c7'],
        'paid'    => ['LUNAS',          '#15803d', '#dcfce7'],
    ];
    [$stLabel, $stColor, $stBg] = $statusMap[$invoice->status] ?? $statusMap['draft'];

    $dates = $tour?->start_date
        ? \Carbon\Carbon::parse($tour->start_date)->format('d M Y') . ($tour->end_date ? ' – ' . \Carbon\Carbon::parse($tour->end_date)->format('d M Y') : '')
        : null;
@endphp

{{-- ── FOOTER ── --}}
<htmlpagefooter name="pf">
    <div class="pagefoot">
        <table class="pagefoot-table">
            <tr>
                @if($logo)
                <td style="width:34px;"><div class="pf-logo"><img src="{{ $logo }}" width="24" height="24" style="width:24px; height:24px;"></div></td>
                @endif
                <td style="padding-left:10px;">
                    <div class="pf-brand">{{ $company['legal_name'] }}</div>
                    <div class="pf-meta">{{ $company['address'] }} &nbsp;·&nbsp; Hp. {{ $company['phone'] }}</div>
                </td>
                <td class="pf-page" style="width:120px;">
                    {{ $invoice->number }}<br>Hal. {PAGENO} / {nbpg}
                </td>
            </tr>
        </table>
    </div>
</htmlpagefooter>

{{-- ── HEADER ── --}}
<div class="topbar">
    <table class="topbar-table">
        <tr>
            @if($logo)
            <td style="width:62px;"><div class="brand-logo"><img src="{{ $logo }}" width="44" height="44" style="width:44px; height:44px;"></div></td>
            @endif
            <td style="padding-left:12px;">
                <div class="brand-name">{{ $company['brand'] }}</div>
                <div class="brand-tagline">{{ strtoupper($company['tagline']) }}</div>
            </td>
            <td class="doc-box">
                <div class="doc-title">INVOICE</div>
                <div class="doc-code">{{ $invoice->number }}</div>
                <div class="doc-date">{{ \Carbon\Carbon::parse($invoice->date)->format('d F Y') }}</div>
            </td>
        </tr>
    </table>
</div>
<div class="accent-stripe">&nbsp;</div>
<div class="topbar-contact">
    {{ $company['address'] }} &nbsp;|&nbsp; {{ $company['phone'] }} &nbsp;|&nbsp; {{ $company['website'] }}
</div>

{{-- ── INFO ROW ── --}}
@php $cardBase = 'width:100%;border-collapse:collapse;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;'; @endphp
<table style="width:100%; margin-top:14px; margin-bottom:16px; border-collapse:collapse;">
    <tr>
        <td style="width:38%; padding-right:6px; vertical-align:top;">
            <table style="{{ $cardBase }} border-top:3px solid #0f3460; background:#f8fafc;">
                <tr><td style="padding:8px 10px;">
                    <div style="font-size:6pt;font-weight:bold;color:#64748b;letter-spacing:1px;margin-bottom:3px;">TAGIHAN KEPADA</div>
                    <div style="font-size:10pt;font-weight:bold;color:#0f3460;">{{ $tour?->customer?->name ?? 'Valued Guest' }}</div>
                    @if($tour?->customer?->phone)<div style="font-size:7.5pt;color:#64748b;margin-top:2px;">{{ $tour->customer->phone }}</div>@endif
                    @if($tour?->customer?->email)<div style="font-size:7.5pt;color:#64748b;">{{ $tour->customer->email }}</div>@endif
                    @if($tour?->customer?->country)<div style="font-size:7.5pt;color:#64748b;">{{ $tour->customer->country }}</div>@endif
                </td></tr>
            </table>
        </td>
        <td style="width:34%; padding-right:6px; vertical-align:top;">
            <table style="{{ $cardBase }} border-top:3px solid #0f3460; background:#f8fafc;">
                <tr><td style="padding:8px 10px;">
                    <div style="font-size:6pt;font-weight:bold;color:#64748b;letter-spacing:1px;margin-bottom:3px;">UNTUK PESANAN</div>
                    <div style="font-size:9pt;font-weight:bold;color:#0f3460;">{{ $tour?->title ?? $tour?->code ?? '-' }}</div>
                    <div style="font-size:7.5pt;color:#64748b;margin-top:2px;">
                        {{ $tour?->code }}@if($tour?->pax) · {{ $tour->pax }} Pax @endif
                    </div>
                    @if($dates)<div style="font-size:7.5pt;color:#64748b;">{{ $dates }}</div>@endif
                </td></tr>
            </table>
        </td>
        <td style="width:28%; vertical-align:top;">
            <table style="{{ $cardBase }} border-top:3px solid #c0272d; background:#fdf4f4;">
                <tr><td style="padding:8px 10px;">
                    <div style="font-size:6pt;font-weight:bold;color:#64748b;letter-spacing:1px;margin-bottom:2px;">TGL INVOICE</div>
                    <div style="font-size:8.5pt;font-weight:bold;color:#0f3460;">{{ \Carbon\Carbon::parse($invoice->date)->format('d M Y') }}</div>
                    @if($invoice->due_date)
                    <div style="font-size:6pt;font-weight:bold;color:#64748b;letter-spacing:1px;margin-top:5px;margin-bottom:2px;">JATUH TEMPO</div>
                    <div style="font-size:8.5pt;font-weight:bold;color:#c0272d;">{{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}</div>
                    @endif
                    <div style="margin-top:6px;">
                        <span style="display:inline-block;background:{{ $stBg }};color:{{ $stColor }};font-size:7pt;font-weight:bold;padding:2px 9px;border-radius:20px;">{{ strtoupper($stLabel) }}</span>
                    </div>
                </td></tr>
            </table>
        </td>
    </tr>
</table>

{{-- ── RINCIAN TAGIHAN ── --}}
<div class="section-title"><span class="tick">|</span> RINCIAN TAGIHAN</div>
<table class="items">
    <thead>
        <tr>
            <td class="c" style="width:30px;">No</td>
            <td>Deskripsi</td>
            <td class="c" style="width:40px;">Qty</td>
            <td class="c" style="width:48px;">Malam</td>
            <td class="r" style="width:110px;">Harga (IDR)</td>
            <td class="r" style="width:120px;">Jumlah (IDR)</td>
        </tr>
    </thead>
    <tbody>
        @forelse($items as $i => $it)
        <tr>
            <td class="c">{{ $i + 1 }}</td>
            <td>{{ $it['desc'] }}</td>
            <td class="c">{{ $it['qty'] }}</td>
            <td class="c">{{ $it['nights'] > 1 ? $it['nights'] : '–' }}</td>
            <td class="r">{{ $fmt($it['unit']) }}</td>
            <td class="r">{{ $fmt($it['line']) }}</td>
        </tr>
        @empty
        {{-- Fallback: tour tanpa item terperinci → satu baris ringkas --}}
        <tr>
            <td class="c">1</td>
            <td colspan="4">
                {{ $tour?->title ?? 'Paket Tour' }}
                <div class="sub">
                    @if($tour?->code){{ $tour->code }}@endif
                    @if($tour?->pax) · {{ $tour->pax }} pax @endif
                    @if($dates) · {{ $dates }}@endif
                </div>
            </td>
            <td class="r">{{ $fmt($invoice->total) }}</td>
        </tr>
        @endforelse
    </tbody>
</table>

{{-- ── TOTALS ── --}}
<table class="totals">
    @if($items->count())
    <tr class="sub">
        <td class="lbl">Subtotal</td>
        <td class="amt">IDR {{ $fmt($itemsTotal) }}</td>
    </tr>
    @if(abs($adjustment) >= 1)
    <tr class="sub">
        <td class="lbl">{{ $adjustment >= 0 ? 'Penyesuaian' : 'Diskon' }}</td>
        <td class="amt">{{ $adjustment < 0 ? '– ' : '' }}IDR {{ $fmt(abs($adjustment)) }}</td>
    </tr>
    @endif
    @endif

    @if($paid > 0)
    <tr class="total">
        <td class="lbl">Total Tagihan</td>
        <td class="amt">IDR {{ $fmt($invoice->total) }}</td>
    </tr>
    <tr class="paidrow">
        <td class="lbl">Sudah Dibayar</td>
        <td class="amt">– IDR {{ $fmt($paid) }}</td>
    </tr>
    @endif

    <tr class="due {{ $outstanding <= 0 ? 'paid' : '' }}">
        <td class="lbl">{{ $outstanding <= 0 ? 'LUNAS' : ($paid > 0 ? 'SISA TAGIHAN' : 'TOTAL TAGIHAN') }}</td>
        <td class="amt">IDR {{ $fmt(max($outstanding, 0)) }}</td>
    </tr>
</table>

<p class="terbilang">Terbilang: <b>{{ $amountWords }}</b></p>

{{-- ── RIWAYAT PEMBAYARAN ── --}}
@if($invoice->payments->count())
<div class="section-title"><span class="tick">|</span> RIWAYAT PEMBAYARAN</div>
<table class="items" style="margin-bottom:14px;">
    <thead>
        <tr>
            <td class="c" style="width:30px;">No</td>
            <td style="width:90px;">Tanggal</td>
            <td>Metode / Keterangan</td>
            <td class="r" style="width:120px;">Jumlah (IDR)</td>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->payments as $i => $p)
        <tr>
            <td class="c">{{ $i + 1 }}</td>
            <td>{{ \Carbon\Carbon::parse($p->date)->format('d M Y') }}</td>
            <td>{{ $p->method ?: '-' }}@if($p->notes) <span class="sub">· {{ $p->notes }}</span>@endif</td>
            <td class="r">{{ $fmt($p->amount) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ── PEMBAYARAN ── --}}
@if(count($bank) || trim((string) $paymentTerms) !== '')
<div class="section-title"><span class="tick">|</span> INFORMASI PEMBAYARAN</div>
<table class="pay-table">
    <tr>
        @if(count($bank))
        <td style="width:42%; padding-right:8px;">
            <div class="bank-box">
                @foreach($bank as $b)
                <div class="bank-row">
                    <div class="bank-name">{{ $b['bank'] ?? '' }}</div>
                    <div class="bank-acc">{{ $b['account'] ?? '' }}</div>
                    <div class="bank-holder">a.n. {{ $b['name'] ?? '' }}</div>
                </div>
                @endforeach
            </div>
        </td>
        <td style="width:2%;"></td>
        @endif
        @if(trim((string) $paymentTerms) !== '')
        <td style="vertical-align:top;">
            <div class="terms-box">{!! nl2br(e($paymentTerms)) !!}</div>
        </td>
        @endif
    </tr>
</table>
@endif

{{-- ── CATATAN ── --}}
@if($invoice->notes)
<div class="terms-box note">{!! nl2br(e($invoice->notes)) !!}</div>
@endif

{{-- ── SIGNATURE ── --}}
<div class="sign-wrap">
<table class="sign-table">
    <tr>
        <td style="width:58%;">
            <div class="legal-name">{{ $company['legal_name'] }}</div>
            <div class="legal-meta">
                {{ $company['address'] }}<br>
                Hp. {{ $company['phone'] }}<br>
                Email: {{ $company['email'] }}
            </div>
        </td>
        <td style="width:42%;">
            <div class="sign-line">&nbsp;</div>
            <div class="sign-label">Hormat kami,</div>
            <div class="sign-name">{{ $company['brand'] }}</div>
        </td>
    </tr>
</table>
</div>

</body>
</html>
