@extends('layouts.app')

@section('title', 'Isi P2H')
@section('page-title', 'Isi P2H')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('p2h.index') }}">P2H</a></li>
    <li class="breadcrumb-item active">Isi</li>
@endsection

@push('styles')
<style>
    .grp-title { font-size: .75rem; letter-spacing: .08em; font-weight: 700; color: #64748b; }
    .chk-row { border-bottom: 1px solid #eef2f7; padding: .7rem 0; }
    .chk-row:last-child { border-bottom: 0; }
    .chk-row.is-bad { background: #fff5f5; margin-inline: -1rem; padding-inline: 1rem; }
    .chk-row .btn-check:checked + .btn-ok { background: #16a34a; border-color: #16a34a; color: #fff; }
    .chk-row .btn-check:checked + .btn-bad { background: #dc2626; border-color: #dc2626; color: #fff; }
    .sticky-submit { position: sticky; bottom: 0; background: #fff; border-top: 1px solid #e5eaf1; padding: .75rem 1rem; margin: 0 -1rem -1rem; z-index: 5; }
</style>
@endpush

@section('content')
@if (! $unit)
    {{-- Langkah 1: pilih unit --}}
    <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
            @if ($unknownCode)
                <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>Unit <strong>{{ $unknownCode }}</strong> tidak ditemukan atau tidak aktif. Pilih unit dari daftar di bawah.</div>
            @endif
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-truck-front me-2"></i>Pilih unit yang diperiksa</h2></div>
                <div class="card-body">
                    <p class="small text-muted">Pilih unit yang akan Anda periksa, lalu isi checklist.</p>
                    <input type="search" id="unitSearch" class="form-control mb-3" placeholder="Cari kode, nama, atau plat unit" autocomplete="off">
                    <div class="list-group" id="unitList">
                        @forelse ($units as $u)
                            <a href="{{ route('p2h.fill', ['unit' => $u->code]) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-text="{{ strtolower($u->code.' '.$u->name.' '.$u->plate) }}">
                                <span><span class="fw-semibold">{{ $u->code }}</span> <span class="text-muted small">&middot; {{ $u->name }}</span></span>
                                <span class="small text-muted">{{ $u->plate }}</span>
                            </a>
                        @empty
                            <div class="text-center text-muted py-4">Belum ada unit aktif. Hubungi administrator.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
    <script>
    document.getElementById('unitSearch')?.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('#unitList [data-text]').forEach(el => { el.hidden = q !== '' && !el.dataset.text.includes(q); });
    });
    </script>
    @endpush
@elseif (! $version)
    <div class="alert alert-danger"><i class="bi bi-exclamation-octagon-fill me-2"></i>Unit <strong>{{ $unit->code }}</strong> (kategori {{ $unit->category }}) belum punya checklist aktif. Hubungi administrator.</div>
@else
    <form method="POST" action="{{ route('p2h.store') }}" id="p2hForm" enctype="multipart/form-data" novalidate>
        @csrf
        <input type="hidden" name="form_token" value="{{ $formToken }}">
        <input type="hidden" name="unit" value="{{ $unit->code }}">

        <div class="row g-4">
            <div class="col-12 col-xl-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="h4 fw-bold mb-0">{{ $unit->code }}</div>
                                <div class="text-muted">{{ $unit->name }}</div>
                                @if ($unit->plate)<div class="small text-muted">{{ $unit->plate }}</div>@endif
                            </div>
                            <a href="{{ route('p2h.fill') }}" class="btn btn-outline-secondary btn-sm">Ganti unit</a>
                        </div>
                        <hr>
                        <div class="small text-muted">Pemeriksa (akun Anda)</div>
                        <div class="fw-semibold">{{ $portalUser->name }}</div>
                        <div class="small text-muted">NIK {{ $portalUser->employee_no ?: '-' }}</div>
                        <div class="small text-muted mt-2">Tanggal {{ now()->translatedFormat('d M Y H:i') }}</div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="shift" class="form-label">Shift</label>
                            <select id="shift" name="shift" class="form-select @error('shift') is-invalid @enderror">
                                @foreach (\App\Models\Inspection::SHIFTS as $k => $label)
                                    <option value="{{ $k }}" @selected(old('shift', $shift) === $k)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('shift')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="odometer" class="form-label">Odometer (km) <span class="text-danger">*</span></label>
                            <input type="number" inputmode="numeric" min="0" id="odometer" name="odometer" value="{{ old('odometer') }}" class="form-control @error('odometer') is-invalid @enderror" placeholder="mis. 48215">
                            @error('odometer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if ($lastOdometer !== null)<div class="form-text" id="odoHint" data-last="{{ $lastOdometer }}" data-max-jump="{{ $maxJump }}">P2H sebelumnya: {{ number_format($lastOdometer, 0, ',', '.') }} km.</div>@endif
                            <div class="form-check mt-2" id="odoConfirmBox" @if (! session('odometer_jump') && ! old('odometer_confirm')) hidden @endif>
                                <input class="form-check-input" type="checkbox" value="1" id="odometer_confirm" name="odometer_confirm" @checked(old('odometer_confirm'))>
                                <label class="form-check-label small" for="odometer_confirm">Selisih odometer sudah benar</label>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label for="fuel_level" class="form-label">Level BBM <span class="text-danger">*</span></label>
                            <select id="fuel_level" name="fuel_level" class="form-select @error('fuel_level') is-invalid @enderror">
                                <option value="">Pilih...</option>
                                @foreach ($fuelLevels as $f)
                                    <option value="{{ $f }}" @selected(old('fuel_level') === $f)>{{ $f }}</option>
                                @endforeach
                            </select>
                            @error('fuel_level')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-8">
                @if ($errors->any())
                    <div class="alert alert-danger"><div class="fw-semibold mb-1">Belum bisa dikirim:</div>
                        <ul class="mb-0 small ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                @endif

                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h2 class="h6 mb-0">Checklist</h2>
                            <div class="small text-muted">{{ $version->name }}</div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <button type="button" class="btn btn-outline-success btn-sm" id="allOk"><i class="bi bi-check2-all me-1"></i>Semua Baik</button>
                            <div class="fw-bold"><span id="doneCount">0</span> / {{ $items->flatten()->count() }} diperiksa</div>
                        </div>
                    </div>
                    <div class="progress rounded-0" style="height:4px"><div class="progress-bar bg-success" id="bar" style="width:0%"></div></div>

                    <div class="card-body pb-0">
                        @foreach ($items as $group => $rows)
                            <div class="grp-title mt-3 mb-1">{{ $group }}</div>
                            @foreach ($rows as $item)
                                @php($ans = old("answers.{$item->id}"))
                                <div class="chk-row {{ $ans === 'bad' ? 'is-bad' : '' }}" data-row>
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <div class="me-2">
                                            {{ $item->label }}
                                            @if ($item->is_critical)<span class="badge bg-danger-subtle text-danger ms-1" title="Jika rusak, unit TIDAK LAYAK">Kritis</span>@endif
                                        </div>
                                        <div class="btn-group" role="group" aria-label="{{ $item->label }}">
                                            <input type="radio" class="btn-check" name="answers[{{ $item->id }}]" id="a{{ $item->id }}ok" value="ok" @checked($ans === 'ok')>
                                            <label class="btn btn-outline-success btn-sm btn-ok" for="a{{ $item->id }}ok">Baik</label>
                                            <input type="radio" class="btn-check" name="answers[{{ $item->id }}]" id="a{{ $item->id }}bad" value="bad" @checked($ans === 'bad')>
                                            <label class="btn btn-outline-danger btn-sm btn-bad" for="a{{ $item->id }}bad">Rusak</label>
                                        </div>
                                    </div>
                                    <div class="mt-2" data-note @if ($ans !== 'bad') hidden @endif>
                                        <input type="text" name="answer_notes[{{ $item->id }}]" value="{{ old("answer_notes.{$item->id}") }}" maxlength="300" class="form-control form-control-sm" placeholder="Keterangan kerusakan (wajib)">
                                        <input type="file" name="photos[{{ $item->id }}]" accept="image/*" capture="environment" class="form-control form-control-sm mt-2" aria-label="Foto kerusakan {{ $item->label }} (opsional)">
                                        <div class="form-text">Foto kerusakan (opsional). JPG/PNG/WEBP, otomatis dikecilkan.</div>
                                    </div>
                                </div>
                            @endforeach
                        @endforeach

                        <div class="mt-4">
                            <label for="notes" class="form-label">Catatan umum</label>
                            <textarea id="notes" name="notes" rows="2" maxlength="1000" class="form-control" placeholder="Opsional">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <div class="card-body pt-0">
                        <div class="form-check mb-2">
                            <input class="form-check-input @error('confirm') is-invalid @enderror" type="checkbox" value="1" id="confirm" name="confirm" @checked(old('confirm'))>
                            <label class="form-check-label" for="confirm">Saya menyatakan hasil pemeriksaan ini benar sesuai kondisi unit.</label>
                        </div>
                        <div class="sticky-submit d-flex flex-wrap gap-2 align-items-center">
                            <button type="submit" id="submitBtn" class="btn btn-primary btn-lg flex-grow-1 flex-md-grow-0"><i class="bi bi-send me-2"></i>Kirim P2H</button>
                            <span class="small text-muted" id="submitHint">Item kritis yang rusak membuat unit <strong>TIDAK LAYAK</strong>.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('scripts')
    <script>
    (function () {
        const form = document.getElementById('p2hForm');
        const rows = [...form.querySelectorAll('[data-row]')];
        const total = rows.length;

        function sync() {
            let done = 0;
            rows.forEach(row => {
                const picked = row.querySelector('input[type=radio]:checked');
                const bad = picked && picked.value === 'bad';
                if (picked) done++;
                row.classList.toggle('is-bad', bad);
                const note = row.querySelector('[data-note]');
                note.hidden = !bad;
            });
            document.getElementById('doneCount').textContent = done;
            document.getElementById('bar').style.width = (total ? Math.round(done / total * 100) : 0) + '%';
        }

        form.addEventListener('change', sync);
        form.addEventListener('submit', () => {
            const btn = document.getElementById('submitBtn');
            // Cegah klik ganda; token form di server juga menolak kiriman kedua.
            setTimeout(() => { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengirim...'; }, 0);
        });

        const odo = document.getElementById('odometer'), hint = document.getElementById('odoHint'), box = document.getElementById('odoConfirmBox');
        if (odo && hint) {
            const last = +hint.dataset.last, max = +hint.dataset.maxJump;
            const check = () => {
                const v = parseInt(odo.value, 10);
                if (isNaN(v)) { hint.className = 'form-text'; box.hidden = true; return; }
                if (v < last) { hint.className = 'form-text text-danger'; hint.textContent = 'Lebih kecil dari P2H sebelumnya (' + last.toLocaleString('id-ID') + ' km).'; box.hidden = true; }
                else if (v - last > max) { hint.className = 'form-text text-warning'; hint.textContent = 'Selisih ' + (v - last).toLocaleString('id-ID') + ' km dari P2H sebelumnya. Konfirmasi bila benar.'; box.hidden = false; }
                else { hint.className = 'form-text'; hint.textContent = 'P2H sebelumnya: ' + last.toLocaleString('id-ID') + ' km.'; box.hidden = true; }
            };
            odo.addEventListener('input', check);
        }
        document.getElementById('allOk').addEventListener('click', () => {
            rows.forEach(row => { const ok = row.querySelector('input[value=ok]'); if (!row.querySelector('input[value=bad]:checked')) ok.checked = true; });
            sync();
        });
        sync();
    })();
    </script>
    @endpush
@endif
@endsection
