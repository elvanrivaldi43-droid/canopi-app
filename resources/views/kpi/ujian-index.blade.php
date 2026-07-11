@extends('layouts.app')
@section('content')
<div style="padding: 16px; max-width: 600px; margin: 0 auto;">
 
    <a href="{{ route('kpi.detail') }}" style="color: #94a3b8; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 16px;">← Poin Kinerja Saya</a>
 
    <h1 style="color: #fbbf24; font-size: 18px; font-weight: 700; margin-bottom: 20px;">📝 Ujian Online</h1>
 
    @if(session('info'))
    <div style="background: #1e3a5f; border: 1px solid #60a5fa; color: #93c5fd; padding: 12px 16px; border-radius: 8px; margin-bottom: 14px; font-size: 14px;">
        ℹ️ {{ session('info') }}
    </div>
    @endif
 
    {{-- INFO PERIODE --}}
    <div style="background: #1e293b; border-radius: 12px; padding: 16px; margin-bottom: 16px; border: 1px solid #334155;">
        <div style="color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Periode Aktif</div>
        <div style="color: #e2e8f0; font-size: 16px; font-weight: 700;">{{ ucfirst($periode) }} {{ $tahun }}</div>
        <div style="color: #64748b; font-size: 13px; margin-top: 4px;">20 soal pilihan ganda · Waktu 30 menit</div>
    </div>
 
    @if($sesi && $sesi->status === 'selesai')
    {{-- SUDAH SELESAI --}}
    <div style="background: #1e293b; border-radius: 12px; padding: 20px; margin-bottom: 16px; border: 1px solid #334155; text-align: center;">
        <div style="font-size: 40px; margin-bottom: 10px;">✅</div>
        <div style="color: #10b981; font-size: 18px; font-weight: 700; margin-bottom: 6px;">Ujian Selesai!</div>
        <div style="color: #94a3b8; font-size: 14px; margin-bottom: 16px;">
            Nilai kamu: <strong style="color: #fbbf24; font-size: 20px;">{{ $sesi->nilai }}/100</strong><br>
            {{ $sesi->jumlah_benar }} benar dari {{ $sesi->jumlah_soal }} soal
        </div>
        <a href="{{ route('kpi.ujian.hasil') }}" style="background: #fbbf24; color: #0f172a; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 700; display: inline-block;">
            Lihat Pembahasan & Rapor
        </a>
    </div>
 
    @elseif($sesi && $sesi->status === 'berlangsung')
    {{-- SEDANG BERLANGSUNG --}}
    <div style="background: #1e293b; border-radius: 12px; padding: 20px; margin-bottom: 16px; border: 1px solid #fbbf24; text-align: center;">
        <div style="font-size: 40px; margin-bottom: 10px;">⏱️</div>
        <div style="color: #fbbf24; font-size: 18px; font-weight: 700; margin-bottom: 6px;">Ujian Sedang Berlangsung</div>
        <div style="color: #94a3b8; font-size: 14px; margin-bottom: 16px;">
            Kamu sudah memulai ujian. Lanjutkan sebelum waktu habis!
        </div>
        <a href="{{ route('kpi.ujian.kerjakan') }}" style="background: #fbbf24; color: #0f172a; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 700; display: inline-block;">
            Lanjutkan Ujian →
        </a>
    </div>
 
    @elseif($ujianDibuka === '1')
    {{-- UJIAN DIBUKA, BELUM MULAI --}}
    <div style="background: #1e293b; border-radius: 12px; padding: 20px; margin-bottom: 16px; border: 1px solid #334155; text-align: center;">
        <div style="font-size: 40px; margin-bottom: 10px;">📋</div>
        <div style="color: #e2e8f0; font-size: 17px; font-weight: 700; margin-bottom: 8px;">Ujian Sudah Dibuka!</div>
        <div style="color: #94a3b8; font-size: 13px; margin-bottom: 6px;">Persiapkan dirimu:</div>
        <div style="color: #64748b; font-size: 13px; margin-bottom: 20px; text-align: left; background: #0f172a; border-radius: 8px; padding: 12px;">
            ✅ 20 soal pilihan ganda<br>
            ✅ Waktu 30 menit (tidak bisa dipause)<br>
            ✅ Jawaban disimpan otomatis per soal<br>
            ✅ Hasil langsung keluar setelah submit<br>
            ⚠️ Ujian hanya bisa dikerjakan 1x
        </div>
        <form method="POST" action="{{ route('kpi.ujian.mulai') }}">
            @csrf
            <button type="submit" onclick="return confirm('Mulai ujian sekarang? Waktu 30 menit langsung berjalan.')"
                style="background: #fbbf24; color: #0f172a; border: none; padding: 14px 32px; border-radius: 10px; font-size: 16px; font-weight: 800; cursor: pointer; width: 100%;">
                🚀 Mulai Ujian Sekarang
            </button>
        </form>
    </div>
 
    @else
    {{-- UJIAN BELUM DIBUKA --}}
    <div style="background: #1e293b; border-radius: 12px; padding: 30px; border: 1px solid #334155; text-align: center; color: #64748b;">
        <div style="font-size: 40px; margin-bottom: 12px;">🔒</div>
        <div style="font-size: 15px; color: #94a3b8;">Ujian periode ini belum dibuka.</div>
        <div style="font-size: 13px; margin-top: 8px;">Owner akan membuka ujian pada waktunya.<br>Pantau terus aplikasi ya!</div>
    </div>
    @endif
 
    {{-- RAPOR TERAKHIR --}}
    @if($rapor && $rapor->status === 'selesai')
    <div style="background: #1e293b; border-radius: 12px; padding: 16px; border: 1px solid #334155;">
        @php $warna = \App\Models\RaporKaryawan::warnaKelas($rapor->kelas_baru); @endphp
        <div style="color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Rapor Terakhir</div>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-size: 22px; font-weight: 800; color: {{ $warna }};">{{ \App\Models\RaporKaryawan::labelKelas($rapor->kelas_baru) }}</div>
                <div style="color: #64748b; font-size: 12px;">{{ ucfirst($rapor->periode) }} {{ $rapor->tahun }} · Nilai {{ $rapor->nilai_total }}/100</div>
            </div>
            <a href="{{ route('kpi.ujian.hasil') }}" style="color: #94a3b8; font-size: 12px; text-decoration: none; border: 1px solid #334155; padding: 6px 12px; border-radius: 6px;">Detail</a>
        </div>
    </div>
    @endif
 
</div>
@endsection