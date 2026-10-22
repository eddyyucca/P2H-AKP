@extends('layouts.app')

@section('title', 'Laporan P2H')
@section('page-title', 'Laporan P2H')
@section('breadcrumb')
    <li class="breadcrumb-item">P2H</li>
    <li class="breadcrumb-item active">Laporan</li>
@endsection

@push('styles')
<style>
    @media print {
        .sidebar-desktop, .app-header, header, footer, .no-print, .breadcrumb { display: none !important; }
        .main-shell, .content-shell { margin: 0 !important; padding: 0 !important; }
        .card { box-shadow: none !important; border: 0 !important; }
    }
</style>
@endpush

@section('content')
@php($q = array_filter(['bulan' => $month->format('Y-m'), 'kategori' => $category]))
<div class="card shadow-sm mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('p2h.report') }}" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label for="bulan" class="form-label small text-muted mb-1">Bulan</label>
                <input type="month" id="bulan" name="bulan" value="{{ $month->format('Y-m') }}" max="{{ today()->format('Y-m') }}" class="form-control">
            </div>
            <div class="col-6 col-md-3">
                <label for="kategori" class="form-label small text-muted mb-1">Kategori unit</label>
                <select id="kategori" name="kategori" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($categories as $c)<option value="{{ $c }}" @selected($category === $c)>{{ $c }}</option>@endforeach
                </select>
            </div>
            <div class="col-12 col-md-6 d-flex flex-wrap gap-2">
                <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Tampilkan</button>
                <a href="{{ route('p2h.report.recap', $q) }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Rekap (Excel)</a>
                <a href="{{ route('p2h.report.findings', $q) }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Temuan (Excel)</a>
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak / PDF</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h2 class="h6 mb-0">Rekap P2H {{ $month->translatedFormat('F Y') }}@if ($category !== '') &middot; kategori {{ $category }}@endif</h2>
    </div>
    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0">
            <thead>
                <tr><th>Unit</th><th class="text-center">P2H</th><th class="text-center">Layak</th><th class="text-center">Tidak layak</th><th class="text-center">Bertemuan</th><th class="text-center">Belum selesai</th><th class="text-center">Kepatuhan</th><th class="text-end">Jarak (km)</th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td><span class="fw-semibold">{{ $r['code'] }}</span><div class="small text-muted">{{ $r['name'] }}</div></td>
                        <td class="text-center">{{ $r['total'] }}</td>
                        <td class="text-center">{{ $r['fit'] }}</td>
                        <td class="text-center">@if ($r['unfit'] > 0)<span class="badge bg-danger-subtle text-danger">{{ $r['unfit'] }}</span>@else - @endif</td>
                        <td class="text-center">{{ $r['findings'] ?: '-' }}</td>
                        <td class="text-center">@if ($r['open'] > 0)<span class="badge bg-warning-subtle text-warning">{{ $r['open'] }}</span>@else - @endif</td>
                        <td class="text-center">{{ $r['compliance'] === null ? '-' : $r['compliance'].'%' }}</td>
                        <td class="text-end">{{ $r['distance'] === null ? '-' : number_format($r['distance'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-5">Tidak ada data untuk bulan ini.</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot>
                    <tr class="fw-semibold">
                        <td>Total</td><td class="text-center">{{ $totals['p2h'] }}</td><td class="text-center">{{ $totals['fit'] }}</td><td class="text-center">{{ $totals['unfit'] }}</td>
                        <td class="text-center">{{ $totals['findings'] }}</td><td class="text-center">{{ $totals['open'] }}</td>
                        <td class="text-center">{{ $totals['compliance'] === null ? '-' : $totals['compliance'].'%' }}</td><td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
    <div class="card-footer bg-white small text-muted">
        Kepatuhan = shift wajib ({{ implode(' dan ', array_map(fn ($s) => \App\Models\Inspection::SHIFTS[$s] ?? $s, config('p2h.required_shifts'))) }}) yang sudah diperiksa dibanding yang seharusnya diperiksa sampai hari ini, untuk unit aktif.
    </div>
</div>
@endsection
