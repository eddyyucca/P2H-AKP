@extends('layouts.app')

@section('title', 'Checklist')
@section('page-title', 'Checklist P2H')
@section('breadcrumb')
    <li class="breadcrumb-item">Data Master</li>
    <li class="breadcrumb-item active">Checklist</li>
@endsection

@section('content')
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white"><h2 class="h6 mb-0">Versi checklist</h2></div>
            <div class="list-group list-group-flush">
                @forelse ($versions as $v)
                    <a href="{{ route('p2h.checklist', ['versi' => $v->id]) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $selected && $selected->id === $v->id ? 'active' : '' }}">
                        <span><span class="fw-semibold">{{ $v->category }}</span> &middot; {{ $v->name }}<br><small class="{{ $selected && $selected->id === $v->id ? '' : 'text-muted' }}">{{ $v->items_count }} item</small></span>
                        @if ($v->is_active)<span class="badge bg-success">Aktif</span>@else<span class="badge bg-secondary">Draf</span>@endif
                    </a>
                @empty
                    <div class="list-group-item text-muted">Belum ada versi.</div>
                @endforelse
            </div>
        </div>

        @if ($canEdit)
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Buat versi baru</h2></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('p2h.checklist.version.store') }}" novalidate>
                        @csrf
                        <div class="mb-2">
                            <label for="v_name" class="form-label small">Nama versi</label>
                            <input type="text" id="v_name" name="name" value="{{ old('name') }}" maxlength="80" class="form-control form-control-sm @error('name') is-invalid @enderror" placeholder="Versi Nov 2026">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label for="v_cat" class="form-label small">Kategori</label>
                            <input type="text" id="v_cat" name="category" value="{{ old('category', $selected?->category ?? 'LV') }}" maxlength="30" class="form-control form-control-sm text-uppercase @error('category') is-invalid @enderror">
                            @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @if ($selected)
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="v_copy" name="copy_from" value="{{ $selected->id }}" checked>
                                <label class="form-check-label small" for="v_copy">Salin item dari "{{ $selected->name }}" (kategori sama)</label>
                            </div>
                        @endif
                        <button class="btn btn-primary btn-sm w-100">Buat versi (draf)</button>
                    </form>
                </div>
            </div>
        @endif
    </div>

    <div class="col-12 col-xl-8">
        @if ($selected)
            <div class="card shadow-sm mb-3">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <div class="h5 mb-0">{{ $selected->category }} &middot; {{ $selected->name }}</div>
                        <div class="small text-muted">Dibuat oleh {{ $selected->created_by_name ?: '-' }}@if ($selected->activated_at) &middot; aktif sejak {{ $selected->activated_at->translatedFormat('d M Y') }}@endif</div>
                    </div>
                    @if ($selected->is_active)
                        <span class="badge bg-success fs-6">Sedang dipakai</span>
                    @elseif ($canEdit)
                        <form method="POST" action="{{ route('p2h.checklist.version.activate', $selected) }}" onsubmit="return confirm('Aktifkan versi ini? P2H berikutnya untuk kategori {{ $selected->category }} memakai versi ini. Riwayat tidak berubah.');">@csrf
                            <button class="btn btn-success btn-sm"><i class="bi bi-check2-circle me-1"></i>Aktifkan versi ini</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Item</h2><div class="small text-muted">Item <span class="badge bg-danger-subtle text-danger">Kritis</span> yang rusak membuat unit TIDAK LAYAK.</div></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Grup</th><th>Item</th><th class="text-center">Kritis</th><th>Urut</th>@if ($canEdit)<th class="text-end">Aksi</th>@endif</tr></thead>
                        <tbody>
                            @foreach ($groups as $group => $rows)
                                @foreach ($rows as $item)
                                    @if ($canEdit)
                                        <tr>
                                            <form method="POST" action="{{ route('p2h.checklist.item.update', $item) }}" id="f{{ $item->id }}">@csrf @method('PUT')</form>
                                            <td style="width:150px"><input form="f{{ $item->id }}" type="text" name="group_name" value="{{ $item->group_name }}" maxlength="60" class="form-control form-control-sm text-uppercase"></td>
                                            <td><input form="f{{ $item->id }}" type="text" name="label" value="{{ $item->label }}" maxlength="200" class="form-control form-control-sm"></td>
                                            <td class="text-center"><input form="f{{ $item->id }}" type="checkbox" name="is_critical" value="1" class="form-check-input" @checked($item->is_critical)></td>
                                            <td style="width:80px"><input form="f{{ $item->id }}" type="number" name="sort_order" value="{{ $item->sort_order }}" min="0" max="9999" class="form-control form-control-sm"></td>
                                            <td class="text-end text-nowrap">
                                                <button form="f{{ $item->id }}" class="btn btn-outline-primary btn-sm" title="Simpan"><i class="bi bi-check2"></i></button>
                                                <form method="POST" action="{{ route('p2h.checklist.item.destroy', $item) }}" class="d-inline" onsubmit="return confirm('Hapus item ini dari versi?');">@csrf @method('DELETE')
                                                    <button class="btn btn-outline-danger btn-sm" title="Hapus"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td class="small text-muted">{{ $item->group_name }}</td>
                                            <td>{{ $item->label }}</td>
                                            <td class="text-center">@if ($item->is_critical)<span class="badge bg-danger-subtle text-danger">Kritis</span>@endif</td>
                                            <td>{{ $item->sort_order }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            @endforeach
                            @if ($groups->isEmpty())<tr><td colspan="5" class="text-center text-muted py-4">Versi ini belum punya item.</td></tr>@endif
                        </tbody>
                    </table>
                </div>

                @if ($canEdit)
                    <div class="card-body border-top">
                        <form method="POST" action="{{ route('p2h.checklist.item.store', $selected) }}" class="row g-2 align-items-end" novalidate>
                            @csrf
                            <div class="col-12 col-md-3"><label class="form-label small mb-1" for="n_group">Grup</label><input id="n_group" name="group_name" maxlength="60" class="form-control form-control-sm text-uppercase" placeholder="MESIN" required></div>
                            <div class="col-12 col-md-5"><label class="form-label small mb-1" for="n_label">Item baru</label><input id="n_label" name="label" maxlength="200" class="form-control form-control-sm" placeholder="Mis. Level oli hidrolik" required></div>
                            <div class="col-6 col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_critical" value="1" id="n_crit"><label class="form-check-label small" for="n_crit">Kritis</label></div></div>
                            <div class="col-6 col-md-2"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg"></i> Tambah</button></div>
                        </form>
                    </div>
                @endif
            </div>
        @else
            <div class="alert alert-info">Belum ada checklist.</div>
        @endif
    </div>
</div>
@endsection
