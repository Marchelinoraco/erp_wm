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
        padding: 24px 32px;
        position: relative;
        overflow: hidden;
    }
    .header::after {
        content: '';
        position: absolute;
        right: -30px;
        top: -30px;
        width: 160px;
        height: 160px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }
    .header-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
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
    .doc-label {
        text-align: right;
    }
    .doc-label .title {
        font-size: 14pt;
        font-weight: 700;
        color: #e8c47a;
        letter-spacing: 1px;
    }
    .doc-label .code {
        font-size: 9pt;
        color: rgba(255,255,255,0.8);
        margin-top: 3px;
        font-family: 'Courier New', monospace;
    }
    .header-bottom {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid rgba(255,255,255,0.2);
        font-size: 7.5pt;
        color: rgba(255,255,255,0.65);
    }

    /* ── Content ── */
    .content { padding: 24px 32px; }

    /* ── Info boxes ── */
    .info-row {
        display: flex;
        gap: 16px;
        margin-bottom: 20px;
    }
    .info-box {
        flex: 1;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 12px 14px;
        background: #f8fafc;
    }
    .info-box.highlight {
        border-color: #0f3460;
        background: #f0f4ff;
    }
    .info-box-label {
        font-size: 6.5pt;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 5px;
    }
    .info-box-value {
        font-size: 9.5pt;
        font-weight: 600;
        color: #1a1a2e;
        line-height: 1.4;
    }
    .info-box-sub {
        font-size: 8pt;
        color: #64748b;
        margin-top: 2px;
    }

    /* ── Section title ── */
    .section-title {
        font-size: 8pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: #0f3460;
        border-bottom: 2px solid #0f3460;
        padding-bottom: 5px;
        margin-bottom: 12px;
    }

    /* ── Items table ── */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 16px;
    }
    thead tr {
        background: #0f3460;
        color: white;
    }
    thead th {
        padding: 8px 10px;
        text-align: left;
        font-size: 7.5pt;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    thead th.right { text-align: right; }
    thead th.center { text-align: center; }

    tbody tr {
        border-bottom: 1px solid #f1f5f9;
    }
    tbody tr:nth-child(even) {
        background: #f8fafc;
    }
    tbody td {
        padding: 7px 10px;
        font-size: 8.5pt;
        vertical-align: top;
    }
    tbody td.right { text-align: right; }
    tbody td.center { text-align: center; }
    tbody td.muted { color: #64748b; font-size: 7.5pt; }

    .type-badge {
        display: inline-block;
        padding: 1px 6px;
        border-radius: 10px;
        font-size: 6.5pt;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .badge-hotel      { background: #dbeafe; color: #1e40af; }
    .badge-transport  { background: #dcfce7; color: #166534; }
    .badge-guide      { background: #fef3c7; color: #92400e; }
    .badge-restaurant { background: #fce7f3; color: #9d174d; }
    .badge-attraction { background: #f3e8ff; color: #6b21a8; }
    .badge-other      { background: #f1f5f9; color: #475569; }

    /* ── Totals ── */
    .totals-wrap {
        display: flex;
        justify-content: flex-end;
        margin-top: -8px;
        margin-bottom: 20px;
    }
    .totals-box {
        width: 240px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        overflow: hidden;
    }
    .totals-row {
        display: flex;
        justify-content: space-between;
        padding: 7px 14px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 8.5pt;
    }
    .totals-row.grand {
        background: #0f3460;
        color: white;
        font-weight: 700;
        font-size: 10pt;
        border-bottom: none;
    }
    .totals-row .label { color: #64748b; }
    .totals-row.grand .label { color: rgba(255,255,255,0.8); }
    .totals-row .amount { font-family: 'Courier New', monospace; font-weight: 600; }

    /* ── Notes ── */
    .notes-box {
        border-left: 3px solid #e8c47a;
        background: #fffbeb;
        padding: 10px 14px;
        border-radius: 0 6px 6px 0;
        margin-bottom: 20px;
        font-size: 8pt;
        color: #78350f;
        line-height: 1.6;
    }

    /* ── Inclusions ── */
    .inclusion-grid {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
    }
    .inclusion-col { flex: 1; }
    .inclusion-title {
        font-size: 7.5pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #0f3460;
        margin-bottom: 6px;
    }
    .inclusion-item {
        display: flex;
        align-items: flex-start;
        gap: 5px;
        font-size: 8pt;
        margin-bottom: 3px;
        color: #374151;
    }
    .inclusion-dot { color: #e8c47a; font-size: 10pt; line-height: 1; }

    /* ── Footer ── */
    .footer {
        margin-top: 24px;
        padding-top: 14px;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
    }
    .footer-left {
        font-size: 7.5pt;
        color: #94a3b8;
        line-height: 1.7;
    }
    .footer-right {
        text-align: center;
    }
    .footer-right .sign-line {
        width: 120px;
        border-bottom: 1px solid #64748b;
        margin: 40px auto 4px;
    }
    .footer-right .sign-label {
        font-size: 7pt;
        color: #64748b;
    }
    .validity-badge {
        display: inline-block;
        background: #dcfce7;
        color: #166534;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 7.5pt;
        font-weight: 600;
        margin-top: 8px;
    }
</style>
</head>
<body>

{{-- ── HEADER ── --}}
<div class="header">
    <div class="header-top">
        <div>
            <div class="company-name">Welcome Manado</div>
            <div class="company-tagline">Tour &amp; Travel Services</div>
        </div>
        <div class="doc-label">
            <div class="title">QUOTATION</div>
            <div class="code">{{ $tour->code }}</div>
            <div class="code" style="margin-top:3px;">{{ now()->format('d F Y') }}</div>
        </div>
    </div>
    <div class="header-bottom">
        Manado, Sulawesi Utara &nbsp;|&nbsp; welcomemanado.com &nbsp;|&nbsp; Instagram: @welcomemanado
    </div>
</div>

<div class="content">

    {{-- ── INFO ROW ── --}}
    <div class="info-row">
        {{-- Customer --}}
        <div class="info-box">
            <div class="info-box-label">Kepada / To</div>
            <div class="info-box-value">{{ $tour->customer?->name ?? 'Valued Guest' }}</div>
            @if($tour->customer?->country)
            <div class="info-box-sub">{{ $tour->customer->country }}</div>
            @endif
            @if($tour->customer?->contact_person)
            <div class="info-box-sub">Attn: {{ $tour->customer->contact_person }}</div>
            @endif
        </div>

        {{-- Tour Info --}}
        <div class="info-box">
            <div class="info-box-label">Paket Tour</div>
            <div class="info-box-value">{{ $tour->title ?? $tour->code }}</div>
            <div class="info-box-sub">{{ $tour->pax }} Pax</div>
        </div>

        {{-- Dates --}}
        <div class="info-box">
            <div class="info-box-label">Tanggal</div>
            @if($tour->start_date)
            <div class="info-box-value">
                {{ \Carbon\Carbon::parse($tour->start_date)->format('d M Y') }}
            </div>
            <div class="info-box-sub">
                s/d {{ \Carbon\Carbon::parse($tour->end_date)->format('d M Y') }}
            </div>
            @else
            <div class="info-box-value">TBD</div>
            @endif
        </div>

        {{-- Sales --}}
        <div class="info-box highlight">
            <div class="info-box-label">Sales Contact</div>
            <div class="info-box-value">{{ $tour->sales_person ?: 'Welcome Manado' }}</div>
            <div class="info-box-sub">welcomemanado.com</div>
        </div>
    </div>

    {{-- ── ITEMS TABLE ── --}}
    <div class="section-title">Rincian Layanan</div>
    <table>
        <thead>
            <tr>
                <th style="width:30px;" class="center">Hari</th>
                <th>Deskripsi Layanan</th>
                <th style="width:60px;" class="center">Tipe</th>
                <th style="width:35px;" class="center">Qty</th>
                <th style="width:35px;" class="center">Mlm</th>
                <th style="width:90px;" class="right">Harga Satuan</th>
                <th style="width:90px;" class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tour->items->sortBy('sort_order') as $item)
            <tr>
                <td class="center muted">
                    {{ $item->day_number ? 'D-' . $item->day_number : '—' }}
                </td>
                <td>
                    <strong>{{ $item->description }}</strong>
                </td>
                <td class="center">
                    @php
                        $badgeClass = match($item->product_type) {
                            'hotel'       => 'badge-hotel',
                            'transport'   => 'badge-transport',
                            'guide'       => 'badge-guide',
                            'restaurant'  => 'badge-restaurant',
                            'attraction'  => 'badge-attraction',
                            default       => 'badge-other',
                        };
                        $typeLabel = match($item->product_type) {
                            'hotel'       => 'Hotel',
                            'transport'   => 'Transport',
                            'guide'       => 'Guide',
                            'restaurant'  => 'Resto',
                            'attraction'  => 'Wisata',
                            default       => 'Lain',
                        };
                    @endphp
                    <span class="type-badge {{ $badgeClass }}">{{ $typeLabel }}</span>
                </td>
                <td class="center">{{ $item->qty }}</td>
                <td class="center">{{ $item->nights }}</td>
                <td class="right">
                    {{ $item->currency }} {{ number_format($item->unit_sell, 0, ',', '.') }}
                </td>
                <td class="right">
                    <strong>{{ $item->currency }} {{ number_format($item->line_sell, 0, ',', '.') }}</strong>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center; padding:16px; color:#94a3b8;">
                    Belum ada item
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── TOTALS ── --}}
    <div class="totals-wrap">
        <div class="totals-box">
            <div class="totals-row">
                <span class="label">Subtotal</span>
                <span class="amount">IDR {{ number_format($tour->total_sell, 0, ',', '.') }}</span>
            </div>
            <div class="totals-row">
                <span class="label">Pajak / Tax</span>
                <span class="amount">—</span>
            </div>
            <div class="totals-row grand">
                <span class="label">TOTAL</span>
                <span class="amount">IDR {{ number_format($tour->total_sell, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    {{-- ── NOTES ── --}}
    @if($tour->notes)
    <div class="section-title">Catatan</div>
    <div class="notes-box">{{ $tour->notes }}</div>
    @endif

    {{-- ── WHAT'S INCLUDED ── --}}
    <div class="section-title">Informasi Tambahan</div>
    <div class="inclusion-grid">
        <div class="inclusion-col">
            <div class="inclusion-title">Termasuk (Included)</div>
            <div class="inclusion-item"><span class="inclusion-dot">✦</span> Layanan sesuai program di atas</div>
            <div class="inclusion-item"><span class="inclusion-dot">✦</span> Tour Leader / Guide lokal berbahasa Indonesia</div>
            <div class="inclusion-item"><span class="inclusion-dot">✦</span> Transportasi sesuai program</div>
            <div class="inclusion-item"><span class="inclusion-dot">✦</span> Akomodasi sesuai program</div>
        </div>
        <div class="inclusion-col">
            <div class="inclusion-title">Tidak Termasuk (Excluded)</div>
            <div class="inclusion-item"><span class="inclusion-dot">✦</span> Tiket pesawat</div>
            <div class="inclusion-item"><span class="inclusion-dot">✦</span> Pengeluaran pribadi</div>
            <div class="inclusion-item"><span class="inclusion-dot">✦</span> Tip guide / driver</div>
            <div class="inclusion-item"><span class="inclusion-dot">✦</span> Visa (jika diperlukan)</div>
        </div>
        <div class="inclusion-col">
            <div class="inclusion-title">Syarat &amp; Ketentuan</div>
            <div class="inclusion-item"><span class="inclusion-dot">✦</span> DP 50% untuk konfirmasi booking</div>
            <div class="inclusion-item"><span class="inclusion-dot">✦</span> Pelunasan H-7 keberangkatan</div>
            <div class="inclusion-item"><span class="inclusion-dot">✦</span> Pembatalan dikenakan biaya sesuai kebijakan</div>
            <div class="inclusion-item"><span class="inclusion-dot">✦</span> Harga dapat berubah sewaktu-waktu</div>
        </div>
    </div>

    {{-- ── FOOTER ── --}}
    <div class="footer">
        <div class="footer-left">
            <strong>Welcome Manado Tour &amp; Travel</strong><br>
            Manado, Sulawesi Utara, Indonesia<br>
            welcomemanado.com<br>
            <span class="validity-badge">✓ Quotation valid 14 hari</span>
        </div>
        <div class="footer-right">
            <div class="sign-line"></div>
            <div class="sign-label">Welcome Manado</div>
            <div class="sign-label" style="color:#0f3460; font-weight:600;">{{ $tour->sales_person ?: 'Tour Consultant' }}</div>
        </div>
    </div>

</div>
</body>
</html>
