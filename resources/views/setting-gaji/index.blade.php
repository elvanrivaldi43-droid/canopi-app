@extends('layouts.app')
@section('title', 'Pengaturan Gaji')
@section('page-title', 'Pengaturan Gaji')
@section('sidebar-menu')
    @include('partials.sidebar-owner')
@endsection

@section('content')
<div style="max-width:640px;margin:0 auto;padding:16px">
    <h1 style="font-size:20px;font-weight:700;margin:0 0 4px">Pengaturan Gaji</h1>
    <p style="font-size:12.5px;color:#94a3b8;margin:0 0 14px">Saklar kebijakan yang mempengaruhi perhitungan slip. Mengubah ini TIDAK mengubah slip yang sudah terlanjur dibuat &mdash; slip yang belum dibayar perlu ditekan <b>Hitung Ulang</b> di halaman Penggajian.</p>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-danger">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ url('/setting-gaji') }}">
        @csrf
        <div style="background:#1e293b;border:1px solid #334155;border-radius:10px;padding:14px;margin-bottom:12px">
            <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer">
                <input type="checkbox" name="bonus_kpi_aktif" value="1" style="margin-top:3px" {{ ($s->bonus_kpi_aktif ?? 0) ? 'checked' : '' }}>
                <span>
                    <b style="color:#e2e8f0">Bayar bonus KPI</b>
                    <div style="font-size:12px;color:#94a3b8;margin-top:2px">Kelas KPI (platinum/gold/silver) tetap dihitung &amp; tampil di slip apa pun pilihannya &mdash; yang disetel di sini hanya <b>dibayar atau tidak</b>. Mati = bonus Rp 0.</div>
                </span>
            </label>
        </div>

        <div style="background:#1e293b;border:1px solid #334155;border-radius:10px;padding:14px;margin-bottom:14px">
            <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer">
                <input type="checkbox" name="tabungan_wajib_aktif" value="1" style="margin-top:3px" {{ ($s->tabungan_wajib_aktif ?? 0) ? 'checked' : '' }}>
                <span>
                    <b style="color:#e2e8f0">Potong tabungan wajib (Rp 100.000)</b>
                    <div style="font-size:12px;color:#94a3b8;margin-top:2px">Mati = barisnya tetap muncul di slip tertulis Rp 0 (transparan), tidak memotong gaji.</div>
                </span>
            </label>
        </div>

        <button type="submit" class="btn btn-gold" style="background:#fbbf24;color:#0f172a;border:0;border-radius:8px;padding:10px 18px;font-weight:700;cursor:pointer">Simpan</button>
    </form>
</div>
@endsection
