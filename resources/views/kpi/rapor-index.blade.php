@extends('layouts.app')
@section('content')
<div style="padding: 16px; max-width: 900px; margin: 0 auto;">
 
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <h1 style="color: #fbbf24; font-size: 18px; font-weight: 700; margin: 0;">🎓 Rapor Karyawan</h1>
 
        {{-- BUKA/TUTUP UJIAN --}}
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <form method="POST" action="{{ route('kpi.ujian.toggle') }}" style="display: inline;">
                @csrf
                <input type="hidden" name="periode" value="{{ $periode }}">
                <input type="hidden" name="tahun" value="{{ $tahun }}">
                <input type="hidden" name="aksi" value="buka">
                <button type="submit" style="background: #10b981; color: white; border: none; padding: 8px 14px; border-radius: 8px; font-size: 13px; cursor: pointer;">📂 Buka Ujian</button>
            </form>
            <form method="POST" action="{{ route('kpi.ujian.toggle') }}" style="display: inline;">
                @csrf
                <input type="hidden" name="periode" value="{{ $periode }}">
                <input type="hidden" name="tahun" value="{{ $tahun }}">
                <input type="hidden" name="aksi" value="tutup">
                <button type="submit" style="background: #ef4444; color: white; border: none; padding: 8px 14px; border-radius: 8px; font-size: 13px; cursor: pointer;">🔒 Tutup Ujian</button>
            </form>
        </div>
    </div>
 
    @if(session('success'))
    <div style="background: #064e3b; border: 1px solid #10b981; color: #6ee7b7; padding: 12px 16px; border-radius: 8px; margin-bottom: 14px; font-size: 14px;">✅ {{ session('success') }}</div>
    @endif
 
    {{-- FILTER PERIODE --}}
    <form method="GET" style="margin-bottom: 16px; display: flex; gap: 8px; flex-wrap: wrap;">
        <select name="periode" onchange="this.form.submit()" style="background: #1e293b; color: #e2e8f0; border: 1px solid #334155; padding: 8px 12px; border-radius: 8px; font-size: 14px;">
            @foreach($periodeOptions as $opt)
            <option value="{{ $opt['periode'] }}" {{ $opt['periode'] === $periode && $opt['tahun'] == $tahun ? 'selected' : '' }}
                data-tahun="{{ $opt['tahun'] }}">{{ $opt['label'] }}</option>
            @endforeach
        </select>
    </form>
 
    {{-- TABEL RAPOR --}}
    <div style="background: #1e293b; border-radius: 12px; border: 1px solid #334155; overflow: hidden;">
        <div style="padding: 14px 16px; border-bottom: 1px solid #334155; color: #e2e8f0; font-weight: 600;">
            Rapor {{ ucfirst($periode) }} {{ $tahun }}
        </div>
        @if($rapor->count() > 0)
        @foreach($rapor as $r)
        @php $warna = \App\Models\RaporKaryawan::warnaKelas($r->kelas_baru); @endphp
        <div style="padding: 14px 16px; border-bottom: 1px solid #0f172a; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <div style="min-width: 160px;">
                <div style="color: #e2e8f0; font-weight: 600; font-size: 14px;">{{ $r->user->name ?? '-' }}</div>
                <div style="color: #64748b; font-size: 12px;">{{ \App\Models\UjianSoal::namaJabatan($r->user->level ?? 0) }}</div>
            </div>
            <div style="font-size: 16px; font-weight: 800; color: {{ $warna }};">{{ \App\Models\RaporKaryawan::labelKelas($r->kelas_baru) }}</div>
            <div style="text-align: center;">
                <div style="color: #e2e8f0; font-size: 16px; font-weight: 700;">{{ $r->nilai_total }}</div>
                <div style="color: #64748b; font-size: 11px;">Total</div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; min-width: 180px;">
                <div style="text-align: center;">
                    <div style="color: #60a5fa; font-size: 13px; font-weight: 600;">{{ $r->nilai_kpi }}</div>
                    <div style="color: #64748b; font-size: 10px;">KPI</div>
                </div>
                <div style="text-align: center;">
                    <div style="color: #a78bfa; font-size: 13px; font-weight: 600;">{{ $r->nilai_ujian }}</div>
                    <div style="color: #64748b; font-size: 10px;">Ujian</div>
                </div>
                <div style="text-align: center;">
                    <div style="color: #10b981; font-size: 13px; font-weight: 600;">{{ $r->nilai_sp }}</div>
                    <div style="color: #64748b; font-size: 10px;">SP</div>
                </div>
            </div>
            @if($r->kenaikan_gaji > 0)
            <div style="background: rgba(16,185,129,0.1); border: 1px solid #10b981; color: #10b981; font-size: 12px; padding: 4px 10px; border-radius: 6px; white-space: nowrap;">
                +Rp {{ number_format($r->kenaikan_gaji, 0, ',', '.') }}
            </div>
            @endif
            <span style="color: {{ $r->status === 'selesai' ? '#10b981' : '#fbbf24' }}; font-size: 12px; font-weight: 600;">
                {{ $r->status === 'selesai' ? '✅ Selesai' : '⏳ Pending' }}
            </span>
        </div>
        @endforeach
        @else
        <div style="padding: 40px; text-align: center; color: #64748b;">
            Belum ada data rapor periode ini.
        </div>
        @endif
    </div>
 
</div>
@endsection