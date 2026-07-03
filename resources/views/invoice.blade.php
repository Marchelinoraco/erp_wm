<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice {{ $invoice->number }}</title>
<style>
    body { font-family: dejavusans, sans-serif; font-size: 10pt; color: #000; }

    .sheet { border: 1.2px solid #000; }

    /* ── Header ── */
    .pad { padding: 12px 18px; }
    .head-table { width: 100%; }
    .head-table td { vertical-align: middle; }
    .logo-cell { width: 130px; text-align: center; }
    .logo-cell img { width: 118px; height: 118px; }
    .co-name { font-size: 14pt; font-weight: bold; letter-spacing: .5px; }
    .co-meta { font-size: 8pt; line-height: 1.5; margin-top: 2px; }
    .co-meta a { color: #000; text-decoration: none; }

    .services {
        border-top: 1.2px solid #000; border-bottom: 1.2px solid #000;
        padding: 6px 18px; text-align: center; font-weight: bold; font-size: 8pt;
    }

    /* ── Invoice title row ── */
    .title-table { width: 100%; margin-bottom: 2px; }
    .title-table td { vertical-align: middle; }
    .inv-title { font-size: 30pt; font-weight: bold; color: #2e74b5; letter-spacing: 1px; }
    .issued-box {
        border: 1.2px solid #000; padding: 7px 14px; text-align: center;
        font-size: 11pt; font-weight: bold;
    }
    .issued-box .hot { color: #c0272d; }
    .page-box {
        border: 1px solid #000; padding: 2px 14px; font-size: 9pt; font-weight: bold;
        display: inline-block;
    }

    /* ── Bill to ── */
    .billto { width: 100%; margin: 10px 0 4px; font-size: 10pt; }
    .billto td { vertical-align: top; padding: 1px 0; }
    .billto .k { width: 130px; font-weight: bold; }
    .billto .strong { font-weight: bold; }

    /* ── Main description / amount table ── */
    table.main { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.main td { font-size: 10pt; }
    table.main thead td {
        border: 1px solid #000; border-bottom: 1px solid #000;
        font-weight: bold; text-align: center; padding: 6px 10px;
    }
    .main .dcell { border-left: 1px solid #000; padding: 3px 12px; vertical-align: top; }
    .main .acell { border-left: 1px solid #000; border-right: 1px solid #000; padding: 3px 12px 3px 4px; text-align: right; white-space: nowrap; vertical-align: top; }
    .main .lead td { padding-top: 9px; }
    .main .tail td { padding-bottom: 9px; }
    .kv { width: 100%; border-collapse: collapse; }
    .kv td { font-size: 10pt; padding: 1px 0; vertical-align: top; }
    .kv td.k { width: 120px; }
    .kv td.s { width: 14px; }
    .gap td { height: 8px; font-size: 0; line-height: 0; }

    .main tr.sumtop td { border-top: 1px solid #000; }
    .main .sumlabel { border-left: 1px solid #000; text-align: right; font-weight: bold; padding: 5px 12px; }
    .main .sumamt   { border-left: 1px solid #000; border-right: 1px solid #000; text-align: right; font-weight: bold; padding: 5px 12px; white-space: nowrap; }
    .main .sumamt.neg { color: #000; }
    .main tr.balance td { border-bottom: 1px solid #000; }
    .main tr.rowend td { border-bottom: 1px solid #000; }

    .note { font-size: 9pt; margin: 8px 0 14px; }
    .note b { font-weight: bold; }
    .note i { font-style: italic; }

    /* ── Payment ── */
    .pay { font-size: 10pt; line-height: 1.6; margin-bottom: 18px; }
    .pay .lead { margin-bottom: 3px; }
    .pay-table { border-collapse: collapse; }
    .pay-table td { font-size: 10pt; padding: 0; vertical-align: top; }
    .pay-table td.k { width: 150px; }
    .pay-table td.s { width: 12px; }

    .proforma {
        border-top: 1.3px solid #c0272d; border-bottom: 1.3px solid #c0272d;
        padding: 9px 4px; text-align: center; font-weight: bold; font-size: 12pt;
        margin: 6px 0 4px;
    }

    .extra-note { font-size: 9pt; color: #334155; margin-top: 10px; line-height: 1.5; }
</style>
</head>
<body>

@php
    $cur  = $invoice->currency ?: 'IDR';
    $fmt  = fn ($v) => $cur . ' ' . number_format((float) $v, 0, ',', '.');
    $tour = $invoice->tour;

    $custName = $tour?->customer?->name ?? 'Valued Guest';
    $party    = trim((string) $invoice->guest_name) !== ''
        ? $invoice->guest_name
        : (($tour?->pax ?? 0) > 1 ? $custName . ' & Party' : $custName);

    $resvDate = $tour?->start_date
        ? \Carbon\Carbon::parse($tour->start_date)->format('d F Y')
          . ($tour->end_date ? ' – ' . \Carbon\Carbon::parse($tour->end_date)->format('d F Y') : '')
        : null;

    $invNote = config('quotation.invoice_note');

    // Domain bersih untuk tampilan "www.domain" tanpa dobel www / skema.
    $domain = preg_replace('#^https?://#', '', (string) $company['website']);
    $domain = preg_replace('#^www\.#', '', $domain);
@endphp

<div class="sheet">

    {{-- ── HEADER ── --}}
    <div class="pad">
        <table class="head-table">
            <tr>
                @if($logo)
                <td class="logo-cell"><img src="{{ $logo }}" width="118" height="118" style="width:118px;height:118px;" alt=""></td>
                @endif
                <td style="text-align:center;">
                    <div class="co-name">{{ $company['legal_name'] }}</div>
                    <div class="co-meta">{{ $company['address'] }}</div>
                    <div class="co-meta">Phone: {{ $company['phone'] }}</div>
                    <div class="co-meta"><a href="https://{{ $domain }}">www.{{ $domain }}</a></div>
                </td>
                @if($logo)<td class="logo-cell">&nbsp;</td>@endif
            </tr>
        </table>
    </div>

    {{-- ── SERVICES BAND ── --}}
    <div class="services">Inbound Tour &ndash; Outbound Tour &ndash; Airline Ticket &ndash; Voucher Hotel &ndash; Rental Car &ndash; MICE &ndash; Incentive Tour</div>

    <div class="pad">

        {{-- ── TITLE + ISSUED ── --}}
        <table class="title-table">
            <tr>
                <td><span class="inv-title">INVOICE</span></td>
                <td style="text-align:right;">
                    <div class="issued-box">
                        Date of Issued: <span class="hot">{{ \Carbon\Carbon::parse($invoice->date)->format('d F Y') }}</span>, No: <span class="hot">{{ $invoice->number }}</span>
                        @if($invoice->due_date)
                        <div style="font-size:10pt; margin-top:3px;">Payment Due: <span class="hot">{{ \Carbon\Carbon::parse($invoice->due_date)->format('d F Y') }}</span></div>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
        <div style="text-align:right;"><span class="page-box">PAGE {PAGENO}</span></div>

        {{-- ── BILL TO ── --}}
        <table class="billto">
            <tr>
                <td class="k">TO</td>
                <td>: <span class="strong">{{ strtoupper($custName) }}</span></td>
            </tr>
            @if($tour?->customer?->phone)
            <tr><td class="k"></td><td>&nbsp;&nbsp;{{ $tour->customer->phone }}</td></tr>
            @endif
            <tr>
                <td class="k" style="padding-top:6px;">RESERVATION</td>
                <td style="padding-top:6px;">: <span class="strong">{{ strtoupper($tour?->title ?? $tour?->code ?? '-') }}</span></td>
            </tr>
        </table>

        {{-- ── DESCRIPTION / AMOUNT ── --}}
        <table class="main">
            <thead>
                <tr>
                    <td style="width:78%;">DESCRIPTION</td>
                    <td style="width:22%;">AMOUNT</td>
                </tr>
            </thead>
            <tbody>
                {{-- Info block + baris deskripsi proforma terstruktur --}}
                <tr class="lead">
                    <td class="dcell">
                        <table class="kv">
                            <tr><td class="k">Guest Name</td><td class="s">:</td><td class="strong">{{ strtoupper($party) }}</td></tr>
                            <tr><td class="k">Reservation</td><td class="s">:</td><td>{{ $tour?->title ?? $tour?->code ?? '-' }}</td></tr>
                            <tr class="gap"><td colspan="3"></td></tr>
                            @if($resvDate)
                            <tr><td class="k">Date</td><td class="s">:</td><td>{{ $resvDate }}</td></tr>
                            @endif
                            @if($tour?->pax)
                            <tr><td class="k">Total Pax</td><td class="s">:</td><td>{{ $tour->pax }} pax</td></tr>
                            @endif

                            @php $prevLbl = null; @endphp
                            @foreach($lines as $ln)
                            @php
                                $lbl = trim($ln['label'] ?? '');
                                $dt  = trim($ln['date'] ?? '');
                                $det = trim($ln['detail'] ?? '');
                                $showLbl = $lbl !== '' && $lbl !== $prevLbl;
                                if ($lbl !== '') { $prevLbl = $lbl; }
                            @endphp
                            @if($lbl !== '' || $dt !== '' || $det !== '')
                            <tr>
                                <td class="k">{{ $showLbl ? $lbl : '' }}</td>
                                <td class="s">{{ $showLbl ? ':' : '' }}</td>
                                <td>
                                    <table style="width:100%; border-collapse:collapse;">
                                        <tr>
                                            @if($dt !== '')
                                            <td style="width:130px; vertical-align:top; padding:0;">{{ $dt }}</td>
                                            <td style="vertical-align:top; padding:0;">{!! nl2br(e($det)) !!}</td>
                                            @else
                                            {{-- Tanpa tanggal: deskripsi langsung setelah titik dua --}}
                                            <td style="vertical-align:top; padding:0;">{!! nl2br(e($det)) !!}</td>
                                            @endif
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            @endif
                            @endforeach

                            <tr class="gap"><td colspan="3"></td></tr>
                        </table>
                    </td>
                    <td class="acell"></td>
                </tr>

                {{-- Baris harga proforma --}}
                <tr>
                    <td class="dcell">
                        <table class="kv">
                            <tr>
                                <td class="k">Price</td>
                                <td class="s">:</td>
                                <td>
                                    @if($unitPrice > 0)
                                        {{ $fmt($unitPrice) }}@if($pax > 0) &times; {{ $pax }} pax @endif
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td class="acell">{{ $fmt($invoice->total) }}</td>
                </tr>

                {{-- Filler for height --}}
                <tr class="tail rowend"><td class="dcell">&nbsp;</td><td class="acell"></td></tr>
            </tbody>

            {{-- Totals --}}
            <tr class="sumtop">
                <td class="sumlabel">Total</td>
                <td class="sumamt">{{ $fmt($invoice->total) }}</td>
            </tr>
            @foreach($invoice->payments as $p)
            <tr>
                <td class="sumlabel" style="font-weight:normal;">Down Payment &ndash; {{ \Carbon\Carbon::parse($p->date)->format('M d, Y') }}</td>
                <td class="sumamt neg">({{ $fmt($p->amount) }})</td>
            </tr>
            @endforeach
            <tr class="balance">
                <td class="sumlabel">Balance Due</td>
                <td class="sumamt">{{ $fmt(max($outstanding, 0)) }}</td>
            </tr>
        </table>

        {{-- ── NOTE ── --}}
        @if($invNote)
        <div class="note"><b>Note:</b> <i>{{ $invNote }}</i></div>
        @endif

        {{-- ── PAYMENT ── --}}
        @if(count($bank))
        <div class="pay">
            <div class="lead">Payment can be made via bank transfer to:</div>
            @foreach($bank as $b)
            <table class="pay-table" @if(!$loop->last) style="margin-bottom:6px;" @endif>
                <tr>
                    <td class="k">Bank</td><td class="s">:</td>
                    <td><span class="strong">{{ $b['bank'] ?? '' }}</span>@if(!empty($b['swift'])) <i>(Swift Code: {{ $b['swift'] }})</i>@endif</td>
                </tr>
                <tr>
                    <td class="k">Account Number</td><td class="s">:</td>
                    <td>{{ $b['account'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="k">Account Name</td><td class="s">:</td>
                    <td>{{ $b['name'] ?? '' }}</td>
                </tr>
            </table>
            @endforeach
        </div>
        @endif

        {{-- Per-invoice note (opsional) --}}
        @if($invoice->notes)
        <div class="extra-note">{!! nl2br(e($invoice->notes)) !!}</div>
        @endif

        {{-- ── PROFORMA LINE ── --}}
        <div class="proforma">THIS PROFORMA INVOICE IS ISSUED TO CONFIRM YOUR RESERVATION</div>

    </div>
</div>

</body>
</html>
