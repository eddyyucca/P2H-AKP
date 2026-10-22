@extends('layouts.app')

@section('title', 'Antrian Perbaikan')
@section('page-title', 'Antrian Perbaikan')
@section('breadcrumb')
    <li class="breadcrumb-item">P2H</li>
    <li class="breadcrumb-item active">Antrian Perbaikan</li>
@endsection

@section('content')
@php
    $tabs = ['' => 'Semua', 'kritis' => 'Tidak layak', 'menunggu' => 'Menunggu', 'dikerjakan' => 'Dikerjakan', 'terlambat' => 'Terlambat'];
@endphp
<div class="card shadow-sm">
    <div class="card-body">
        <ul class="nav nav-pills gap-1 mb-3">
            @foreach ($tabs as $key => $label)
                <li class="nav-item">
                    <a class="nav-link py-1 {{ $filter === $key ? 'active' : '' }}" href="{{ route('p2h.repairs', $key === '' ? [] : ['filter' => $key]) }}">
                        {{ $label }} <span class="badge {{ $filter === $key ? 'bg-light text-dark' : 'bg-secondary-subtle text-secondary' }}">{{ $counts[$key] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <thead><tr><th>Unit</th><th>Temuan</th><th>Umur</th><th>Status</th><th>PIC / tenggat</th><th>Hasil</th></tr></thead>
                <tbody>
                    @forelse ($items as $i)
                        @php($age = (int) floor($i->inspected_at->diffInDays(now())))
                        <tr class="{{ $i->isFit() ? '' : 'table-danger' }}">
                            <td>
                                <a href="{{ route('p2h.show', $i) }}" class="fw-semibold text-decoration-none">{{ $i->unit_code }}</a>
                                <div class="small text-muted">{{ $i->number }}</div>
                            </td>
                            <td>{{ $i->bad_count }} item <span class="small text-muted">({{ $i->critical_bad_count }} kritis)</span><div class="small text-muted">{{ $i->driver_name }}</div></td>
                            <td class="small text-nowrap">{{ $i->inspected_at->translatedFormat('d M H:i') }}<div class="{{ $age > 3 ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $age < 1 ? 'hari ini' : $age.' hari lalu' }}</div></td>
                            <td>
                                @if ($i->follow_up_status === 'in_progress')<span class="badge bg-info-subtle text-info">Dikerjakan</span>@else<span class="badge bg-warning-subtle text-warning">Menunggu</span>@endif
                            </td>
                            <td class="small">
                                {{ $i->follow_up_pic ?: '-' }}
                                @if ($i->follow_up_due)<div class="{{ $i->isOverdue() ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $i->follow_up_due->translatedFormat('d M Y') }}@if ($i->isOverdue()) (terlambat)@endif</div>@endif
                            </td>
                            <td><span class="badge {{ $i->isFit() ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">{{ $i->isFit() ? 'LAYAK' : 'TIDAK LAYAK' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-emoji-smile fs-1 d-block mb-2"></i>Tidak ada temuan yang menunggu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $items->links() }}</div>
    </div>
</div>
@endsection
