{{-- ============================================================ --}}
{{-- FILE 1: resources/views/kpi/ujian-hasil.blade.php --}}
{{-- ============================================================ --}}
@extends('layouts.app')
@section('content')
<div style="padding: 16px; max-width: 700px; margin: 0 auto;">

    <a href="{{ route('kpi.ujian.index') }}" style="color: #94a3b8; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 16px;">← Kembali</a>

    @if($sesi)
    {{-- HASIL UJIAN --}}
    @php
        $nilaiWarna = $sesi->nilai >= 75 ? '#10b981' : ($sesi->nilai >= 50 ? '#fbbf24' : '#ef4444');
        $persen = round(($sesi->jumlah_benar / $sesi->jumlah_soal) * 100);
    @endphp
    <div style="background: #1e293b; border-radius: 14px; padding: 24px; margin-bottom: 20px; border: 1px solid #334155; text-align: center;">
        <div style="color: #94a3b8; font-size: 13px; margin-bottom: 8px;">Hasil Ujian {{ ucfirst($sesi->periode) }} {{ $sesi->tahun }}</div>
        <div style="font-size: 64px; font-weight: 900; color: {{ $nilaiWarna }}; line-height: 1;">{{ $sesi->nilai }}</div>
        <div style="color: #94a3b8; font-size: 14px; margin-top: 6px;">{{ $sesi->jumlah_benar }} benar dari {{ $sesi->jumlah_soal }} soal</div>

        <div style="background: #0f172a; border-radius: 8px; height: 8px; overflow: hidden; margin: 16px 0;">
            <div style="background: {{ $nilaiWarna }}; width: {{ $sesi->nilai }}%; height: 100%;"></div>
        </div>

        <div style="color: #64748b; font-size: 12px;">
            Selesai: {{ $sesi->selesai_pada?->format('d M Y H:i') }}
        </div>
    </div>

    {{-- PEMBAHASAN JAWABAN --}}
    <div style="background: #1e293b; border-radius: 12px; padding: 16px; margin-bottom: 20px; border: 1px solid #334155;">
        <div style="color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px;">Pembahasan</div>
        @foreach($sesi->jawaban->sortBy('urutan') as $j)
        @php $benar = $j->is_benar; @endphp
        <div style="background: {{ $benar ? 'rgba(16,185,129,0.05)' : 'rgba(239,68,68,0.05)' }}; border: 1px solid {{ $benar ? '#10b981' : '#ef4444' }}; border-radius: 8px; padding: 12px; margin-bottom: 10px;">
            <div style="display: flex; gap: 10px; margin-bottom: 8px;">
                <span style="background: {{ $benar ? '#10b981' : '#ef4444' }}; color: white; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0;">
                    {{ $benar ? '✓' : '✗' }}
                </span>
                <span style="color: #e2e8f0; font-size: 13px;">{{ $j->soal->pertanyaan }}</span>
            </div>
            <div style="margin-left: 32px; font-size: 12px;">
                @if($j->jawaban_karyawan)
                <span style="color: {{ $benar ? '#10b981' : '#ef4444' }};">Jawabanmu: {{ strtoupper($j->jawaban_karyawan) }}</span>
                @else
                <span style="color: #64748b;">Tidak dijawab</span>
                @endif
                @if(!$benar)
                <span style="color: #10b981; margin-left: 12px;">Benar: {{ strtoupper($j->soal->jawaban_benar) }}</span>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div style="background: #1e293b; border-radius: 12px; padding: 30px; text-align: center; color: #64748b;">
        Belum ada ujian periode ini
    </div>
    @endif

    {{-- RAPOR 6 BULANAN --}}
    @if($rapor && $rapor->status === 'selesai')
    @php $warna = \App\Models\RaporKaryawan::warnaKelas($rapor->kelas_baru); @endphp
    <div style="background: linear-gradient(135deg, #0f1a00, #1a2e00); border: 2px solid {{ $warna }}; border-radius: 14px; padding: 20px; margin-bottom: 20px;">
        <div style="color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">Rapor Karyawan {{ ucfirst($rapor->periode) }} {{ $rapor->tahun }}</div>
        <div style="font-size: 28px; font-weight: 800; color: {{ $warna }};">{{ \App\Models\RaporKaryawan::labelKelas($rapor->kelas_baru) }}</div>
        <div style="color: #94a3b8; font-size: 24px; font-weight: 700; margin-top: 4px;">{{ $rapor->nilai_total }}/100</div>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 14px;">
            <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 10px; text-align: center;">
                <div style="color: #e2e8f0; font-size: 16px; font-weight: 700;">{{ $rapor->nilai_kpi }}</div>
                <div style="color: #64748b; font-size: 11px;">KPI 6 bln</div>
            </div>
            <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 10px; text-align: center;">
                <div style="color: #e2e8f0; font-size: 16px; font-weight: 700;">{{ $rapor->nilai_ujian }}</div>
                <div style="color: #64748b; font-size: 11px;">Ujian</div>
            </div>
            <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 10px; text-align: center;">
                <div style="color: #e2e8f0; font-size: 16px; font-weight: 700;">{{ $rapor->nilai_sp }}</div>
                <div style="color: #64748b; font-size: 11px;">Rekam SP</div>
            </div>
        </div>

        @if($rapor->kenaikan_gaji > 0)
        <div style="background: rgba(16,185,129,0.1); border: 1px solid #10b981; border-radius: 8px; padding: 12px; margin-top: 14px; text-align: center;">
            <div style="color: #10b981; font-size: 15px; font-weight: 700;">
                🎉 Gaji naik Rp {{ number_format($rapor->kenaikan_gaji, 0, ',', '.') }} permanen mulai bulan depan!
            </div>
        </div>
        @endif

        @if($rapor->kelas_sebelumnya && $rapor->kelas_sebelumnya !== $rapor->kelas_baru)
        <div style="color: #64748b; font-size: 12px; margin-top: 10px; text-align: center;">
            {{ \App\Models\RaporKaryawan::labelKelas($rapor->kelas_sebelumnya) }}
            {{ $rapor->kelas_naik > 0 ? '→ ⬆️' : '→ ⬇️' }}
            {{ \App\Models\RaporKaryawan::labelKelas($rapor->kelas_baru) }}
        </div>
        @endif
    </div>
    @endif

</div>
@endsection