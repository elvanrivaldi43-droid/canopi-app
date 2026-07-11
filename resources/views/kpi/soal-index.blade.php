@extends('layouts.app')
@section('content')
<div style="padding: 16px; max-width: 800px; margin: 0 auto;">
 
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <h1 style="color: #fbbf24; font-size: 18px; font-weight: 700; margin: 0;">📝 Bank Soal Ujian</h1>
        <a href="{{ route('kpi.soal.create') }}" style="background: #fbbf24; color: #0f172a; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 700;">+ Tambah Soal</a>
    </div>
 
    @if(session('success'))
    <div style="background: #064e3b; border: 1px solid #10b981; color: #6ee7b7; padding: 12px 16px; border-radius: 8px; margin-bottom: 14px; font-size: 14px;">✅ {{ session('success') }}</div>
    @endif
 
    {{-- FILTER JABATAN --}}
    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px;">
        @foreach($levels as $lv => $nm)
        <a href="{{ route('kpi.soal.index', ['level' => $lv]) }}"
           style="padding: 8px 14px; border-radius: 8px; font-size: 13px; text-decoration: none; border: 1px solid {{ $level == $lv ? '#fbbf24' : '#334155' }}; background: {{ $level == $lv ? 'rgba(251,191,36,0.1)' : '#1e293b' }}; color: {{ $level == $lv ? '#fbbf24' : '#94a3b8' }};">
            {{ $nm }}
        </a>
        @endforeach
    </div>
 
    {{-- STATS --}}
    <div style="background: #1e293b; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; border: 1px solid #334155; color: #94a3b8; font-size: 13px;">
        Total soal aktif: <strong style="color: #e2e8f0;">{{ $soal->where('is_aktif', 1)->count() }}</strong> dari {{ $soal->total() }} soal · Perlu minimal 20 soal aktif untuk ujian bisa dijalankan.
        @if($soal->where('is_aktif', 1)->count() >= 20)
        <span style="color: #10b981;">✅ Cukup</span>
        @else
        <span style="color: #ef4444;">⚠️ Kurang {{ 20 - $soal->where('is_aktif', 1)->count() }} soal lagi</span>
        @endif
    </div>
 
    {{-- DAFTAR SOAL --}}
    @foreach($soal as $index => $s)
    <div style="background: #1e293b; border-radius: 10px; padding: 14px; margin-bottom: 10px; border: 1px solid {{ $s->is_aktif ? '#334155' : '#1e293b' }}; opacity: {{ $s->is_aktif ? '1' : '0.5' }};">
        <div style="display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <div style="color: #64748b; font-size: 11px; margin-bottom: 4px;">Soal #{{ ($soal->currentPage() - 1) * 20 + $loop->iteration }}</div>
                <div style="color: #e2e8f0; font-size: 14px; line-height: 1.5;">{{ $s->pertanyaan }}</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px; margin-top: 10px;">
                    @foreach(['a', 'b', 'c', 'd'] as $huruf)
                    <div style="font-size: 12px; color: {{ $s->jawaban_benar === $huruf ? '#10b981' : '#64748b' }}; font-weight: {{ $s->jawaban_benar === $huruf ? '700' : '400' }};">
                        {{ strtoupper($huruf) }}. {{ $s->{'pilihan_' . $huruf} }}
                        {{ $s->jawaban_benar === $huruf ? ' ✓' : '' }}
                    </div>
                    @endforeach
                </div>
            </div>
            <div style="display: flex; flex-direction: column; gap: 6px; align-items: flex-end;">
                <a href="{{ route('kpi.soal.edit', $s->id) }}" style="background: #0f172a; color: #94a3b8; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; border: 1px solid #334155;">Edit</a>
                <form method="POST" action="{{ route('kpi.soal.hapus', $s->id) }}">
                    @csrf
                    <button type="submit" onclick="return confirm('Hapus soal ini?')" style="background: transparent; color: #ef4444; border: 1px solid #ef4444; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">Hapus</button>
                </form>
                @if(!$s->is_aktif)
                <span style="color: #64748b; font-size: 11px;">Nonaktif</span>
                @endif
            </div>
        </div>
    </div>
    @endforeach
 
    {{ $soal->appends(['level' => $level])->links() }}
 
</div>
@endsection