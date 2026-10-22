@extends('layouts.app')

@section('title', 'Unit')
@section('page-title', 'Unit')
@section('breadcrumb')
    <li class="breadcrumb-item">Data Master</li>
    <li class="breadcrumb-item active">Unit</li>
@endsection

@push('topbar-actions')
    @if ($canEdit)
        <a href="{{ route('p2h.units.import') }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2"><i class="bi bi-upload"></i><span class="d-none d-sm-inline">Impor CSV</span></a>
        <a href="{{ route('p2h.units.create') }}" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2"><i class="bi bi-plus-lg"></i><span class="d-none d-sm-inline">Tambah unit</span></a>
    @endif
@endpush

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        <form method="GET" action="{{ route('p2h.units') }}" class="row g-2 mb-3">
            <div class="col-12 col-md-6 col-lg-4"><input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Cari kode, nama, atau plat"></div>
            <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-search me-1"></i>Cari</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <thead><tr><th>Kode</th><th>Unit</th><th>Kategori</th><th>Odometer / servis</th><th>Status</th>@if ($canEdit)<th class="text-end">Aksi</th>@endif</tr></thead>
                <tbody>
                    @forelse ($units as $u)
                        <tr class="{{ $u->is_active ? '' : 'text-muted' }}">
                            <td class="fw-semibold">{{ $u->code }}</td>
                            <td>{{ $u->name }}<div class="small text-muted">{{ $u->plate }}</div></td>
                            <td>{{ $u->category }}</td>
                            @php($odo = $odometers[$u->id] ?? null)
                            @php($left = \App\Models\Unit::serviceRemaining($u->service_interval_km, $u->last_service_km, $odo))
                            <td class="small">
                                {{ $odo !== null ? number_format($odo, 0, ',', '.').' km' : '-' }}
                                @if ($left !== null)<div class="{{ $left <= 0 ? 'text-danger fw-semibold' : ($left <= config('p2h.service_warning_km') ? 'text-warning' : 'text-muted') }}">{{ $left <= 0 ? 'Servis lewat '.number_format(-$left, 0, ',', '.').' km' : 'Servis '.number_format($left, 0, ',', '.').' km lagi' }}</div>@endif
                            </td>
                            <td><span class="badge {{ $u->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            @if ($canEdit)
                                <td class="text-end text-nowrap">
                                    @if ($u->service_interval_km && $odo !== null)
                                        <form method="POST" action="{{ route('p2h.units.serviced', $u) }}" class="d-inline">@csrf
                                            <button class="btn btn-outline-primary btn-sm" title="Catat servis dilakukan pada odometer terakhir"><i class="bi bi-wrench"></i></button>
                                        </form>
                                    @endif
                                    <a href="{{ route('p2h.units.edit', $u) }}" class="btn btn-outline-secondary btn-sm" title="Ubah"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" action="{{ route('p2h.units.toggle', $u) }}" class="d-inline">@csrf
                                        <button class="btn btn-outline-{{ $u->is_active ? 'warning' : 'success' }} btn-sm" title="{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"><i class="bi {{ $u->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i></button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-truck-front fs-1 d-block mb-2"></i>Belum ada unit.@if ($canEdit) <a href="{{ route('p2h.units.create') }}">Tambah unit pertama</a>.@endif</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $units->links() }}</div>
    </div>
</div>
@endsection
