@extends('layouts.app')

@section('title', $inspection->number)
@section('page-title', 'Hasil P2H')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('p2h.index') }}">P2H</a></li>
    <li class="breadcrumb-item active">{{ $inspection->number }}</li>
@endsection

@push('styles')
<style>
    .result { border-radius: 18px; padding: 1.5rem; text-align: center; }
    .result.fit { background: #dcfce7; color: #166534; } .result.unfit { background: #fee2e2; color: #991b1b; }
    .result .big { font-size: 2.2rem; font-weight: 800; letter-spacing: .02em; }
    .ans-bad { background: #fff5f5; }
</style>
@endpush

@section('content')
@php($fit = $inspection->isFit())
@if (session('just_submitted'))
    <div class="result {{ $fit ? 'fit' : 'unfit' }} mb-4">
        <i class="bi {{ $fit ? 'bi-check-circle-fill' : 'bi-x-octagon-fill' }}" style="font-size:3rem"></i>
        <div class="big">{{ $fit ? 'LAYAK' : 'TIDAK LAYAK' }}</div>
        <div class="mx-auto" style="max-width:520px">
            @if ($fit)
                {{ $inspection->unit_code }} boleh digunakan {{ $inspection->shift === 'D' ? 'hari ini' : 'malam ini' }}.
                @if ($inspection->bad_count > 0) Ada {{ $inspection->bad_count }} temuan non-kritis yang menunggu tindak lanjut pengawas. @endif
            @else
                {{ $inspection->unit_code }} ditandai <strong>tidak boleh digunakan</strong>. Pengawas akan menindaklanjuti {{ $inspection->bad_count }} temuan.
            @endif
        </div>
        <div class="mt-3 d-flex flex-wrap justify-content-center gap-2">
            <a href="{{ route('p2h.fill') }}" class="btn btn-dark btn-sm">Isi P2H unit lain</a>
            <a href="{{ route('p2h.home') }}" class="btn btn-outline-dark btn-sm">Kembali ke dashboard</a>
            <a href="{{ route('p2h.print', $inspection) }}" class="btn btn-outline-dark btn-sm" target="_blank"><i class="bi bi-printer me-1"></i>Cetak lembar</a>
        </div>
    </div>
@else
    <div class="d-flex align-items-center gap-3 mb-4">
        <span class="badge fs-6 {{ $fit ? 'bg-success' : 'bg-danger' }}">{{ $fit ? 'LAYAK' : 'TIDAK LAYAK' }}</span>
        <span class="text-muted">{{ $inspection->number }}</span>
        <a href="{{ route('p2h.print', $inspection) }}" class="btn btn-outline-secondary btn-sm ms-auto" target="_blank"><i class="bi bi-printer me-1"></i>Cetak</a>
    </div>
@endif

<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white"><h2 class="h6 mb-0">Ringkasan</h2></div>
            <table class="table table-sm mb-0 small">
                <tbody>
                    <tr><th class="text-muted fw-normal">No. P2H</th><td class="fw-semibold">{{ $inspection->number }}</td></tr>
                    <tr><th class="text-muted fw-normal">Unit</th><td>{{ $inspection->unit_code }} &middot; {{ $inspection->unit_name }}@if ($inspection->unit_plate) ({{ $inspection->unit_plate }})@endif</td></tr>
                    <tr><th class="text-muted fw-normal">Waktu</th><td>{{ $inspection->inspected_at->translatedFormat('d M Y H:i') }} &middot; Shift {{ $inspection->shiftLabel() }}</td></tr>
                    <tr><th class="text-muted fw-normal">Pemeriksa</th><td>{{ $inspection->driver_name }}<br><span class="text-muted">NIK {{ $inspection->driver_nik ?: '-' }}</span></td></tr>
                    <tr><th class="text-muted fw-normal">Odometer</th><td>{{ $inspection->odometer !== null ? number_format($inspection->odometer, 0, ',', '.').' km' : '-' }}</td></tr>
                    <tr><th class="text-muted fw-normal">Level BBM</th><td>{{ $inspection->fuel_level ?: '-' }}</td></tr>
                    <tr><th class="text-muted fw-normal">Temuan rusak</th><td>{{ $inspection->bad_count === 0 ? 'Tidak ada' : $inspection->bad_count.' item ('.$inspection->critical_bad_count.' kritis)' }}</td></tr>
                    <tr><th class="text-muted fw-normal">Checklist</th><td>{{ $inspection->checklist_version_name }}</td></tr>
                    @if ($inspection->declared_at)<tr><th class="text-muted fw-normal">Pernyataan</th><td>Pemeriksa menyatakan hasil benar<br><span class="text-muted">{{ $inspection->declared_at->translatedFormat('d M Y H:i') }}</span></td></tr>@endif
                    @if ($inspection->notes)<tr><th class="text-muted fw-normal">Catatan</th><td style="white-space:pre-line">{{ $inspection->notes }}</td></tr>@endif
                </tbody>
            </table>
        </div>

        @if ($inspection->follow_up_status !== 'none')
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">Tindak lanjut</h2>
                    @php($badge = ['done' => 'bg-success-subtle text-success', 'in_progress' => 'bg-info-subtle text-info', 'open' => 'bg-warning-subtle text-warning'][$inspection->follow_up_status])
                    <span class="badge {{ $badge }}">{{ \App\Http\Controllers\RepairController::STATUS_LABELS[$inspection->follow_up_status] }}</span>
                </div>
                <div class="card-body small">
                    @if ($inspection->isOpen() && $inspection->follow_up_pic)
                        <div class="mb-2">PIC / bengkel: <strong>{{ $inspection->follow_up_pic }}</strong>
                            @if ($inspection->follow_up_due)<br>Tenggat: <span class="{{ $inspection->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $inspection->follow_up_due->translatedFormat('d M Y') }}@if ($inspection->isOverdue()) (terlambat)@endif</span>@endif
                        </div>
                    @endif

                    @if ($inspection->follow_up_status === 'done')
                        <div>{{ $inspection->follow_up_note }}</div>
                        <div class="text-muted mt-2">oleh {{ $inspection->follow_up_by_name }} &middot; {{ $inspection->follow_up_at->translatedFormat('d M Y H:i') }}</div>
                        @if ($inspection->resolved_by_number)<div class="mt-1"><a href="{{ route('p2h.show', $inspection->resolved_by_number) }}">Ditutup oleh {{ $inspection->resolved_by_number }}</a></div>@endif
                    @elseif ($canFollowUp)
                        @if ($inspection->follow_up_status === 'open')
                            <form method="POST" action="{{ route('p2h.followup.start', $inspection) }}" class="mb-3 pb-3 border-bottom">
                                @csrf
                                <div class="fw-semibold mb-1">Mulai dikerjakan</div>
                                <input type="text" name="pic" value="{{ old('pic') }}" maxlength="100" class="form-control form-control-sm mb-2 @error('pic') is-invalid @enderror" placeholder="PIC / bengkel (wajib)">
                                @error('pic')<div class="invalid-feedback d-block mb-1">{{ $message }}</div>@enderror
                                <input type="date" name="due_date" value="{{ old('due_date') }}" min="{{ today()->toDateString() }}" class="form-control form-control-sm mb-2 @error('due_date') is-invalid @enderror" aria-label="Tenggat">
                                @error('due_date')<div class="invalid-feedback d-block mb-1">{{ $message }}</div>@enderror
                                <input type="text" name="note" value="{{ old('note') }}" maxlength="500" class="form-control form-control-sm mb-2" placeholder="Catatan (opsional)">
                                <button type="submit" class="btn btn-info btn-sm"><i class="bi bi-wrench me-1"></i>Tandai sedang dikerjakan</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('p2h.followup', $inspection) }}">
                            @csrf
                            <label for="follow_up_note" class="form-label">Catatan penanganan <span class="text-danger">*</span></label>
                            <textarea id="follow_up_note" name="follow_up_note" rows="3" maxlength="500" class="form-control @error('follow_up_note') is-invalid @enderror" placeholder="Mis. ban sudah diganti di workshop, lampu rotary dipasang">{{ old('follow_up_note') }}</textarea>
                            @error('follow_up_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <button type="submit" class="btn btn-success btn-sm mt-2"><i class="bi bi-check2-circle me-1"></i>Tandai sudah ditangani</button>
                        </form>
                    @else
                        <span class="text-muted">Menunggu penanganan pengawas.</span>
                    @endif

                    @if ($timeline->isNotEmpty())
                        <div class="fw-semibold mt-3 mb-1">Riwayat</div>
                        <ul class="list-unstyled mb-0 border-start ps-3">
                            @foreach ($timeline as $t)
                                <li class="mb-2">
                                    <span class="badge bg-secondary-subtle text-secondary">{{ \App\Http\Controllers\RepairController::STATUS_LABELS[$t->status] ?? $t->status }}</span>
                                    <span class="text-muted">{{ $t->created_at->translatedFormat('d M H:i') }} &middot; {{ $t->by_name }}</span>
                                    @if ($t->pic)<div>PIC: {{ $t->pic }}@if ($t->due_date), tenggat {{ $t->due_date->translatedFormat('d M Y') }}@endif</div>@endif
                                    @if ($t->note)<div>{{ $t->note }}</div>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="col-12 col-xl-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><h2 class="h6 mb-0">Hasil checklist</h2></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        @foreach ($answers as $group => $rows)
                            <tr class="table-light"><th colspan="2" class="small text-muted">{{ $group }}</th></tr>
                            @foreach ($rows as $a)
                                <tr class="{{ $a->isBad() ? 'ans-bad' : '' }}">
                                    <td>
                                        {{ $a->label }}
                                        @if ($a->is_critical)<span class="badge bg-danger-subtle text-danger ms-1">Kritis</span>@endif
                                        @if ($a->isBad() && $a->note)<div class="small text-danger mt-1"><i class="bi bi-exclamation-circle me-1"></i>{{ $a->note }}</div>@endif
                                        @if ($a->photo_path)<a href="{{ route('p2h.photo', $a->id) }}" target="_blank" class="d-inline-block mt-2"><img src="{{ route('p2h.photo', $a->id) }}" alt="Foto {{ $a->label }}" loading="lazy" class="rounded border" style="height:72px"></a>@endif
                                    </td>
                                    <td class="text-end" style="width:90px">
                                        <span class="badge {{ $a->isBad() ? 'bg-danger' : 'bg-success-subtle text-success' }}">{{ $a->isBad() ? 'Rusak' : 'Baik' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
