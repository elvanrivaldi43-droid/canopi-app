{{-- FILE: resources/views/absensi/kode-hari-ini.blade.php --}}
@extends('layouts.app')

@section('page-title', 'Kode Absen Hari Ini')

@section('sidebar-menu')
    @if(auth()->user()->level == 1)
        @include('partials.sidebar-owner')
    @else
        @include('partials.sidebar-pipeline')
    @endif
@endsection

@section('bottom-nav')
    @include('partials.bottomnav')
@endsection

@section('content')
<div style="max-width:700px;margin:0 auto;">

    <div class="stat-card" style="padding:16px;margin-bottom:16px;">
        <div style="font-size:13px;color:#94a3b8;">{{ $tanggal->translatedFormat('l, d F Y') }}</div>
        <div style="font-size:12px;color:#64748b;margin-top:4px;">Kode ini cuma bisa dipakai oleh karyawan yang bersangkutan. Yang belum "Sudah terhubung" perlu dikasih tahu manual.</div>
        <div style="font-size:12px;color:#64748b;margin-top:6px;">Karyawan yang hari ini libur tidak dapat kode. Kalau mendadak diminta masuk, tekan "Aktifkan Masuk Hari Ini" — hari itu berubah jadi hari kerja biasa (jatah libur hari itu terpakai, tidak ada hari pengganti) dan dibayar 1x gaji harian + uang makan.</div>
    </div>

    <div class="stat-card" style="padding:0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#0f172a;">
                    <th style="padding:10px 14px;text-align:left;color:#94a3b8;">Nama</th>
                    <th style="padding:10px 14px;text-align:left;color:#94a3b8;">Kode</th>
                    <th style="padding:10px 14px;text-align:left;color:#94a3b8;">Telegram</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $d)
                <tr style="border-top:1px solid #1e293b;">
                    <td style="padding:10px 14px;">
                        {{ $d['nama'] }}
                        <div style="font-size:11px;color:#64748b;">{{ $d['jabatan'] }}</div>
                        @if($d['kerja_libur'] && $d['diaktifkan_oleh'])
                        <div style="font-size:11px;color:#64748b;">Diaktifkan oleh {{ $d['diaktifkan_oleh'] }}</div>
                        @endif
                    </td>
                    <td style="padding:10px 14px;font-family:monospace;font-weight:700;letter-spacing:1px;">
                        {{-- Sakit/izin/cuti/dinas luar (atau ajuan yang masih berjalan):
                             tidak ada kode dan TIDAK ada tombol. Dua tombol di bawah
                             sama-sama ditolak server untuk keadaan ini, jadi kalau tetap
                             ditampilkan dia cuma jadi jebakan klik. --}}
                        @if($d['izin'])
                            <div style="font-family:'Segoe UI',sans-serif;font-weight:600;letter-spacing:0;color:#94a3b8;font-size:12px;display:inline-flex;align-items:center;gap:4px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5A3.375 3.375 0 0010.125 2.25H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                {{ $d['izin'] }}
                            </div>
                            @if($d['kode'])
                                {{-- Kodenya sudah terlanjur ada (mis. diaktifkan lebih dulu,
                                     izinnya masuk belakangan). Ditampilkan apa adanya —
                                     menyembunyikannya cuma bikin Owner mengira ada yang error. --}}
                                <div style="font-family:'Segoe UI',sans-serif;font-weight:400;letter-spacing:0;font-size:11px;color:#64748b;margin-top:4px;">Kode terlanjur dibuat hari ini: <span style="font-family:monospace;font-weight:700;">{{ $d['kode'] }}</span></div>
                            @else
                                <div style="font-family:'Segoe UI',sans-serif;font-weight:400;letter-spacing:0;font-size:11px;color:#64748b;margin-top:4px;">Tidak dibuatkan kode absen hari ini.</div>
                            @endif
                        @elseif($d['libur'] && !$d['kerja_libur'])
                            <div style="font-family:'Segoe UI',sans-serif;font-weight:600;letter-spacing:0;color:#94a3b8;font-size:12px;display:inline-flex;align-items:center;gap:4px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                                Libur
                            </div>
                            {{-- Tombol hanya untuk target yang memang boleh diaktifkan aktor
                                 ini (Mandor: level 3-7, bukan dirinya sendiri). Sisanya cuma
                                 label "Libur" — servernya toh menolak, jadi jangan dipancing. --}}
                            @if($d['boleh_aktivasi'])
                            <form method="POST" action="{{ route('absensi.kerja-hari-libur', $d['id']) }}"
                                  {{-- Nama karyawan sengaja TIDAK diselipkan ke string JS: apostrof di nama
                                       (mis. "Ma'ruf") bikin confirm() error dan form ke-submit tanpa konfirmasi. --}}
                                  onsubmit="return confirm('Aktifkan karyawan ini untuk masuk hari ini? Hari ini jadi hari kerja biasa buat dia — jatah liburnya hari ini terpakai (tidak ada hari pengganti) dan dia dibayar 1x gaji harian + uang makan.');"
                                  style="margin-top:6px;">
                                @csrf
                                <button type="submit" style="font-family:'Segoe UI',sans-serif;font-weight:600;letter-spacing:0;font-size:11px;padding:5px 10px;border-radius:6px;border:1px solid #334155;background:#1e293b;color:#e2e8f0;cursor:pointer;">
                                    Aktifkan Masuk Hari Ini
                                </button>
                            </form>
                            @endif
                        @else
                            {{ $d['kode'] ?? '—' }}
                            @if($d['kerja_libur'])
                            <div style="font-family:'Segoe UI',sans-serif;font-weight:600;letter-spacing:0;color:#fbbf24;font-size:11px;margin-top:4px;">Masuk Hari Libur</div>
                            @endif

                            {{-- Belum punya kode hari ini. Halaman ini BACA SAJA (membukanya
                                 tidak lagi membuat kode diam-diam — dulu itu bikin cron pagi
                                 melewati semua karyawan tanpa mengirim Telegram). Jadi
                                 pembuatannya lewat tombol yang ditekan sengaja. --}}
                            @if(!$d['kode'])
                            <form method="POST" action="{{ route('absensi.kirim-kode', $d['id']) }}"
                                  onsubmit="return confirm('Buatkan kode absen hari ini untuk karyawan ini dan kirim ke Telegram-nya?');"
                                  style="margin-top:6px;">
                                @csrf
                                <button type="submit" style="font-family:'Segoe UI',sans-serif;font-weight:600;letter-spacing:0;font-size:11px;padding:5px 10px;border-radius:6px;border:1px solid #334155;background:#1e293b;color:#e2e8f0;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                                    Buat &amp; Kirim Kode
                                </button>
                            </form>
                            @endif
                        @endif
                    </td>
                    <td style="padding:10px 14px;">
                        @if($d['connected'])
                            <span style="display:inline-flex;align-items:center;gap:4px;color:#34d399;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                Sudah terhubung
                            </span>
                        @else
                            <span style="display:inline-flex;align-items:center;gap:4px;color:#f87171;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                Belum connect
                            </span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
@endsection
