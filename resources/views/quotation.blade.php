<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Quotation {{ $tour->code }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'DejaVu Sans', Arial, sans-serif;
        font-size: 9pt;
        color: #1a1a2e;
        background: #fff;
    }

    /* ── Header ── */
    .header {
        background: linear-gradient(135deg, #0f3460 0%, #16213e 100%);
        color: white;
        padding: 22px 32px;
    }
    .header-table { width: 100%; }
    .header-table td { vertical-align: top; }
    .company-name {
        font-size: 22pt;
        font-weight: 700;
        letter-spacing: 1px;
        color: #e8c47a;
    }
    .company-tagline {
        font-size: 8pt;
        color: rgba(255,255,255,0.7);
        margin-top: 2px;
        letter-spacing: 2px;
        text-transform: uppercase;
    }
    .doc-label { text-align: right; }
    .doc-label .title {
        font-size: 14pt;
        font-weight: 700;
        color: #e8c47a;
        letter-spacing: 1px;
    }
    .doc-label .code {
        font-size: 9pt;
        color: rgba(255,255,255,0.85);
        margin-top: 3px;
        font-family: 'Courier New', monospace;
    }
    .header-bottom {
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px solid rgba(255,255,255,0.2);
        font-size: 7.5pt;
        color: rgba(255,255,255,0.7);
    }

    .content { padding: 22px 32px; }

    /* ── Info boxes (table layout for dompdf reliability) ── */
    .info-table { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 18px; }
    .info-box {
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 10px 12px;
        background: #f8fafc;
        vertical-align: top;
    }
    .info-box.highlight { border-color: #0f3460; background: #f0f4ff; }
    .info-box-label {
        font-size: 6.5pt; font-weight: 700; color: #64748b;
        text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;
    }
    .info-box-value { font-size: 9.5pt; font-weight: 600; color: #1a1a2e; line-height: 1.35; }
    .info-box-sub { font-size: 8pt; color: #64748b; margin-top: 2px; }

    /* ── Section title ── */
    .section-title {
        font-size: 8.5pt; font-weight: 700; text-transform: uppercase;
        letter-spacing: 1.5px; color: #0f3460;
        border-bottom: 2px solid #0f3460; padding-bottom: 5px;
        margin-bottom: 12px; margin-top: 4px;
    }

    /* ── Itinerary (day-by-day) ── */
    .day { margin-bottom: 12px; page-break-inside: avoid; }
    .day-head {
        background: #0f3460; color: #fff;
        padding: 6px 12px; border-radius: 5px;
        font-size: 8.5pt; font-weight: 700; letter-spacing: 0.3px;
    }
    .day-body {
        padding: 8px 12px 2px;
        font-size: 8.5pt; color: #374151; line-height: 1.55; text-align: justify;
    }
    .day-hours { margin: 6px 0 2px; padding-left: 4px; }
    .day-hour {
        font-size: 8pt; color: #475569; margin-bottom: 2px;
    }
    .day-hour .time {
        display: inline-block; min-width: 78px;
        font-weight: 700; color: #0f3460; font-family: 'Courier New', monospace;
    }

    /* ── Pricing matrix ── */
    table.matrix { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    table.matrix thead tr { background: #0f3460; color: #fff; }
    table.matrix thead th {
        padding: 7px 8px; font-size: 7.5pt; font-weight: 600;
        border: 1px solid #1e406e; text-align: center;
    }
    table.matrix thead th.left { text-align: left; }
    table.matrix thead th .tier-note { font-weight: 400; font-size: 6.5pt; color: rgba(255,255,255,0.75); display: block; }
    table.matrix tbody td {
        padding: 6px 8px; font-size: 8pt; border: 1px solid #e2e8f0; text-align: right;
        font-family: 'Courier New', monospace;
    }
    table.matrix tbody td.name { text-align: left; font-family: 'DejaVu Sans', Arial, sans-serif; }
    table.matrix tbody td.name strong { color: #1a1a2e; }
    table.matrix tbody td.name span { color: #64748b; font-size: 7pt; display: block; }
    table.matrix tbody tr:nth-child(even) { background: #f8fafc; }
    .price-caption { font-size: 7.5pt; color: #64748b; margin: 0 0 16px; }

    .simple-price-wrap { margin-bottom: 18px; }
    .simple-price-box {
        border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; width: 280px; float: right;
    }
    .sp-row { padding: 8px 14px; border-bottom: 1px solid #f1f5f9; font-size: 9pt; }
    .sp-row .lbl { color: #64748b; }
    .sp-row .amt { float: right; font-family: 'Courier New', monospace; font-weight: 600; }
    .sp-row.grand { background: #0f3460; color: #fff; font-weight: 700; font-size: 10.5pt; border-bottom: none; }
    .sp-row.grand .lbl { color: rgba(255,255,255,0.85); }

    /* ── Two column inc/exc ── */
    .twocol { width: 100%; border-collapse: separate; border-spacing: 10px 0; margin-bottom: 16px; }
    .twocol td { vertical-align: top; width: 50%; }
    .col-title {
        font-size: 7.5pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;
        margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid #e2e8f0;
    }
    .col-title.inc { color: #166534; }
    .col-title.exc { color: #b91c1c; }
    .li { font-size: 8pt; color: #374151; margin-bottom: 3px; line-height: 1.4; }
    .li .mk-inc { color: #16a34a; font-weight: 700; }
    .li .mk-exc { color: #dc2626; font-weight: 700; }

    .policy-box {
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
        padding: 10px 14px; margin-bottom: 16px;
    }
    .policy-box .li { margin-bottom: 4px; }
    .terms-box {
        border-left: 3px solid #e8c47a; background: #fffbeb;
        padding: 10px 14px; border-radius: 0 6px 6px 0; margin-bottom: 16px;
        font-size: 7.8pt; color: #78350f; line-height: 1.55;
    }
    .validity-badge {
        display: inline-block; background: #dcfce7; color: #166534;
        padding: 3px 10px; border-radius: 20px; font-size: 7.5pt; font-weight: 600;
    }

    /* ── Footer ── */
    .footer {
        margin-top: 18px; padding-top: 12px; border-top: 2px solid #0f3460;
    }
    .footer-table { width: 100%; }
    .footer-table td { vertical-align: bottom; }
    .footer-company { font-size: 8pt; font-weight: 700; color: #0f3460; }
    .footer-meta { font-size: 7pt; color: #64748b; line-height: 1.6; }
    .sign-line { width: 150px; border-bottom: 1px solid #64748b; margin: 36px 0 4px auto; }
    .sign-label { font-size: 7pt; color: #64748b; text-align: right; }
    .clear { clear: both; }
</style>
</head>
<body>

@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', '.');

    $pricing  = $tour->pricing ?? [];
    $tiers    = $pricing['tiers'] ?? [];
    $base     = $pricing['base'] ?? null;
    $hotels   = $pricing['hotels'] ?? [];
    $optionals = $pricing['optionals'] ?? [];

    $baseEnabled  = $base && ($base['enabled'] ?? true) && collect($base['prices'] ?? [])->filter()->isNotEmpty();
    $hasMatrix    = count($tiers) > 0 && ($baseEnabled || count($hotels) > 0);
    $hasSingleSup = collect($hotels)->contains(fn ($h) => ! empty($h['single_sup']));

    $toLines = fn ($t) => collect(explode("\n", (string) $t))->map(fn ($l) => trim($l))->filter()->values();
@endphp

{{-- ── HEADER ── --}}
<div class="header">
    <table class="header-table">
        <tr>
            <td>
                <div class="company-name">{{ $company['brand'] }}</div>
                <div class="company-tagline">Tour &amp; Travel Services</div>
            </td>
            <td class="doc-label">
                <div class="title">QUOTATION</div>
                <div class="code">{{ $tour->code }}</div>
                <div class="code" style="margin-top:3px;">{{ now()->format('d F Y') }}</div>
            </td>
        </tr>
    </table>
    <div class="header-bottom">
        {{ $company['address'] }} &nbsp;|&nbsp; {{ $company['phone'] }} &nbsp;|&nbsp; {{ $company['website'] }}
    </div>
</div>

<div class="content">

    {{-- ── INFO ROW ── --}}
    <table class="info-table">
        <tr>
            <td class="info-box" style="width:30%;">
                <div class="info-box-label">Kepada / To</div>
                <div class="info-box-value">{{ $tour->customer?->name ?? 'Valued Guest' }}</div>
                @if($tour->customer?->country)
                <div class="info-box-sub">{{ $tour->customer->country }}</div>
                @endif
            </td>
            <td class="info-box" style="width:30%;">
                <div class="info-box-label">Paket Tour</div>
                <div class="info-box-value">{{ $tour->title ?? $tour->code }}</div>
                <div class="info-box-sub">{{ $tour->pax }} Pax</div>
            </td>
            <td class="info-box" style="width:20%;">
                <div class="info-box-label">Tanggal</div>
                @if($tour->start_date)
                <div class="info-box-value">{{ \Carbon\Carbon::parse($tour->start_date)->format('d M Y') }}</div>
                <div class="info-box-sub">s/d {{ \Carbon\Carbon::parse($tour->end_date)->format('d M Y') }}</div>
                @else
                <div class="info-box-value">TBD</div>
                @endif
            </td>
            <td class="info-box highlight" style="width:20%;">
                <div class="info-box-label">Sales Contact</div>
                <div class="info-box-value">{{ $tour->sales_person ?: $company['brand'] }}</div>
                <div class="info-box-sub">{{ $company['website'] }}</div>
            </td>
        </tr>
    </table>

    {{-- ── PROGRAM TOUR (itinerary day-by-day) ── --}}
    @if($tour->itineraryDays->count())
        <div class="section-title">Program Tour</div>
        @foreach($tour->itineraryDays as $day)
            @php $hours = $tour->itineraryHours->where('day_number', $day->day_number); @endphp
            <div class="day">
                <div class="day-head">
                    HARI {{ $day->day_number }}@if($day->title): {{ strtoupper($day->title) }}@endif
                </div>
                <div class="day-body">
                    @if($day->description){{ $day->description }}@endif
                    @if($hours->count())
                    <div class="day-hours">
                        @foreach($hours as $h)
                        <div class="day-hour">
                            <span class="time">{{ $h->start_time?->format('H:i') }}@if($h->end_time)–{{ $h->end_time?->format('H:i') }}@endif</span>
                            {{ $h->activity }}@if($h->notes) <em style="color:#94a3b8;">({{ $h->notes }})</em>@endif
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        @endforeach
    @endif

    {{-- ── HARGA ── --}}
    <div class="section-title">Harga Paket</div>

    @if($hasMatrix)
        <table class="matrix">
            <thead>
                <tr>
                    <th class="left">{{ count($hotels) ? 'Hotel / Paket' : 'Paket' }}</th>
                    @foreach($tiers as $tier)
                    <th>
                        {{ $tier['label'] ?? '—' }}
                        @if(!empty($tier['note']))<span class="tier-note">{{ $tier['note'] }}</span>@endif
                    </th>
                    @endforeach
                    @if($hasSingleSup)<th>Sgl. Sup</th>@endif
                </tr>
            </thead>
            <tbody>
                @if($baseEnabled)
                <tr>
                    <td class="name"><strong>{{ $base['label'] ?? 'Tanpa Hotel' }}</strong></td>
                    @foreach($tiers as $tier)
                    <td>{{ isset($base['prices'][$tier['id']]) ? $fmt($base['prices'][$tier['id']]) : '—' }}</td>
                    @endforeach
                    @if($hasSingleSup)<td>—</td>@endif
                </tr>
                @endif
                @foreach($hotels as $hotel)
                <tr>
                    <td class="name">
                        <strong>{{ $hotel['name'] ?? '—' }}</strong>
                        @if(!empty($hotel['room']))<span>{{ $hotel['room'] }}</span>@endif
                    </td>
                    @foreach($tiers as $tier)
                    <td>{{ isset($hotel['prices'][$tier['id']]) ? $fmt($hotel['prices'][$tier['id']]) : '—' }}</td>
                    @endforeach
                    @if($hasSingleSup)<td>{{ !empty($hotel['single_sup']) ? $fmt($hotel['single_sup']) : '—' }}</td>@endif
                </tr>
                @endforeach
            </tbody>
        </table>
        <p class="price-caption">Harga per pax dalam IDR. Sgl. Sup = tambahan untuk kamar single.
            @if($tour->price_validity) Harga berlaku s/d {{ \Carbon\Carbon::parse($tour->price_validity)->format('d F Y') }}.@endif
        </p>
    @else
        {{-- Fallback: harga tunggal dari total jual tour --}}
        <div class="simple-price-wrap">
            <div class="simple-price-box">
                <div class="sp-row"><span class="lbl">Total Paket ({{ $tour->pax }} pax)</span><span class="amt">IDR {{ $fmt($tour->total_sell) }}</span></div>
                @if($tour->pax > 0)
                <div class="sp-row"><span class="lbl">Harga per Pax</span><span class="amt">IDR {{ $fmt($tour->total_sell / $tour->pax) }}</span></div>
                @endif
                <div class="sp-row grand"><span class="lbl">TOTAL</span><span class="amt">IDR {{ $fmt($tour->total_sell) }}</span></div>
            </div>
            <div class="clear"></div>
        </div>
        @if($tour->price_validity)
        <p class="price-caption">Harga berlaku s/d {{ \Carbon\Carbon::parse($tour->price_validity)->format('d F Y') }}.</p>
        @endif
    @endif

    {{-- ── Optional tour ── --}}
    @if(count($optionals))
    <div style="margin-bottom:16px;">
        <div class="col-title" style="color:#0f3460;">Optional Tour</div>
        @foreach($optionals as $opt)
        <div class="li">
            <span class="mk-inc">◦</span> {{ $opt['label'] ?? '' }} — IDR {{ $fmt($opt['price'] ?? 0) }}@if(!empty($opt['note'])) <em style="color:#94a3b8;">({{ $opt['note'] }})</em>@endif
        </div>
        @endforeach
    </div>
    @endif

    {{-- ── INCLUDED / EXCLUDED ── --}}
    <table class="twocol">
        <tr>
            <td>
                <div class="col-title inc">Harga Sudah Termasuk</div>
                @foreach($toLines($included) as $line)
                <div class="li"><span class="mk-inc">✓</span> {{ $line }}</div>
                @endforeach
            </td>
            <td>
                <div class="col-title exc">Harga Belum Termasuk</div>
                @foreach($toLines($excluded) as $line)
                <div class="li"><span class="mk-exc">✗</span> {{ $line }}</div>
                @endforeach
            </td>
        </tr>
    </table>

    {{-- ── CHILD POLICY ── --}}
    @if($toLines($childPolicy)->count())
    <div class="section-title">Kebijakan Anak</div>
    <div class="policy-box">
        @foreach($toLines($childPolicy) as $line)
        <div class="li">• {{ $line }}</div>
        @endforeach
    </div>
    @endif

    {{-- ── TERMS ── --}}
    @if(trim((string) $terms) !== '')
    <div class="section-title">Syarat &amp; Ketentuan</div>
    <div class="terms-box">{!! nl2br(e($terms)) !!}</div>
    @endif

    @if($tour->notes)
    <div class="section-title">Catatan</div>
    <div class="terms-box" style="border-left-color:#0f3460; background:#f0f4ff; color:#1e3a5f;">{!! nl2br(e($tour->notes)) !!}</div>
    @endif

    {{-- ── FOOTER ── --}}
    <div class="footer">
        <table class="footer-table">
            <tr>
                <td style="width:60%;">
                    <div class="footer-company">{{ $company['legal_name'] }}</div>
                    <div class="footer-meta">
                        {{ $company['address'] }}<br>
                        Hp. {{ $company['phone'] }}<br>
                        Email: {{ $company['email'] }}
                    </div>
                    <div style="margin-top:8px;"><span class="validity-badge">Quotation valid 14 hari</span></div>
                </td>
                <td style="width:40%;">
                    <div class="sign-line"></div>
                    <div class="sign-label">{{ $company['brand'] }}</div>
                    <div class="sign-label" style="color:#0f3460; font-weight:700;">{{ $tour->sales_person ?: 'Tour Consultant' }}</div>
                </td>
            </tr>
        </table>
    </div>

</div>
</body>
</html>
