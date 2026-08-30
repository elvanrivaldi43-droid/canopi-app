@extends('layouts.app')
@section('title', 'Cutting List')
@section('page-title', 'Cutting List')
@section('sidebar-menu')
    @if(auth()->user()->level == 1)
        @include('partials.sidebar-owner')
    @else
        @include('partials.sidebar-pipeline')
    @endif
@endsection

@section('content')
<style>
.ct-wrap { max-width:900px; margin:0 auto; padding:16px; }
.ct-title { font-size:20px; font-weight:700; margin:0 0 4px; }
.ct-sub { font-size:12.5px; color:#94a3b8; margin:0 0 14px; }
.ct-card { background:#1e293b; border:1px solid #334155; border-radius:10px; padding:14px; margin-bottom:14px; }
.ct-btn { background:#fbbf24; color:#0f172a; border:0; border-radius:8px; padding:10px 16px; font-weight:700; cursor:pointer; font-size:13px; }
</style>

<div class="ct-wrap">
    <h1 class="ct-title">Cutting List</h1>
    <p class="ct-sub">Gambar denah kanopi (bentuk apa pun — sama seperti di RAB Multi-Opsi), lalu buka cutting list-nya: jumlah batang + daftar potong per batang untuk produksi. Tanpa harga.</p>

    {{-- Cutting list dari RAB tersimpan (lead) — buat mengecek project yang sudah digambar --}}
    <div class="ct-card" style="border-left:3px solid #fbbf24">
        <div style="font-weight:700;font-size:13px;margin-bottom:6px">Dari RAB tersimpan</div>
        @if($leadDenah->count())
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <select id="leadDenahSel" style="flex:1;min-width:200px;padding:8px;border-radius:8px">
                @foreach($leadDenah as $ld)
                <option value="{{ $ld->id }}">#{{ $ld->id }} — {{ $ld->nama_customer ?: 'Tanpa nama' }}</option>
                @endforeach
            </select>
            <button type="button" class="ct-btn"
                onclick="window.open('{{ url('/cutting-denah') }}?lead='+document.getElementById('leadDenahSel').value,'_blank')">Buka</button>
        </div>
        @else
        <div style="font-size:12px;color:#64748b">Belum ada lead dengan blok Denah.</div>
        @endif
    </div>

    {{-- Gambar langsung di sini (tanpa lead) --}}
    <div class="ct-card">
        <div style="font-weight:700;font-size:13px;margin-bottom:8px">Gambar langsung</div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
            <input type="text" id="ctJudul" placeholder="Judul (mis. Kanopi Pak Budi 4x8)" style="flex:1;min-width:220px;padding:8px;border-radius:8px">
            <button type="button" class="ct-btn" onclick="bukaCutting()">Buka Cutting List</button>
        </div>
        <div id="denahMount"></div>
    </div>
</div>

{{-- Form POST sungguhan (bukan fetch) + target _blank: dibuka langsung dari sentuhan,
     aman dari blokir tab Safari iOS (pelajaran tombol Buat Penawaran 28 Ags). --}}
<form id="formCutting" method="POST" action="{{ url('/cutting-denah/manual') }}" target="_blank" style="display:none">
    @csrf
    <input type="hidden" name="judul" id="fJudul">
    <input type="hidden" name="members" id="fMembers">
    <input type="hidden" name="denah_svg" id="fSvg">
</form>

<script src="{{ asset('js/denah-editor.js') }}?v={{ @filemtime(public_path('js/denah-editor.js')) ?: time() }}"></script>
<script>
const BESI = @json($besi);
let ED = null;
document.addEventListener('DOMContentLoaded', function(){
    // Harga sengaja 0 semua: halaman ini dokumen produksi/kalibrasi, tanpa harga.
    ED = new DenahEditor(document.getElementById('denahMount'), {
        besi: BESI.map(function(b){ return { nama:b.nama, harga:0, lebar:b.lebar??null, tinggi:b.tinggi??null }; }),
        cuttingUrl: '{{ url("/rab-blok/cutting") }}',   // legend jumlah batang live (mesin yang sama)
        csrf: '{{ csrf_token() }}'
    });
});
function bukaCutting(){
    if(!ED) return;
    var members = ED.getMembers().map(function(m){ return { nama:m.nama, jenis:m.jenis, panjang:m.panjang, material:m.material }; });
    if(!members.length){ alert('Denah belum punya batang besi — gambar bentuknya dulu.'); return; }
    document.getElementById('fJudul').value = document.getElementById('ctJudul').value || 'Cutting List Denah';
    document.getElementById('fMembers').value = JSON.stringify(members);
    document.getElementById('fSvg').value = ED.snapshotCetak();
    document.getElementById('formCutting').submit();
}
</script>
@endsection
