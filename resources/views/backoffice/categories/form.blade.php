@extends('backoffice.layouts.app')

@php($pageTitle = ($category ? 'Edit ' : 'Tambah ').$cfg['kicker'].' - ATG POS')

@section('content')
<style>
    .cat-form{max-width:640px;display:grid;gap:18px}.cat-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:22px;display:grid;gap:16px}
    .field{display:grid;gap:6px}.field label{font-size:12px;font-weight:800;color:#64748b;text-transform:uppercase}.field input[type=text],.field select{border:1px solid #cbd5e1;border-radius:10px;padding:12px;background:#fff}
    .err{color:#b91c1c;font-size:13px;font-weight:700}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:12px;padding:11px 18px;background:#ea580c;color:#fff;font-weight:800;text-decoration:none;cursor:pointer}.btn-dark{background:#111827}
</style>
<div class="cat-form">
    <div><a href="{{ route($cfg['route'].'.index') }}" style="color:#ea580c">← {{ $cfg['title'] }}</a><h1>{{ $category ? 'Edit' : 'Tambah' }} {{ $cfg['kicker'] }}</h1></div>
    <form method="POST" action="{{ $category ? route($cfg['route'].'.update', $category->id) : route($cfg['route'].'.store') }}" class="cat-card">
        @csrf
        @if($category) @method('PUT') @endif

        <div class="field">
            <label for="name">Nama Category</label>
            <input type="text" id="name" name="name" value="{{ old('name', $category?->name) }}" maxlength="255" required>
            @error('name')<div class="err">{{ $message }}</div>@enderror
        </div>

        @if($cfg['needs_brand'])
            <div class="field">
                <label for="brand_id">Brand</label>
                <select id="brand_id" name="brand_id" required>
                    <option value="">Pilih brand</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" @selected((string) old('brand_id', $category?->brand_id) === (string) $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
                @error('brand_id')<div class="err">{{ $message }}</div>@enderror
            </div>
        @endif

        <label style="display:flex;gap:10px;align-items:center;font-weight:700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category ? $category->is_active : true))> Active
        </label>

        <div style="display:flex;gap:10px">
            <button class="btn" type="submit">Simpan</button>
            <a class="btn btn-dark" href="{{ route($cfg['route'].'.index') }}">Batal</a>
        </div>
    </form>
</div>
@endsection
