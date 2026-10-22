@extends('layouts.app')

@php($editing = $unit->exists)
@section('title', $editing ? 'Ubah Unit' : 'Tambah Unit')
@section('page-title', $editing ? 'Ubah Unit' : 'Tambah Unit')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('p2h.units') }}">Unit</a></li>
    <li class="breadcrumb-item active">{{ $editing ? 'Ubah' : 'Tambah' }}</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm"><div class="card-body p-4">
            <form method="POST" action="{{ $editing ? route('p2h.units.update', $unit) : route('p2h.units.store') }}" novalidate>
                @csrf @if ($editing) @method('PUT') @endif

                <div class="mb-3">
                    <label for="code" class="form-label">Kode unit <span class="text-danger">*</span></label>
                    <input type="text" id="code" name="code" value="{{ old('code', $unit->code) }}" maxlength="30" @readonly($editing) required
                           class="form-control text-uppercase @error('code') is-invalid @enderror" placeholder="LV-12">
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">{{ $editing ? 'Kode tidak bisa diubah karena sudah tersimpan di riwayat P2H.' : 'Sama dengan tulisan di unit, tanpa spasi. Tidak bisa diubah setelah dibuat.' }}</div>
                </div>

                <div class="mb-3">
                    <label for="name" class="form-label">Nama / jenis unit <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $unit->name) }}" maxlength="120" required class="form-control @error('name') is-invalid @enderror" placeholder="Double cabin 4x4">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row">
                    <div class="col-12 col-md-6 mb-3">
                        <label for="category" class="form-label">Kategori <span class="text-danger">*</span></label>
                        <select id="category" name="category" class="form-select @error('category') is-invalid @enderror">
                            @foreach ($categories as $c)<option value="{{ $c }}" @selected(old('category', $unit->category) === $c)>{{ $c }}</option>@endforeach
                        </select>
                        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Menentukan checklist yang dipakai.</div>
                    </div>
                    <div class="col-12 col-md-6 mb-3">
                        <label for="plate" class="form-label">Plat / nomor lambung</label>
                        <input type="text" id="plate" name="plate" value="{{ old('plate', $unit->plate) }}" maxlength="30" class="form-control" placeholder="DT 8123 KC">
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-md-6 mb-3">
                        <label for="service_interval_km" class="form-label">Interval servis (km)</label>
                        <input type="number" min="100" id="service_interval_km" name="service_interval_km" value="{{ old('service_interval_km', $unit->service_interval_km) }}" class="form-control @error('service_interval_km') is-invalid @enderror" placeholder="mis. 5000">
                        @error('service_interval_km')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Opsional. Dashboard memberi peringatan saat servis mendekat.</div>
                    </div>
                    <div class="col-12 col-md-6 mb-3">
                        <label for="last_service_km" class="form-label">Servis terakhir pada (km)</label>
                        <input type="number" min="0" id="last_service_km" name="last_service_km" value="{{ old('last_service_km', $unit->last_service_km) }}" class="form-control @error('last_service_km') is-invalid @enderror">
                        @error('last_service_km')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mb-4">
                    <label for="notes" class="form-label">Catatan</label>
                    <input type="text" id="notes" name="notes" value="{{ old('notes', $unit->notes) }}" maxlength="255" class="form-control">
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <a href="{{ route('p2h.units') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        </div></div>
    </div>
</div>
@endsection
