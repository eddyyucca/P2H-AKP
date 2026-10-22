@extends('layouts.app')

@section('title', $sees ? 'Riwayat P2H' : 'P2H Saya')
@section('page-title', $sees ? 'Riwayat P2H' : 'P2H Saya')
@section('breadcrumb')
    <li class="breadcrumb-item active">{{ $sees ? 'Riwayat' : 'P2H Saya' }}</li>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        <form method="GET" action="{{ route('p2h.index') }}" class="row g-2 align-items-end mb-3">
            <div class="col-12 col-md-4 col-xl-3">
                <label for="q" class="form-label small text-muted mb-1">Cari</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ $sees ? 'Unit, plat, pemeriksa, NIK, no. P2H' : 'Unit, plat, no. P2H' }}">
            </div>
            <div class="col-6 col-md-2">
                <label for="from" class="form-label small text-muted mb-1">Dari</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] }}" class="form-control">
            </div>
            <div class="col-6 col-md-2">
                <label for="to" class="form-label small text-muted mb-1">Sampai</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] }}" class="form-control">
            </div>
            <div class="col-6 col-md-2 col-xl-1">
                <label for="status" class="form-label small text-muted mb-1">Hasil</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua</option>
                    <option value="layak" @selected($filters['status'] === 'layak')>Layak</option>
                    <option value="tidak_layak" @selected($filters['status'] === 'tidak_layak')>Tidak layak</option>
                </select>
            </div>
            <div class="col-6 col-md-2 col-xl-1">
                <label for="shift" class="form-label small text-muted mb-1">Shift</label>
                <select id="shift" name="shift" class="form-select">
                    <option value="">Semua</option>
                    @foreach (\App\Models\Inspection::SHIFTS as $k => $l)<option value="{{ $k }}" @selected($filters['shift'] === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label for="follow_up" class="form-label small text-muted mb-1">Tindak lanjut</label>
                <select id="follow_up" name="follow_up" class="form-select">
                    <option value="">Semua</option>
                    <option value="active" @selected($filters['follow_up'] === 'active')>Belum selesai</option>
                    <option value="open" @selected($filters['follow_up'] === 'open')>Menunggu</option>
                    <option value="in_progress" @selected($filters['follow_up'] === 'in_progress')>Dikerjakan</option>
                    <option value="done" @selected($filters['follow_up'] === 'done')>Selesai</option>
                    <option value="none" @selected($filters['follow_up'] === 'none')>Tanpa temuan</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-search me-1"></i>Cari</button>
                <a href="{{ route('p2h.index') }}" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                <a href="{{ route('p2h.export', request()->query()) }}" class="btn btn-outline-success" title="Ekspor ke Excel (CSV) sesuai filter"><i class="bi bi-file-earmark-excel"></i></a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <thead>
                    <tr><th>No. P2H</th><th>Unit</th><th>Waktu</th><th>Pemeriksa</th><th>Hasil</th><th>Temuan</th><th>Tindak lanjut</th></tr>
                </thead>
                <tbody>
                    @forelse ($items as $i)
                        <tr>
                            <td><a href="{{ route('p2h.show', $i) }}" class="fw-semibold text-decoration-none small">{{ $i->number }}</a></td>
                            <td>{{ $i->unit_code }}<div class="small text-muted">{{ $i->unit_plate }}</div></td>
                            <td class="small text-nowrap">{{ $i->inspected_at->translatedFormat('d M Y H:i') }}<div class="text-muted">Shift {{ $i->shiftLabel() }}</div></td>
                            <td class="small">{{ $i->driver_name }}<div class="text-muted">NIK {{ $i->driver_nik ?: '-' }}</div></td>
                            <td><span class="badge {{ $i->isFit() ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">{{ $i->isFit() ? 'LAYAK' : 'TIDAK LAYAK' }}</span></td>
                            <td>{{ $i->bad_count ?: '-' }}</td>
                            <td>
                                @if ($i->follow_up_status === 'open')<span class="badge bg-warning-subtle text-warning">Menunggu</span>
                                @elseif ($i->follow_up_status === 'in_progress')<span class="badge bg-info-subtle text-info">Dikerjakan</span>
                                @elseif ($i->follow_up_status === 'done')<span class="badge bg-success-subtle text-success">Selesai</span>
                                @else <span class="text-muted">-</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-clipboard2 fs-1 d-block mb-2"></i>Tidak ada P2H yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $items->links() }}</div>
    </div>
</div>
@endsection
