<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Quotation {{ $tour->code }}</title>
<style>
    /* Margin halaman diatur di controller (mPDF). Footer pakai <htmlpagefooter>
       sehingga mPDF otomatis menyediakan ruangnya — konten tak pernah tertimpa. */
    @page { footer: html_pf; }

    body {
        font-family: dejavusans, sans-serif;
        font-size: 9pt;
        color: #1f2937;
    }

    /* ── Brand palette (dari logo WM): navy #0f3460 · navy-dark #16213e ·
         merah WM #c0272d · emas #e0b667 ── */

    /* ── Header band (kartu rounded, hanya halaman 1) ── */
    .topbar { background: #0f3460; color: #ffffff; padding: 14px 18px 12px; border-radius: 8px 8px 0 0; }
    .topbar-table { width: 100%; }
    .topbar-table td { vertical-align: middle; }
    .brand-logo {
        width: 54px; height: 54px; background: #000000; border-radius: 8px; text-align: center;
    }
    .brand-logo img { width: 44px; height: 44px; margin-top: 5px; }
    .brand-name { font-size: 20pt; font-weight: bold; color: #ffffff; }
    .brand-tagline { font-size: 7pt; color: #e0b667; letter-spacing: 3px; }
    .doc-box { text-align: right; }
    .doc-title { font-size: 16pt; font-weight: bold; color: #e0b667; letter-spacing: 2px; }
    .doc-code { font-size: 8.5pt; color: #ffffff; }
    .doc-date { font-size: 7.5pt; color: #aebfd6; }
    .accent-stripe { height: 4px; background: #c0272d; font-size: 0; line-height: 4px; }
    .topbar-contact {
        background: #16213e; color: #aebfd6; font-size: 7pt;
        padding: 5px 18px; border-radius: 0 0 8px 8px;
    }

    /* ── Info cards ── */
    .info-table { width: 100%; margin-top: 14px; margin-bottom: 16px; }
    .info-table td { vertical-align: top; padding-right: 7px; }
    .info-table td.last { padding-right: 0; }
    .info-box {
        border: 1px solid #e2e8f0; border-top: 3px solid #0f3460;
        border-radius: 5px; padding: 8px 10px; background: #f8fafc;
    }
    .info-box.accent { border-top-color: #c0272d; background: #fdf4f4; }
    .info-box-label { font-size: 6pt; font-weight: bold; color: #64748b; letter-spacing: 1px; }
    .info-box-value { font-size: 9.5pt; font-weight: bold; color: #0f3460; }
    .info-box-sub { font-size: 7.5pt; color: #64748b; }

    /* ── Section title ── */
    .section-title {
        font-size: 9pt; font-weight: bold; letter-spacing: 1.5px; color: #0f3460;
        border-bottom: 2px solid #0f3460; padding-bottom: 5px; margin: 14px 0 11px;
        page-break-after: avoid;
    }
    .section-title .tick { color: #c0272d; }

    /* ── Itinerary ── */
    .day { margin-bottom: 13px; page-break-inside: avoid; }
    .day-head {
        background: #0f3460; color: #ffffff; padding: 7px 12px; border-radius: 4px;
        font-size: 8.5pt; font-weight: bold; border-left: 4px solid #c0272d;
    }
    .day-body { padding: 7px 12px 5px; font-size: 8.5pt; color: #374151; line-height: 1.5; text-align: justify; }
    .hour-table { width: 100%; border-collapse: collapse; margin: 8px 0 3px; }
    .hour-table td { font-size: 8pt; color: #374151; padding: 2px 0; vertical-align: top; }
    .hour-table td.ht { font-weight: bold; color: #0f3460; white-space: nowrap; width: 90px; padding-right: 10px; }
    .hour-table tr:first-child td { padding-top: 0; }
    .hour-sep { border-top: 1px dashed #e2e8f0; margin: 5px 0 0; }

    /* ── Pricing matrix ── */
    table.matrix { width: 100%; border-collapse: collapse; margin-bottom: 7px; }
    table.matrix thead td {
        background: #0f3460; color: #ffffff; padding: 7px 8px; font-size: 7.5pt;
        font-weight: bold; border: 1px solid #1e406e; text-align: center;
    }
    table.matrix thead td.left { text-align: left; }
    table.matrix thead td .tier-note { font-weight: normal; font-size: 6.5pt; color: #c9d6ea; }
    table.matrix tbody td {
        padding: 5px 8px; font-size: 8pt; border: 1px solid #e2e8f0; text-align: right;
    }
    table.matrix tbody td.name { text-align: left; }
    table.matrix tbody td.name span { color: #64748b; font-size: 7pt; }
    table.matrix tbody tr.alt td { background: #f6f8fb; }
    .price-caption { font-size: 7.5pt; color: #64748b; margin: 0 0 14px; }

    .simple-price-box {
        border: 1px solid #e2e8f0; border-radius: 6px; width: 48%; margin-left: 52%; margin-bottom: 16px;
    }
    .simple-price-box td { padding: 7px 12px; font-size: 9pt; }
    .sp-lbl { color: #64748b; }
    .sp-amt { text-align: right; font-weight: bold; color: #0f3460; }
    .simple-price-box tr.grand td { background: #0f3460; color: #ffffff; font-weight: bold; font-size: 10.5pt; }
    .simple-price-box tr.grand .sp-lbl { color: #c9d6ea; }
    .simple-price-box tr.grand .sp-amt { color: #ffffff; }

    /* ── Two column inc/exc ── */
    .twocol { width: 100%; margin-bottom: 16px; border-collapse: separate; border-spacing: 0; }
    .twocol td.cell { vertical-align: top; width: 50%; }
    .twocol td.gap { width: 10px; }
    .col-pane {
        border-radius: 7px; overflow: hidden;
        border: 1.5px solid #e2e8f0;
        box-shadow: 0 1px 4px rgba(0,0,0,.06);
    }
    .col-pane.inc { border-color: #86efac; }
    .col-pane.exc { border-color: #fca5a5; }
    .col-title {
        font-size: 8pt; font-weight: bold; letter-spacing: 1px;
        padding: 7px 12px 7px 12px; margin: 0;
        display: flex; align-items: center; gap: 5px;
    }
    .col-title.inc { background: #16a34a; color: #ffffff; }
    .col-title.exc { background: #dc2626; color: #ffffff; }
    .col-title .hd-icon { font-size: 10pt; opacity: .9; }
    .col-body { background: #ffffff; padding: 8px 12px 10px; }
    .col-pane.inc .col-body { background: #f0fdf4; }
    .col-pane.exc .col-body { background: #fff5f5; }
    .li {
        font-size: 8pt; color: #374151; margin-bottom: 3px; line-height: 1.4;
        display: flex; align-items: flex-start; gap: 5px;
    }
    .li .mk-inc {
        color: #ffffff; background: #16a34a; border-radius: 50%;
        font-size: 6.5pt; font-weight: bold; min-width: 14px; height: 14px;
        display: inline-flex; align-items: center; justify-content: center;
        margin-top: 1px; flex-shrink: 0;
    }
    .li .mk-exc {
        color: #ffffff; background: #dc2626; border-radius: 50%;
        font-size: 6.5pt; font-weight: bold; min-width: 14px; height: 14px;
        display: inline-flex; align-items: center; justify-content: center;
        margin-top: 1px; flex-shrink: 0;
    }
    .li .mk-opt { color: #0f3460; font-weight: bold; }

    .policy-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 5px; padding: 9px 13px; margin-bottom: 14px; }
    .terms-box {
        border-left: 3px solid #e0b667; background: #fffbeb; padding: 10px 13px;
        border-radius: 0 5px 5px 0; margin-bottom: 14px; font-size: 7.8pt; color: #78350f; line-height: 1.55;
    }
    .terms-box.note { border-left-color: #0f3460; background: #f0f4fb; color: #1e3a5f; }

    /* ── Signature ── */
    .sign-wrap { border-top: 1px solid #e2e8f0; margin-top: 18px; padding-top: 14px; }
    .sign-table { width: 100%; }
    .sign-table td { vertical-align: bottom; }
    .legal-name { font-size: 8.5pt; font-weight: bold; color: #0f3460; }
    .legal-meta { font-size: 7pt; color: #64748b; line-height: 1.8; margin-top: 3px; }
    .validity-badge {
        display: inline-block; background: #dcfce7; color: #166534;
        padding: 3px 12px; border-radius: 20px; font-size: 7.5pt; font-weight: bold;
        border: 1px solid #bbf7d0; margin-top: 8px;
    }
    .sign-line { border-top: 1px solid #94a3b8; width: 170px; margin: 42px 0 5px auto; font-size: 0; }
    .sign-label { font-size: 7.5pt; color: #64748b; text-align: right; }
    .sign-name { font-size: 8.5pt; color: #0f3460; font-weight: bold; text-align: right; }

    /* ── Footer (htmlpagefooter — berulang tiap halaman) ── */
    .pagefoot { background: #16213e; border-radius: 8px; padding: 5px 12px; }
    .pagefoot-table { width: 100%; }
    .pagefoot-table td { vertical-align: middle; }
    .pf-logo { width: 30px; height: 30px; background: #000000; border-radius: 5px; text-align: center; }
    .pf-logo img { width: 24px; height: 24px; margin-top: 3px; }
    .pf-brand { font-size: 7.5pt; font-weight: bold; color: #ffffff; }
    .pf-meta { font-size: 6.5pt; color: #8ea2c2; }
    .pf-page { text-align: right; font-size: 6.5pt; color: #aebfd6; }
</style>
</head>
<body>

@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', '.');

    $pricing   = $tour->pricing ?? [];
    $tiers     = $pricing['tiers'] ?? [];
    $base      = $pricing['base'] ?? null;
    $hotels    = $pricing['hotels'] ?? [];
    $optionals = $pricing['optionals'] ?? [];

    $baseEnabled  = $base && ($base['enabled'] ?? true) && collect($base['prices'] ?? [])->filter()->isNotEmpty();
    $hasMatrix    = count($tiers) > 0 && ($baseEnabled || count($hotels) > 0);
    $hasSingleSup = collect($hotels)->contains(fn ($h) => ! empty($h['single_sup']));

    $toLines = fn ($t) => collect(explode("\n", (string) $t))->map(fn ($l) => trim($l))->filter()->values();

    $colCount = 1 + count($tiers) + ($hasSingleSup ? 1 : 0);
@endphp

{{-- ── FOOTER (mPDF reserve otomatis) ── --}}
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
                    {{ $tour->code }}<br>Hal. {PAGENO} / {nbpg}
                </td>
            </tr>
        </table>
    </div>
</htmlpagefooter>

{{-- ── HEADER (halaman 1 saja) ── --}}
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
                <div class="doc-title">QUOTATION</div>
                <div class="doc-code">{{ $tour->code }}</div>
                <div class="doc-date">{{ now()->format('d F Y') }}</div>
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
<table class="info-table">
    <tr>
        <td style="width:30%; padding-right:6px; vertical-align:top;">
            <table style="{{ $cardBase }} border-top:3px solid #0f3460; background:#f8fafc;">
                <tr><td style="padding:8px 10px;">
                    <div style="font-size:6pt;font-weight:bold;color:#64748b;letter-spacing:1px;margin-bottom:3px;">KEPADA / TO</div>
                    <div style="font-size:9.5pt;font-weight:bold;color:#0f3460;">{{ $tour->customer?->name ?? 'Valued Guest' }}</div>
                    @if($tour->customer?->country)
                    <div style="font-size:7.5pt;color:#64748b;margin-top:2px;">{{ $tour->customer->country }}</div>
                    @endif
                </td></tr>
            </table>
        </td>
        <td style="width:30%; padding-right:6px; vertical-align:top;">
            <table style="{{ $cardBase }} border-top:3px solid #0f3460; background:#f8fafc;">
                <tr><td style="padding:8px 10px;">
                    <div style="font-size:6pt;font-weight:bold;color:#64748b;letter-spacing:1px;margin-bottom:3px;">{{ strtoupper($tour->type_label ?? 'Paket') }}</div>
                    <div style="font-size:9.5pt;font-weight:bold;color:#0f3460;">{{ $tour->title ?? $tour->code }}</div>
                    <div style="font-size:7.5pt;color:#64748b;margin-top:2px;">{{ $tour->pax }} Pax</div>
                </td></tr>
            </table>
        </td>
        <td style="width:20%; padding-right:6px; vertical-align:top;">
            <table style="{{ $cardBase }} border-top:3px solid #0f3460; background:#f8fafc;">
                <tr><td style="padding:8px 10px;">
                    <div style="font-size:6pt;font-weight:bold;color:#64748b;letter-spacing:1px;margin-bottom:3px;">TANGGAL</div>
                    @if($tour->start_date)
                    <div style="font-size:9.5pt;font-weight:bold;color:#0f3460;">{{ \Carbon\Carbon::parse($tour->start_date)->format('d M Y') }}</div>
                    <div style="font-size:7.5pt;color:#64748b;margin-top:2px;">s/d {{ \Carbon\Carbon::parse($tour->end_date)->format('d M Y') }}</div>
                    @else
                    <div style="font-size:9.5pt;font-weight:bold;color:#0f3460;">TBD</div>
                    @endif
                </td></tr>
            </table>
        </td>
        <td style="width:20%; vertical-align:top;">
            <table style="{{ $cardBase }} border-top:3px solid #c0272d; background:#fdf4f4;">
                <tr><td style="padding:8px 10px;">
                    <div style="font-size:6pt;font-weight:bold;color:#64748b;letter-spacing:1px;margin-bottom:3px;">SALES CONTACT</div>
                    <div style="font-size:9.5pt;font-weight:bold;color:#0f3460;">{{ $tour->sales_person ?: $company['brand'] }}</div>
                    <div style="font-size:7.5pt;color:#64748b;margin-top:2px;">{{ $company['website'] }}</div>
                </td></tr>
            </table>
        </td>
    </tr>
</table>

{{-- ── PROGRAM TOUR ── --}}
@if($tour->itineraryDays->count())
    <div class="section-title"><span class="tick">|</span> PROGRAM TOUR</div>
    @foreach($tour->itineraryDays as $day)
        @php $hours = $tour->itineraryHours->where('day_number', $day->day_number); @endphp
        <div class="day">
            <div class="day-head">HARI {{ $day->day_number }}@if($day->title): {{ strtoupper($day->title) }}@endif</div>
            <div class="day-body">
                @if($day->description){{ $day->description }}@endif
                @if($hours->count())
                <div class="hour-sep"></div>
                <table class="hour-table">
                    @foreach($hours as $h)
                    <tr>
                        <td class="ht">{{ $h->start_time?->format('H:i') }}@if($h->end_time)–{{ $h->end_time?->format('H:i') }}@endif</td>
                        <td>{{ $h->activity }}@if($h->notes) <i style="color:#94a3b8;">({{ $h->notes }})</i>@endif</td>
                    </tr>
                    @endforeach
                </table>
                @endif
            </div>
        </div>
    @endforeach
@endif

{{-- ── DETAIL LAYANAN (rental / guide / dokumen / tiket) ── --}}
@php
    $details = $tour->details ?? [];
    $detailRows = collect($detailLabels ?? [])
        ->map(fn ($label, $key) => ['label' => $label, 'value' => $details[$key] ?? null])
        ->filter(fn ($r) => $r['value'] !== null && $r['value'] !== '' && $r['value'] !== false)
        ->values();
@endphp
@if($detailRows->count())
<div class="section-title"><span class="tick">|</span> DETAIL {{ strtoupper($tour->type_label) }}</div>
<table width="100%" style="border-collapse:collapse;margin-bottom:14px;">
    @foreach($detailRows as $r)
    <tr>
        <td style="width:32%;padding:5px 10px;font-size:8pt;color:#64748b;background:#f8fafc;border:1px solid #e2e8f0;vertical-align:top;">{{ $r['label'] }}</td>
        <td style="padding:5px 10px;font-size:8pt;color:#1f2937;border:1px solid #e2e8f0;vertical-align:top;">{{ $r['value'] === true ? 'Ya' : $r['value'] }}</td>
    </tr>
    @endforeach
</table>
@endif

{{-- ── HARGA ── --}}
<div class="section-title"><span class="tick">|</span> {{ ($tour->type ?? 'tour') === 'tour' ? 'HARGA PAKET' : 'HARGA' }}</div>

@if($hasMatrix)
    <table class="matrix">
        <thead>
            <tr>
                <td class="left">{{ count($hotels) ? 'Hotel / Paket' : 'Paket' }}</td>
                @foreach($tiers as $tier)
                <td>{{ $tier['label'] ?? '—' }}@if(!empty($tier['note']))<br><span class="tier-note">{{ $tier['note'] }}</span>@endif</td>
                @endforeach
                @if($hasSingleSup)<td>Sgl. Sup</td>@endif
            </tr>
        </thead>
        <tbody>
            @php $ri = 0; @endphp
            @if($baseEnabled)
            @php $ri++; @endphp
            <tr class="{{ $ri % 2 === 0 ? 'alt' : '' }}">
                <td class="name"><b>{{ $base['label'] ?? 'Tanpa Hotel' }}</b></td>
                @foreach($tiers as $tier)
                <td>{{ isset($base['prices'][$tier['id']]) ? $fmt($base['prices'][$tier['id']]) : '—' }}</td>
                @endforeach
                @if($hasSingleSup)<td>—</td>@endif
            </tr>
            @endif
            @foreach($hotels as $hotel)
            @php $ri++; @endphp
            <tr class="{{ $ri % 2 === 0 ? 'alt' : '' }}">
                <td class="name"><b>{{ $hotel['name'] ?? '—' }}</b>@if(!empty($hotel['room']))<br><span>{{ $hotel['room'] }}</span>@endif</td>
                @foreach($tiers as $tier)
                <td>{{ isset($hotel['prices'][$tier['id']]) ? $fmt($hotel['prices'][$tier['id']]) : '—' }}</td>
                @endforeach
                @if($hasSingleSup)<td>{{ !empty($hotel['single_sup']) ? $fmt($hotel['single_sup']) : '—' }}</td>@endif
            </tr>
            @endforeach
        </tbody>
    </table>
    <p class="price-caption">Harga per pax dalam IDR. Sgl. Sup = tambahan untuk kamar single.@if($tour->price_validity) Harga berlaku s/d {{ \Carbon\Carbon::parse($tour->price_validity)->format('d F Y') }}.@endif</p>
@else
    <table class="simple-price-box">
        <tr><td class="sp-lbl">Total Paket ({{ $tour->pax }} pax)</td><td class="sp-amt">IDR {{ $fmt($tour->total_sell) }}</td></tr>
        @if($tour->pax > 0)
        <tr><td class="sp-lbl">Harga per Pax</td><td class="sp-amt">IDR {{ $fmt($tour->total_sell / $tour->pax) }}</td></tr>
        @endif
        <tr class="grand"><td class="sp-lbl">TOTAL</td><td class="sp-amt">IDR {{ $fmt($tour->total_sell) }}</td></tr>
    </table>
    @if($tour->price_validity)
    <p class="price-caption">Harga berlaku s/d {{ \Carbon\Carbon::parse($tour->price_validity)->format('d F Y') }}.</p>
    @endif
@endif

{{-- ── Optional tour ── --}}
@if(count($optionals))
<div style="margin-bottom:14px;">
    <div style="font-size:8pt;font-weight:bold;letter-spacing:1px;color:#0f3460;padding:6px 0 6px;border-bottom:1px solid #e2e8f0;margin-bottom:6px;">OPTIONAL TOUR</div>
    <table width="100%" style="border-collapse:collapse;">
        @foreach($optionals as $opt)
        <tr>
            <td style="width:14px;padding:3px 6px 3px 0;vertical-align:top;color:#0f3460;font-weight:bold;font-size:9pt;">&#9702;</td>
            <td style="padding:3px 0;font-size:8pt;color:#374151;vertical-align:top;line-height:1.4;">
                {{ $opt['label'] ?? '' }} &mdash; <b>IDR {{ $fmt($opt['price'] ?? 0) }}</b>
                @if(!empty($opt['note'])) <i style="color:#94a3b8;">({{ $opt['note'] }})</i>@endif
            </td>
        </tr>
        @endforeach
    </table>
</div>
@endif

{{-- ── INCLUDED / EXCLUDED (hanya jika diisi) ── --}}
@php $incLines = $toLines($included); $excLines = $toLines($excluded); @endphp
@if($incLines->count() || $excLines->count())
<table width="100%" style="border-collapse:collapse;margin-bottom:16px;">
    <tr>
        @if($incLines->count())
        <td style="width:{{ $excLines->count() ? '49%' : '100%' }};vertical-align:top;padding-right:{{ $excLines->count() ? '6px' : '0' }};">
            <table width="100%" style="border-collapse:collapse;border:1.5px solid #86efac;">
                <tr>
                    <td colspan="2" style="background:#16a34a;color:#ffffff;font-size:8pt;font-weight:bold;padding:7px 12px;letter-spacing:1px;">
                        &#10004; HARGA SUDAH TERMASUK
                    </td>
                </tr>
                @foreach($incLines as $line)
                <tr>
                    <td style="background:#f0fdf4;padding:4px 5px 4px 10px;vertical-align:top;width:16px;color:#16a34a;font-weight:bold;font-size:9pt;text-align:center;">&#10003;</td>
                    <td style="background:#f0fdf4;padding:4px 10px 4px 4px;font-size:8pt;color:#374151;vertical-align:top;line-height:1.4;">{{ $line }}</td>
                </tr>
                @endforeach
            </table>
        </td>
        @endif
        @if($incLines->count() && $excLines->count())
        <td style="width:2%;vertical-align:top;"></td>
        @endif
        @if($excLines->count())
        <td style="width:{{ $incLines->count() ? '49%' : '100%' }};vertical-align:top;">
            <table width="100%" style="border-collapse:collapse;border:1.5px solid #fca5a5;">
                <tr>
                    <td colspan="2" style="background:#dc2626;color:#ffffff;font-size:8pt;font-weight:bold;padding:7px 12px;letter-spacing:1px;">
                        &#10008; HARGA BELUM TERMASUK
                    </td>
                </tr>
                @foreach($excLines as $line)
                <tr>
                    <td style="background:#fff5f5;padding:4px 5px 4px 10px;vertical-align:top;width:16px;color:#dc2626;font-weight:bold;font-size:9pt;text-align:center;">&#10007;</td>
                    <td style="background:#fff5f5;padding:4px 10px 4px 4px;font-size:8pt;color:#374151;vertical-align:top;line-height:1.4;">{{ $line }}</td>
                </tr>
                @endforeach
            </table>
        </td>
        @endif
    </tr>
</table>
@endif

{{-- ── CHILD POLICY ── --}}
@if($toLines($childPolicy)->count())
<div class="section-title"><span class="tick">|</span> KEBIJAKAN ANAK</div>
<div class="policy-box">
    <table width="100%" style="border-collapse:collapse;">
        @foreach($toLines($childPolicy) as $line)
        <tr>
            <td style="width:14px;padding:3px 6px 3px 0;vertical-align:top;color:#0f3460;font-weight:bold;">&#8226;</td>
            <td style="padding:3px 0;font-size:8pt;color:#374151;vertical-align:top;line-height:1.4;">{{ $line }}</td>
        </tr>
        @endforeach
    </table>
</div>
@endif

{{-- ── TERMS ── --}}
@if(trim((string) $terms) !== '')
<div class="section-title"><span class="tick">|</span> SYARAT &amp; KETENTUAN</div>
<div class="terms-box">{!! nl2br(e($terms)) !!}</div>
@endif

@if($tour->notes)
<div class="section-title"><span class="tick">|</span> CATATAN</div>
<div class="terms-box note">{!! nl2br(e($tour->notes)) !!}</div>
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
            <div><span class="validity-badge">Quotation valid 14 hari</span></div>
        </td>
        <td style="width:42%;">
            <div class="sign-line">&nbsp;</div>
            <div class="sign-label">Hormat kami,</div>
            <div class="sign-name">{{ $tour->sales_person ?: $company['brand'] }}</div>
        </td>
    </tr>
</table>
</div>

</body>
</html>
