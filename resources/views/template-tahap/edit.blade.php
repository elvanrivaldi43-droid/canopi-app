@extends('layouts.app')
@section('title', 'Edit Template')
@section('page-title', 'Edit Template')
@section('sidebar-menu')
    @include('partials.sidebar-owner')
@endsection
@section('content')
<style>
* { box-sizing:border-box; }
.tf-wrap { padding:14px 12px 60px; max-width:560px; }
.tf-label { font-size:12px; color:#94a3b8; margin:12px 0 4px; display:block; }
.tf-input, .tf-select { width:100%; background:#0f172a; border:1px solid #334155; border-radius:8px; padding:10px; color:#f1f5f9; font-size:14px; }
.tf-check-row { display:flex; align-items:center; gap:8px; padding:6px 0; border-bottom:1px solid #263349; }
.btn { border:none; border-radius:10px; padding:12px 18px; min-height:48px; font-size:14px; font-weight:700; cursor:pointer; margin-top:16px; }
.btn-gold { background:#fbbf24; color:#0f172a; }
.tf-err { color:#f87171; font-size:12px; margin-top:4px; }
</style>

<div class="tf-wrap">
    <h1 style="font-size:18px;font-weight:700;color:#fbbf24;">Edit Template — {{ $templateTahap->nama }}</h1>

    @if($errors->any())
    <div style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);border-radius:8px;padding:10px;font-size:13px;color:#fca5a5;margin-top:10px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('template-tahap.update', $templateTahap) }}">
        @csrf
        @method('PUT')
        <label class="tf-label">Nama Template</label>
        <input type="text" name="nama" class="tf-input" value="{{ old('nama', $templateTahap->nama) }}" required>

        <label class="tf-label">Jenis Project</label>
        <select name="jenis_project" class="tf-select" required>
            <option value="">- pilih -</option>
            @foreach($jenisProjectOptions as $jp)
            <option value="{{ $jp }}" {{ old('jenis_project', $templateTahap->jenis_project)==$jp?'selected':'' }}>{{ $jp }}</option>
            @endforeach
        </select>

        <label class="tf-label">Tahap yang dipakai (urutan checklist = urutan kerja)</label>
        @forelse($tahapList as $tahap)
        <div class="tf-check-row">
            <input type="checkbox" name="tahap_ids[]" value="{{ $tahap->id }}" id="th{{ $tahap->id }}" {{ in_array($tahap->id, $selectedIds) ? 'checked' : '' }}>
            <label for="th{{ $tahap->id }}">{{ $tahap->nama }}</label>
        </div>
        @empty
        <p class="tf-err">Belum ada tahap master.</p>
        @endforelse

        <button type="submit" class="btn btn-gold">Update Template</button>
    </form>
</div>
@endsection
