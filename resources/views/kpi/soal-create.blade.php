@extends('layouts.app')
@section('content')
<div style="padding: 16px; max-width: 700px; margin: 0 auto;">
 
    <a href="{{ route('kpi.soal.index') }}" style="color: #94a3b8; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 16px;">← Kembali</a>
 
    <h1 style="color: #fbbf24; font-size: 18px; font-weight: 700; margin-bottom: 20px;">+ Tambah Soal Ujian</h1>
 
    <form method="POST" action="{{ route('kpi.soal.store') }}">
        @csrf
 
        <div style="background: #1e293b; border-radius: 12px; padding: 18px; border: 1px solid #334155; margin-bottom: 16px;">
 
            <div style="margin-bottom: 14px;">
                <label style="color: #94a3b8; font-size: 13px; display: block; margin-bottom: 6px;">Jabatan</label>
                <select name="jabatan_level" required style="width: 100%; background: #0f172a; color: #e2e8f0; border: 1px solid #334155; padding: 10px 12px; border-radius: 8px; font-size: 14px;">
                    @foreach($levels as $lv => $nm)
                    <option value="{{ $lv }}" {{ old('jabatan_level') == $lv ? 'selected' : '' }}>{{ $nm }}</option>
                    @endforeach
                </select>
            </div>
 
            <div style="margin-bottom: 14px;">
                <label style="color: #94a3b8; font-size: 13px; display: block; margin-bottom: 6px;">Pertanyaan</label>
                <textarea name="pertanyaan" required rows="3" style="width: 100%; background: #0f172a; color: #e2e8f0; border: 1px solid #334155; padding: 10px 12px; border-radius: 8px; font-size: 14px; resize: vertical; box-sizing: border-box;">{{ old('pertanyaan') }}</textarea>
            </div>
 
            @foreach(['a', 'b', 'c', 'd'] as $huruf)
            <div style="margin-bottom: 12px;">
                <label style="color: #94a3b8; font-size: 13px; display: block; margin-bottom: 6px;">Pilihan {{ strtoupper($huruf) }}</label>
                <input type="text" name="pilihan_{{ $huruf }}" value="{{ old('pilihan_' . $huruf) }}" required
                    style="width: 100%; background: #0f172a; color: #e2e8f0; border: 1px solid #334155; padding: 10px 12px; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
            </div>
            @endforeach
 
            <div style="margin-bottom: 14px;">
                <label style="color: #94a3b8; font-size: 13px; display: block; margin-bottom: 6px;">Jawaban Benar</label>
                <select name="jawaban_benar" required style="width: 100%; background: #0f172a; color: #e2e8f0; border: 1px solid #334155; padding: 10px 12px; border-radius: 8px; font-size: 14px;">
                    @foreach(['a', 'b', 'c', 'd'] as $huruf)
                    <option value="{{ $huruf }}" {{ old('jawaban_benar') === $huruf ? 'selected' : '' }}>{{ strtoupper($huruf) }}</option>
                    @endforeach
                </select>
            </div>
 
        </div>
 
        <button type="submit" style="background: #fbbf24; color: #0f172a; border: none; padding: 14px 24px; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; width: 100%;">
            Simpan Soal
        </button>
    </form>
 
</div>
@endsection