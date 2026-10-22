<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $inspection->number }} | Lembar P2H</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { font: 12px/1.45 Arial, Helvetica, sans-serif; color: #111; margin: 0; padding: 16px; }
        h1 { font-size: 17px; margin: 0; } h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .06em; margin: 14px 0 4px; color: #444; }
        .head { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #111; padding-bottom: 8px; }
        .result { font-size: 18px; font-weight: 700; padding: 4px 14px; border: 2px solid; border-radius: 6px; }
        .fit { color: #166534; } .unfit { color: #991b1b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f1f1f1; width: 22%; font-weight: 600; }
        .grp td { background: #eee; font-weight: 700; }
        .bad td { background: #fff1f1; }
        .sign { display: flex; gap: 24px; margin-top: 28px; } .sign > div { flex: 1; text-align: center; } .sign .line { height: 54px; border-bottom: 1px solid #111; }
        .actions { margin-bottom: 12px; } @media print { .actions { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
@php($fit = $inspection->isFit())
<div class="actions"><button onclick="window.print()">Cetak / simpan PDF</button> &nbsp; <a href="{{ route('p2h.show', $inspection) }}">Kembali</a></div>

<div class="head">
    <div><h1>LEMBAR P2H</h1><div>Pemeriksaan dan Perawatan Harian &middot; {{ $inspection->number }}</div></div>
    <div class="result {{ $fit ? 'fit' : 'unfit' }}">{{ $fit ? 'LAYAK' : 'TIDAK LAYAK' }}</div>
</div>

<h2>Data pemeriksaan</h2>
<table>
    <tr><th>Unit</th><td>{{ $inspection->unit_code }} &middot; {{ $inspection->unit_name }}</td><th>Plat</th><td>{{ $inspection->unit_plate ?: '-' }}</td></tr>
    <tr><th>Waktu</th><td>{{ $inspection->inspected_at->translatedFormat('d F Y H:i') }}</td><th>Shift</th><td>{{ $inspection->shiftLabel() }}</td></tr>
    <tr><th>Pemeriksa</th><td>{{ $inspection->driver_name }} (NIK {{ $inspection->driver_nik ?: '-' }})</td><th>Checklist</th><td>{{ $inspection->checklist_version_name }}</td></tr>
    <tr><th>Odometer</th><td>{{ $inspection->odometer !== null ? number_format($inspection->odometer, 0, ',', '.').' km' : '-' }}</td><th>Level BBM</th><td>{{ $inspection->fuel_level ?: '-' }}</td></tr>
</table>

<h2>Hasil checklist</h2>
<table>
    @foreach ($answers as $group => $rows)
        <tr class="grp"><td colspan="3">{{ $group }}</td></tr>
        @foreach ($rows as $a)
            <tr class="{{ $a->isBad() ? 'bad' : '' }}">
                <td>{{ $a->label }}@if ($a->is_critical) <strong>(kritis)</strong>@endif @if ($a->isBad() && $a->note)<br><em>{{ $a->note }}</em>@endif</td>
                <td style="width:70px; text-align:center">{{ $a->isBad() ? 'RUSAK' : 'Baik' }}</td>
            </tr>
        @endforeach
    @endforeach
</table>

@if ($inspection->notes)<h2>Catatan</h2><div style="white-space:pre-line">{{ $inspection->notes }}</div>@endif

@if ($inspection->follow_up_status !== 'none')
    <h2>Tindak lanjut</h2>
    <table>
        <tr><th>Status</th><td>{{ \App\Http\Controllers\RepairController::STATUS_LABELS[$inspection->follow_up_status] ?? $inspection->follow_up_status }}</td><th>PIC</th><td>{{ $inspection->follow_up_pic ?: '-' }}</td></tr>
        @if ($inspection->follow_up_status === 'done')
            <tr><th>Catatan</th><td colspan="3">{{ $inspection->follow_up_note }} ({{ $inspection->follow_up_by_name }}, {{ $inspection->follow_up_at?->translatedFormat('d M Y H:i') }})</td></tr>
        @endif
    </table>
@endif

<div class="sign">
    <div><div class="line"></div>Pemeriksa<br>{{ $inspection->driver_name }}@if ($inspection->declared_at)<br><small>Dinyatakan benar {{ $inspection->declared_at->translatedFormat('d M Y H:i') }}</small>@endif</div>
    <div><div class="line"></div>Pengawas</div>
</div>
</body>
</html>
