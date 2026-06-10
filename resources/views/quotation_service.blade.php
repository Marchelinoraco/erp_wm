<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Quotation {{ $tour->code }}</title>
<style>
    @page { footer: html_pf; }

    body {
        font-family: dejavusans, sans-serif;
        font-size: 9pt;
        color: #1f2937;
    }

    /* ── Header ── */
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

    /* ── Section title ── */
    .section-title {
        font-size: 9pt; font-weight: bold; letter-spacing: 1.5px; color: #0f3460;
        border-bottom: 2px solid #0f3460; padding-bottom: 5px; margin: 14px 0 11px;
        page-break-after: avoid;
    }
    .section-title .tick { color: #c0272d; }

    /* ── Terms / Notes ── */
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

    /* ── Footer ── */
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
    $fmt     = fn ($v) => number_format((float) $v, 0, ',', '.');
    $toLines = fn ($t) => collect(explode("\n", (string) $t))->map(fn ($l) => trim($l))->filter()->values();
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
                    {{ $tour->code }}<br>Hal. {PAGENO} / {nbpg}
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
                    <div style="font-size:6pt;font-weight:bold;color:#64748b;letter-spacing:1px;margin-bottom:3px;">{{ strtoupper($tour->type_label ?? 'Layanan') }}</div>
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

{{-- ── DETAIL LAYANAN ── --}}
@php
    $details    = $tour->details ?? [];
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
        <td style="width:32%;padding:6px 12px;font-size:8.5pt;color:#64748b;background:#f8fafc;border:1px solid #e2e8f0;vertical-align:top;">{{ $r['label'] }}</td>
        <td style="padding:6px 12px;font-size:8.5pt;color:#1f2937;border:1px solid #e2e8f0;vertical-align:top;font-weight:bold;">{{ $r['value'] === true ? 'Ya' : $r['value'] }}</td>
    </tr>
    @endforeach
</table>
@endif

{{-- ── HARGA ── --}}
<div class="section-title"><span class="tick">|</span> HARGA</div>
<table width="100%" style="border-collapse:collapse;margin-bottom:14px;">
    <tr>
        <td style="background:#f8fafc;border:1px solid #e2e8f0;padding:9px 14px;font-size:8.5pt;color:#64748b;width:50%;">
            Total Biaya ({{ $tour->pax }} Pax)
        </td>
        <td style="border:1px solid #e2e8f0;padding:9px 14px;font-size:10pt;font-weight:bold;color:#0f3460;text-align:right;">
            IDR {{ $fmt($tour->total_sell) }}
        </td>
    </tr>
    @if($tour->pax > 1)
    <tr>
        <td style="background:#f8fafc;border:1px solid #e2e8f0;padding:9px 14px;font-size:8.5pt;color:#64748b;">
            Biaya per Pax
        </td>
        <td style="border:1px solid #e2e8f0;padding:9px 14px;font-size:10pt;font-weight:bold;color:#0f3460;text-align:right;">
            IDR {{ $fmt($tour->total_sell / max(1, $tour->pax)) }}
        </td>
    </tr>
    @endif
    <tr>
        <td style="background:#0f3460;border:1px solid #0f3460;padding:11px 14px;font-size:10pt;font-weight:bold;color:#c9d6ea;letter-spacing:1px;">
            TOTAL
        </td>
        <td style="background:#0f3460;border:1px solid #0f3460;padding:11px 14px;font-size:11.5pt;font-weight:bold;color:#ffffff;text-align:right;">
            IDR {{ $fmt($tour->total_sell) }}
        </td>
    </tr>
</table>
@if($tour->price_validity)
<p style="font-size:7.5pt;color:#64748b;margin:0 0 14px;">Harga berlaku s/d {{ \Carbon\Carbon::parse($tour->price_validity)->format('d F Y') }}.</p>
@endif

{{-- ── INCLUDED / EXCLUDED (hanya jika diisi manual) ── --}}
@php
    $incLines = $toLines($included);
    $excLines = $toLines($excluded);
@endphp
@if($incLines->count() || $excLines->count())
<table width="100%" style="border-collapse:collapse;margin-bottom:16px;">
    <tr>
        @if($incLines->count())
        <td style="width:{{ $excLines->count() ? '49%' : '100%' }};vertical-align:top;padding-right:{{ $excLines->count() ? '6px' : '0' }};">
            <table width="100%" style="border-collapse:collapse;border:1.5px solid #86efac;">
                <tr>
                    <td colspan="2" style="background:#16a34a;color:#ffffff;font-size:8pt;font-weight:bold;padding:7px 12px;letter-spacing:1px;">
                        &#10004; SUDAH TERMASUK
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
                        &#10008; BELUM TERMASUK
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
