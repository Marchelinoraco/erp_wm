<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>{{ $title }}</title>
<style>
    @page { footer: html_pf; }
    body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #1f2937; }

    .topbar { background: #0f3460; color: #fff; padding: 12px 16px; border-radius: 8px 8px 0 0; }
    .topbar-table { width: 100%; }
    .topbar-table td { vertical-align: middle; }
    .brand-logo { width: 42px; height: 42px; background: #000; border-radius: 6px; text-align: center; }
    .brand-logo img { width: 34px; height: 34px; margin-top: 4px; }
    .brand-name { font-size: 15pt; font-weight: bold; color: #fff; }
    .brand-tagline { font-size: 6.5pt; color: #e0b667; letter-spacing: 2px; }
    .doc-box { text-align: right; }
    .doc-title { font-size: 13pt; font-weight: bold; color: #e0b667; letter-spacing: 1px; }
    .doc-period { font-size: 8pt; color: #c9d6ea; }
    .accent-stripe { height: 3px; background: #c0272d; font-size: 0; line-height: 3px; margin-bottom: 14px; }

    table.rpt { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.rpt thead td, table.rpt thead th {
        background: #0f3460; color: #fff; padding: 7px 9px; font-size: 8pt; font-weight: bold;
        border: 1px solid #1e406e; text-align: left;
    }
    table.rpt thead td.r, table.rpt thead th.r { text-align: right; }
    table.rpt thead td.c, table.rpt thead th.c { text-align: center; }
    table.rpt tbody td { padding: 5px 9px; font-size: 8.5pt; border: 1px solid #e2e8f0; vertical-align: top; }
    table.rpt tbody td.r { text-align: right; }
    table.rpt tbody td.c { text-align: center; }
    table.rpt tbody tr.alt td { background: #f6f8fb; }
    table.rpt tbody td.sub { color: #64748b; font-size: 7.5pt; }
    table.rpt tfoot td { padding: 7px 9px; font-size: 9pt; font-weight: bold; border: 1px solid #cbd5e1; background: #eef2f7; }
    table.rpt tfoot td.r { text-align: right; }
    table.rpt tr.group td { background: #f1f5f9; font-weight: bold; color: #0f3460; font-size: 8pt; }

    .section-title { font-size: 10pt; font-weight: bold; color: #0f3460; margin: 6px 0 8px; }
    .muted { color: #64748b; font-size: 8pt; }
    .ok-badge { display: inline-block; background: #dcfce7; color: #166534; padding: 2px 10px; border-radius: 12px; font-size: 8pt; font-weight: bold; }

    .pagefoot { background: #16213e; border-radius: 6px; padding: 4px 10px; }
    .pagefoot-table { width: 100%; }
    .pf-brand { font-size: 7pt; font-weight: bold; color: #fff; }
    .pf-meta { font-size: 6pt; color: #8ea2c2; }
    .pf-page { text-align: right; font-size: 6.5pt; color: #aebfd6; }
</style>
</head>
<body>

<htmlpagefooter name="pf">
    <div class="pagefoot">
        <table class="pagefoot-table">
            <tr>
                <td>
                    <div class="pf-brand">{{ $company['legal_name'] }}</div>
                    <div class="pf-meta">{{ $company['address'] }}</div>
                </td>
                <td class="pf-page" style="width:130px;">
                    Dicetak {{ now()->format('d M Y H:i') }}<br>Hal. {PAGENO} / {nbpg}
                </td>
            </tr>
        </table>
    </div>
</htmlpagefooter>

<div class="topbar">
    <table class="topbar-table">
        <tr>
            @if($logo)
            <td style="width:50px;"><div class="brand-logo"><img src="{{ $logo }}" width="34" height="34" style="width:34px;height:34px;"></div></td>
            @endif
            <td style="padding-left:10px;">
                <div class="brand-name">{{ $company['brand'] }}</div>
                <div class="brand-tagline">{{ strtoupper($company['tagline']) }}</div>
            </td>
            <td class="doc-box">
                <div class="doc-title">{{ strtoupper($title) }}</div>
                <div class="doc-period">{{ $period }}</div>
            </td>
        </tr>
    </table>
</div>
<div class="accent-stripe">&nbsp;</div>

@yield('content')

</body>
</html>
