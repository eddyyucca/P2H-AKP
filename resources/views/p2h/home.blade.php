@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard P2H')
@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@push('topbar-actions')
    @if ($canFill)
        <a href="{{ route('p2h.fill') }}" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2">
            <i class="bi bi-clipboard2-check"></i><span class="d-none d-sm-inline">Isi P2H</span>
        </a>
    @endif
@endpush

@push('styles')
<style>
    .hero { background: linear-gradient(135deg, #0e2a47 0%, #1b4f86 100%); color: #fff; border-radius: 18px; }
    .badge-fit { background: #dcfce7; color: #166534; } .badge-unfit { background: #fee2e2; color: #991b1b; }
    .chart-box { position: relative; height: 280px; }
    .chart-box.sm { height: 240px; }
    .hm { display: inline-block; width: 16px; height: 16px; border-radius: 4px; vertical-align: middle; }
    .hm-ok { background: #16a34a; } .hm-bad { background: #dc2626; } .hm-none { background: #e5eaf1; }
    .rate-ring { --p: 0; width: 84px; height: 84px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; font-size: 1.15rem;
        background: conic-gradient(var(--c, #16a34a) calc(var(--p) * 1%), #e5eaf1 0); position: relative; }
    .rate-ring::after { content: ''; position: absolute; inset: 9px; background: #fff; border-radius: 50%; }
    .rate-ring span { position: relative; z-index: 1; }
</style>
@endpush

@section('content')
@php
    $compliance = $stats['compliance'];
    $ringColor = $compliance === null ? '#94a3b8' : ($compliance >= 90 ? '#16a34a' : ($compliance >= 60 ? '#f59e0b' : '#dc2626'));
@endphp

{{-- Sapaan --}}
<div class="hero p-4 mb-4 shadow-sm">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="small opacity-75">{{ now()->translatedFormat('l, d F Y') }} &middot; Shift {{ \App\Models\Inspection::SHIFTS[$shift] }}</div>
            <h2 class="h4 fw-bold mb-1">Halo, {{ $portalUser->name }}</h2>
            <div class="opacity-75 small">
                {{ $canFill ? 'Pilih unit lalu isi P2H sebelum unit dipakai.' : 'Pantau hasil P2H seluruh unit dan temuan yang menunggu tindak lanjut.' }}
            </div>
        </div>
        @if ($canFill)
            <a href="{{ route('p2h.fill') }}" class="btn btn-light btn-lg fw-semibold"><i class="bi bi-clipboard2-check me-2"></i>Mulai P2H</a>
        @endif
    </div>
</div>

{{-- Pemberitahuan --}}
@if (! empty($alerts))
    <div class="d-flex flex-column gap-2 mb-4">
        @foreach ($alerts as $a)
            <a href="{{ $a['href'] }}" class="alert alert-{{ $a['level'] }} d-flex align-items-center gap-2 mb-0 py-2 text-decoration-none">
                <i class="bi {{ $a['icon'] }}"></i><span>{{ $a['text'] }}</span>
            </a>
        @endforeach
    </div>
@endif

{{-- Angka hari ini --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3 col-xl-2"><div class="erp-card h-100"><div class="erp-card-body d-flex align-items-center gap-3">
        <div class="kpi-icon bg-primary-subtle text-primary"><i class="bi bi-clipboard-data"></i></div>
        <div><div class="kpi-label">{{ $sees ? 'P2H hari ini' : 'P2H saya hari ini' }}</div><div class="kpi-value fs-5 fw-bold">{{ number_format($stats['today']) }}</div>
            <div class="small text-muted">Siang {{ $stats['day'] }} &middot; Malam {{ $stats['night'] }}</div></div>
    </div></div></div>
    <div class="col-6 col-lg-3 col-xl-2"><div class="erp-card h-100"><div class="erp-card-body d-flex align-items-center gap-3">
        <div class="kpi-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></div>
        <div><div class="kpi-label">Layak</div><div class="kpi-value fs-5 fw-bold">{{ number_format($stats['fit']) }}</div></div>
    </div></div></div>
    <div class="col-6 col-lg-3 col-xl-2"><div class="erp-card h-100"><div class="erp-card-body d-flex align-items-center gap-3">
        <div class="kpi-icon bg-danger-subtle text-danger"><i class="bi bi-x-octagon"></i></div>
        <div><div class="kpi-label">Tidak layak</div><div class="kpi-value fs-5 fw-bold">{{ number_format($stats['unfit']) }}</div></div>
    </div></div></div>
    <div class="col-6 col-lg-3 col-xl-2"><div class="erp-card h-100"><div class="erp-card-body d-flex align-items-center gap-3">
        <div class="kpi-icon bg-warning-subtle text-warning"><i class="bi bi-tools"></i></div>
        <div><div class="kpi-label">Temuan menunggu</div><div class="kpi-value fs-5 fw-bold">{{ number_format($stats['open']) }}</div></div>
    </div></div></div>
    @if ($sees)
        <div class="col-12 col-xl-4"><div class="erp-card h-100"><div class="erp-card-body d-flex align-items-center gap-3">
            <div class="rate-ring" style="--p: {{ $compliance ?? 0 }}; --c: {{ $ringColor }}"><span>{{ $compliance === null ? '-' : $compliance.'%' }}</span></div>
            <div>
                <div class="kpi-label">Kepatuhan P2H hari ini</div>
                <div class="fw-bold">{{ $stats['units_done'] }} dari {{ $stats['units_active'] }} unit aktif sudah diperiksa</div>
                <div class="small text-muted">{{ max($stats['units_active'] - $stats['units_done'], 0) }} unit belum P2H</div>
                <div class="small mt-1 d-flex flex-wrap gap-2">
                    @foreach ($shiftCompliance as $sc)
                        <span class="badge bg-body-secondary text-body-emphasis border" title="Unit aktif yang sudah P2H pada shift {{ $sc['label'] }} hari ini">{{ $sc['label'] }} {{ $sc['done'] }}/{{ $sc['total'] }}@if ($sc['percent'] !== null) &middot; {{ $sc['percent'] }}%@endif</span>
                    @endforeach
                </div>
            </div>
        </div></div></div>
    @else
        <div class="col-12 col-xl-4"><div class="erp-card h-100"><div class="erp-card-body d-flex align-items-center gap-3">
            <div class="kpi-icon bg-info-subtle text-info"><i class="bi bi-calendar-range"></i></div>
            <div><div class="kpi-label">P2H saya ({{ $days }} hari)</div><div class="kpi-value fs-5 fw-bold">{{ number_format($periodStats['total']) }}</div>
                <div class="small text-muted">{{ $periodStats['fit_rate'] === null ? 'Belum ada' : $periodStats['fit_rate'].'% layak' }}</div></div>
        </div></div></div>
    @endif
</div>

{{-- Grafik --}}
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <h3 class="h5 mb-0">Ringkasan {{ $custom ? $from->translatedFormat('d M').' - '.$to->translatedFormat('d M Y') : $days.' hari terakhir' }}</h3>
        @if ($category !== '')<div class="small text-muted">Kategori unit: {{ $category }}</div>@endif
    </div>
    <form method="GET" action="{{ route('p2h.home') }}" class="d-flex flex-wrap align-items-end gap-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="Periode">
            @foreach ([7, 14, 30] as $d)
                <a href="{{ route('p2h.home', array_filter(['hari' => $d, 'kategori' => $category])) }}" class="btn {{ ! $custom && $days === $d ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $d }} hari</a>
            @endforeach
        </div>
        <input type="date" name="dari" value="{{ $custom ? $from->toDateString() : '' }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm w-auto" aria-label="Dari tanggal">
        <input type="date" name="sampai" value="{{ $custom ? $to->toDateString() : '' }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm w-auto" aria-label="Sampai tanggal">
        @if ($categories->count() > 1)
            <select name="kategori" class="form-select form-select-sm w-auto" aria-label="Kategori unit">
                <option value="">Semua kategori</option>
                @foreach ($categories as $c)<option value="{{ $c }}" @selected($category === $c)>{{ $c }}</option>@endforeach
            </select>
        @endif
        <button class="btn btn-sm btn-outline-primary">Terapkan</button>
    </form>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-{{ $sees ? 7 : 12 }}">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="h6 mb-0"><i class="bi bi-bar-chart me-2"></i>P2H per hari</h2>
                <div class="small text-muted">
                    {{ number_format($periodStats['total']) }} P2H &middot;
                    {{ $periodStats['fit_rate'] === null ? 'belum ada data' : $periodStats['fit_rate'].'% layak' }} &middot;
                    {{ $periodStats['findings'] }} dengan temuan
                </div>
            </div>
            <div class="card-body">
                @if ($periodStats['total'] === 0)
                    <div class="text-center text-muted py-5"><i class="bi bi-bar-chart fs-1 d-block mb-2"></i>Belum ada P2H pada periode ini.</div>
                @else
                    <div class="chart-box"><canvas id="chartDaily" aria-label="Grafik P2H per hari" role="img"></canvas></div>
                @endif
            </div>
        </div>
    </div>

    @if ($sees)
        <div class="col-12 col-md-6 col-xl-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-truck-front me-2"></i>Status unit hari ini</h2></div>
                <div class="card-body">
                    @if ($stats['units_active'] === 0)
                        <div class="text-center text-muted py-5"><i class="bi bi-truck-front fs-1 d-block mb-2"></i>Belum ada unit aktif.<br>Tambahkan di menu <strong>Unit</strong>.</div>
                    @else
                        <div class="chart-box sm"><canvas id="chartUnits" aria-label="Status unit hari ini" role="img"></canvas></div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="col-12 col-md-{{ $sees ? 6 : 12 }} col-xl-{{ $sees ? 7 : 12 }}">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0"><i class="bi bi-wrench-adjustable me-2"></i>Item paling sering rusak</h2>
                <span class="small text-muted"><span class="badge bg-danger-subtle text-danger">Kritis</span> berwarna merah</span>
            </div>
            <div class="card-body">
                @if (empty($chart['items']))
                    <div class="text-center text-muted py-5"><i class="bi bi-emoji-smile fs-1 d-block mb-2"></i>Tidak ada item rusak pada periode ini.</div>
                @else
                    <div class="chart-box sm"><canvas id="chartItems" aria-label="Item paling sering rusak" role="img"></canvas></div>
                @endif
            </div>
        </div>
    </div>

    @if ($sees)
        <div class="col-12 col-xl-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-exclamation-diamond me-2 text-danger"></i>Unit dengan temuan terbanyak</h2></div>
                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-0">
                        <thead><tr><th>Unit</th><th class="text-center">P2H bertemuan</th><th class="text-center">Tidak layak</th></tr></thead>
                        <tbody>
                            @forelse ($topUnits as $u)
                                <tr>
                                    <td class="fw-semibold"><a href="{{ route('p2h.index', ['q' => $u->unit_code]) }}" class="text-decoration-none">{{ $u->unit_code }}</a></td>
                                    <td class="text-center">{{ $u->n }}</td>
                                    <td class="text-center">@if ($u->unfit > 0)<span class="badge badge-unfit">{{ $u->unfit }}</span>@else<span class="text-muted">-</span>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">Tidak ada temuan pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Kinerja tindak lanjut --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="erp-card h-100"><div class="erp-card-body">
        <div class="kpi-label">Temuan terbuka menurut umur</div>
        <div class="d-flex gap-3 mt-1">
            <div><div class="fw-bold fs-5">{{ $findingsStats['aging']['lt1'] }}</div><div class="small text-muted">&lt; 1 hari</div></div>
            <div><div class="fw-bold fs-5 text-warning">{{ $findingsStats['aging']['d1_3'] }}</div><div class="small text-muted">1-3 hari</div></div>
            <div><div class="fw-bold fs-5 text-danger">{{ $findingsStats['aging']['gt3'] }}</div><div class="small text-muted">&gt; 3 hari</div></div>
        </div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="erp-card h-100"><div class="erp-card-body">
        <div class="kpi-label">Sedang dikerjakan</div>
        <div class="fw-bold fs-5">{{ $findingsStats['in_progress'] }}</div>
        <div class="small {{ $findingsStats['overdue'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $findingsStats['overdue'] }} lewat tenggat</div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="erp-card h-100"><div class="erp-card-body">
        <div class="kpi-label">Rata-rata waktu selesai</div>
        <div class="fw-bold fs-5">
            @if ($findingsStats['mttr_hours'] === null) - @elseif ($findingsStats['mttr_hours'] >= 48) {{ round($findingsStats['mttr_hours'] / 24, 1) }} hari @else {{ $findingsStats['mttr_hours'] }} jam @endif
        </div>
        <div class="small text-muted">{{ $findingsStats['done'] }} temuan selesai pada periode</div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="erp-card h-100"><div class="erp-card-body">
        <div class="kpi-label">Antrian perbaikan</div>
        @if ($sees)<a href="{{ route('p2h.repairs') }}" class="fw-bold fs-5 text-decoration-none">{{ $stats['open'] }} <span class="small fw-normal">temuan &rarr;</span></a>
        @else <div class="fw-bold fs-5">{{ $stats['open'] }}</div>@endif
    </div></div></div>
</div>

@if ($sees)
<div class="row g-4 mb-4">
    <div class="col-12 col-xl-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-graph-up me-2"></i>Tren kepatuhan harian</h2></div>
            <div class="card-body">
                @if ($stats['units_active'] === 0)
                    <div class="text-center text-muted py-5">Belum ada unit aktif.</div>
                @else
                    <div class="chart-box sm"><canvas id="chartCompliance" aria-label="Tren kepatuhan harian" role="img"></canvas></div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0"><i class="bi bi-x-octagon me-2 text-danger"></i>Unit tidak layak saat ini</h2>
                <span class="badge bg-danger-subtle text-danger">{{ $fleet['unfit']->count() }}</span>
            </div>
            <div class="list-group list-group-flush">
                @forelse ($fleet['unfit'] as $r)
                    <a href="{{ route('p2h.show', $r->last) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><span class="fw-semibold">{{ $r->unit->code }}</span><span class="small text-muted d-block">{{ $r->last->critical_bad_count }} item kritis rusak</span></span>
                        <span class="small text-danger text-nowrap">{{ $r->last->inspected_at->diffForHumans(null, true) }}</span>
                    </a>
                @empty
                    <div class="text-center text-muted py-4 small"><i class="bi bi-check2-circle fs-3 d-block text-success"></i>Semua unit layak menurut P2H terakhir.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-person-check me-2"></i>Pemeriksa teraktif</h2></div>
            <table class="table table-modern align-middle mb-0">
                <tbody>
                    @forelse ($topDrivers as $d)
                        <tr><td class="small">{{ $d->driver_name }}</td><td class="text-end fw-semibold">{{ $d->n }}</td></tr>
                    @empty
                        <tr><td class="text-center text-muted py-4 small">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h6 mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Peta P2H unit x tanggal</h2>
        <span class="small text-muted">
            <span class="hm hm-ok"></span> layak &nbsp;<span class="hm hm-bad"></span> ada tidak layak &nbsp;<span class="hm hm-none"></span> belum P2H &middot; {{ count($heatmapDays) }} hari terakhir
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 text-center">
            <thead><tr><th class="text-start">Unit</th>@foreach ($heatmapDays as $d)<th class="small fw-normal text-muted">{{ $d->format('d') }}</th>@endforeach</tr></thead>
            <tbody>
                @forelse ($heatmap['units'] as $u)
                    <tr>
                        <td class="text-start fw-semibold small">{{ $u->code }}</td>
                        @foreach ($heatmapDays as $d)
                            @php($c = $heatmap['cells'][$u->id][$d->toDateString()] ?? 'none')
                            <td><span class="hm hm-{{ $c }}" title="{{ $u->code }} &middot; {{ $d->translatedFormat('d M') }}: {{ ['ok' => 'layak', 'bad' => 'ada tidak layak', 'none' => 'belum P2H'][$c] }}"></span></td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td class="text-center text-muted py-4">Belum ada unit aktif.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Yang perlu ditindaklanjuti --}}
<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0"><i class="bi bi-tools me-2 text-warning"></i>Temuan menunggu tindak lanjut</h2>
                <a href="{{ $sees ? route('p2h.repairs') : route('p2h.index', ['follow_up' => 'active']) }}" class="small text-decoration-none">Lihat semua</a>
            </div>
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead><tr><th>Unit</th><th>Waktu</th><th>Temuan</th><th>Status</th><th>Hasil</th></tr></thead>
                    <tbody>
                        @forelse ($followUps as $i)
                            <tr>
                                <td><a href="{{ route('p2h.show', $i) }}" class="fw-semibold text-decoration-none">{{ $i->unit_code }}</a><div class="small text-muted">{{ $i->driver_name }}</div></td>
                                <td class="small text-nowrap">{{ $i->inspected_at->translatedFormat('d M H:i') }}</td>
                                <td>{{ $i->bad_count }} item</td>
                                <td class="small">@if ($i->follow_up_status === 'in_progress')<span class="badge bg-info-subtle text-info">Dikerjakan</span>@else<span class="badge bg-warning-subtle text-warning">Menunggu</span>@endif
                                    @if ($i->isOverdue())<span class="badge bg-danger">Terlambat</span>@endif</td>
                                <td><span class="badge {{ $i->isFit() ? 'badge-fit' : 'badge-unfit' }}">{{ $i->isFit() ? 'LAYAK' : 'TIDAK LAYAK' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-emoji-smile fs-3 d-block mb-1"></i>Tidak ada temuan yang menunggu.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0"><i class="bi bi-clock-history me-2"></i>{{ $sees ? 'P2H terbaru' : 'P2H terakhir saya' }}</h2>
                <a href="{{ route('p2h.index') }}" class="small text-decoration-none">Riwayat lengkap</a>
            </div>
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead><tr><th>No. P2H</th><th>Unit</th><th>Pemeriksa</th><th>Hasil</th></tr></thead>
                    <tbody>
                        @forelse ($recent as $i)
                            <tr>
                                <td><a href="{{ route('p2h.show', $i) }}" class="text-decoration-none small">{{ $i->number }}</a></td>
                                <td>{{ $i->unit_code }}</td>
                                <td class="small">{{ $i->driver_name }}</td>
                                <td><span class="badge {{ $i->isFit() ? 'badge-fit' : 'badge-unfit' }}">{{ $i->isFit() ? 'LAYAK' : 'TIDAK LAYAK' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada P2H.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        @if ($sees)
            <div class="card shadow-sm" id="pendingUnits">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0"><i class="bi bi-hourglass-split me-2 text-danger"></i>Unit belum P2H hari ini</h2>
                    <span class="badge bg-secondary-subtle text-secondary">{{ $pending->count() }} dari {{ $stats['units_active'] }}</span>
                </div>
                <div class="card-body">
                    @forelse ($pending as $unit)
                        @if ($canFill)
                            <a href="{{ route('p2h.fill', ['unit' => $unit->code]) }}" class="d-inline-flex align-items-center gap-1 border rounded px-2 py-1 me-1 mb-2 text-decoration-none small" title="{{ $unit->name }}">
                                <i class="bi bi-truck-front"></i>{{ $unit->code }}
                            </a>
                        @else
                            <span class="d-inline-flex align-items-center gap-1 border rounded px-2 py-1 me-1 mb-2 small" title="{{ $unit->name }}"><i class="bi bi-truck-front"></i>{{ $unit->code }}</span>
                        @endif
                    @empty
                        <div class="text-muted small text-center py-3">
                            @if ($stats['units_active'] === 0) Belum ada unit aktif. Tambahkan di menu <strong>Unit</strong>.
                            @else <i class="bi bi-check2-all fs-3 d-block text-success"></i>Semua unit aktif sudah diperiksa hari ini.
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>
        @else
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6"><i class="bi bi-info-circle me-2"></i>Cara mengisi P2H</h2>
                    <ol class="small text-muted ps-3 mb-0">
                        <li>Klik <strong>Mulai P2H</strong>, lalu pilih unit yang Anda pakai.</li>
                        <li>Isi odometer dan level BBM.</li>
                        <li>Periksa tiap item: pilih <strong>Baik</strong> atau <strong>Rusak</strong> (rusak wajib diberi keterangan).</li>
                        <li>Kirim. Hasil <strong>LAYAK</strong> atau <strong>TIDAK LAYAK</strong> langsung tampil dan tersimpan.</li>
                    </ol>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/chart.umd.min.js') }}"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;   // CDN tidak terjangkau: tabel tetap tampil
    // Tunggu font halaman selesai dimuat supaya lebar label sumbu dihitung dengan font yang sama dengan yang digambar.
    (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve()).then(draw);

    function draw() {
    const data = @json($chart);
    const grid = 'rgba(100, 116, 139, .15)';
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.color = '#475569';

    const daily = document.getElementById('chartDaily');
    if (daily) {
        new Chart(daily, {
            type: 'bar',
            data: { labels: data.labels, datasets: [
                { label: 'Layak', data: data.fit, backgroundColor: '#16a34a', borderRadius: 4, stack: 'p2h' },
                { label: 'Tidak layak', data: data.unfit, backgroundColor: '#dc2626', borderRadius: 4, stack: 'p2h' },
            ] },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false } },
                scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: grid } } },
            },
        });
    }

    const trend = document.getElementById('chartCompliance');
    if (trend && data.compliance) {
        new Chart(trend, {
            type: 'line',
            data: { labels: data.labels, datasets: [{ label: 'Unit diperiksa (%)', data: data.compliance, borderColor: '#1b4f86', backgroundColor: 'rgba(27, 79, 134, .12)', fill: true, tension: .25, pointRadius: 3 }] },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => c.parsed.y + '% unit aktif diperiksa' } } },
                scales: { x: { grid: { display: false } }, y: { min: 0, max: 100, ticks: { callback: v => v + '%' }, grid: { color: grid } } },
            },
        });
    }

    const units = document.getElementById('chartUnits');
    if (units && data.unitStatus) {
        const s = data.unitStatus;
        new Chart(units, {
            type: 'doughnut',
            data: { labels: ['Layak', 'Tidak layak', 'Belum P2H'], datasets: [{ data: [s.layak, s.tidak_layak, s.belum], backgroundColor: ['#16a34a', '#dc2626', '#cbd5e1'], borderWidth: 2 }] },
            options: { maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom' } } },
        });
    }

    const items = document.getElementById('chartItems');
    if (items && data.items.length) {
        new Chart(items, {
            type: 'bar',
            data: { labels: data.items.map(i => i.label), datasets: [{
                label: 'Kali rusak', data: data.items.map(i => i.n),
                backgroundColor: data.items.map(i => i.critical ? '#dc2626' : '#f59e0b'), borderRadius: 4,
            }] },
            options: {
                indexAxis: 'y', maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: grid } },
                    // Nama item panjang dipendekkan di sumbu; nama lengkap tetap muncul di tooltip.
                    y: { grid: { display: false }, ticks: { callback(v) { const l = this.getLabelForValue(v); return l.length > 24 ? l.slice(0, 23) + '…' : l; } } },
                },
            },
        });
    }
    }
})();
</script>
@endpush
