{{-- FILE: resources/views/penugasan/rencana.blade.php --}}
@extends('layouts.app')

@section('page-title', 'Rencana Besok')

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
@php
    $opsiPorsi = ['penuh' => 'Penuh (1 hari)', 'setengah' => 'Setengah hari'];
    $labelJawab = ['hadir' => 'Hadir', 'tidak_hadir' => 'Tidak hadir'];
@endphp
<div style="max-width:760px;margin:0 auto;">

    @if(session('success'))
        <div class="stat-card" style="padding:12px 16px;margin-bottom:12px;border-left:3px solid #10B981;font-size:13px;">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="stat-card" style="padding:12px 16px;margin-bottom:12px;border-left:3px solid #F59E0B;font-size:13px;">{{ session('warning') }}</div>
    @endif
    @if($errors->any())
        <div class="stat-card" style="padding:12px 16px;margin-bottom:12px;border-left:3px solid #EF4444;font-size:13px;">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <div class="stat-card" style="padding:16px;margin-bottom:16px;">
        <div style="font-size:13px;color:#94a3b8;">Rencana kerja untuk {{ $tanggal->translatedFormat('l, d F Y') }}</div>
        <div style="font-size:12px;color:#64748b;margin-top:4px;">
            Setiap orang maksimal 1 hari kerja (satu tugas penuh, atau dua tugas setengah hari). Pekerja yang diberi tugas dapat pesan Telegram untuk konfirmasi Hadir / Tidak Hadir.
            @if($tersimpan) Terakhir disimpan {{ \Carbon\Carbon::parse($tersimpan->disimpan_at)->translatedFormat('d M H:i') }}. @endif
        </div>
    </div>

    @if(auth()->user()->level == 1)
    <form method="POST" action="{{ route('penugasan.mandor') }}" class="stat-card" style="padding:16px;margin-bottom:16px;display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
        @csrf
        <div style="flex:1;min-width:180px;">
            <label style="font-size:12px;color:#94a3b8;display:block;margin-bottom:4px;">Mandor Bertugas</label>
            <select name="mandor_id" style="width:100%;font-size:16px;padding:8px;">
                <option value="">- belum dipilih -</option>
                @foreach($daftarMandor as $m)
                    <option value="{{ $m->id }}" @selected($m->id == $mandorId)>{{ $m->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary" style="padding:9px 16px;">Simpan Mandor</button>
    </form>
    @endif

    <form method="POST" action="{{ route('penugasan.simpan') }}">
        @csrf
        @foreach($users as $u)
            @php
                $hal = $halangan[$u->id] ?? null;
                $k = $konfirmasi[$u->id] ?? null;
                $adalahMandor = $u->id === $mandorId;
                $baris = $isi[$u->id] ?? [];
            @endphp
            <div class="stat-card" style="padding:14px 16px;margin-bottom:10px;{{ $hal ? 'opacity:.6;' : '' }}">
                <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;align-items:center;">
                    <div style="font-weight:600;font-size:14px;">
                        {{ $u->name }}
                        @if($adalahMandor)<span style="font-size:11px;color:#38bdf8;font-weight:400;">Mandor Bertugas (otomatis hadir)</span>@endif
                    </div>
                    <div style="font-size:12px;">
                        @if($hal === 'Libur')
                            <span style="color:#F59E0B;">Libur — aktifkan Kerja Hari Libur besok pagi bila perlu</span>
                        @elseif($hal)
                            <span style="color:#F59E0B;">{{ $hal }}</span>
                        @elseif($k)
                            <span style="color:{{ $k->jawaban === 'hadir' ? '#10B981' : ($k->jawaban ? '#EF4444' : '#94a3b8') }};">
                                {{ $k->jawaban ? $labelJawab[$k->jawaban] : 'Menunggu jawaban' }}
                            </span>
                        @endif
                    </div>
                </div>

                @if(!$hal)
                    @if(!empty($dariHariIni[$u->id]))
                        <div style="font-size:11px;color:#64748b;margin-top:4px;">Sama seperti hari ini (belum disimpan untuk besok).</div>
                    @endif
                    @if(!$u->telegram_chat_id && !$adalahMandor)
                        <div style="font-size:11px;color:#F59E0B;margin-top:4px;">Belum sambung Telegram — konfirmasikan manual.</div>
                    @endif
                    @for($i = 0; $i < 2; $i++)
                        @php $b = $baris[$i] ?? null; @endphp
                        <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
                            <select name="tugas[{{ $u->id }}][{{ $i }}][tahap]" style="flex:2;min-width:200px;font-size:16px;padding:8px;">
                                <option value="">- {{ $i === 0 ? 'tidak ada tugas' : 'tugas ke-2 (opsional)' }} -</option>
                                @foreach($grup as $g)
                                    <optgroup label="{{ $g['label'] }}">
                                        @foreach($g['tahap'] as $t)
                                            <option value="{{ $t->id }}" @selected($b && $b['tahap'] == $t->id)>{{ $t->nama_tahap }} ({{ $t->status === 'sedang' ? 'berjalan' : 'belum mulai' }})</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <select name="tugas[{{ $u->id }}][{{ $i }}][porsi]" style="flex:1;min-width:130px;font-size:16px;padding:8px;">
                                @foreach($opsiPorsi as $v => $l)
                                    <option value="{{ $v }}" @selected($b && $b['porsi'] === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endfor
                    @if($adalahMandor && auth()->id() === $mandorId)
                        <label style="display:block;margin-top:10px;font-size:13px;">
                            <input type="checkbox" name="mandor_tidak_masuk" value="1" @checked($k && $k->jawaban === 'tidak_hadir')>
                            Saya tidak masuk besok
                        </label>
                    @endif
                @endif
            </div>
        @endforeach

        <button type="submit" class="btn-primary" style="width:100%;padding:12px;margin-top:6px;">Simpan Rencana &amp; Kirim Konfirmasi</button>
    </form>

    {{-- Tandai hadir manual (pekerja belum sambung Telegram / tidak bisa dihubungi). Form terpisah, tak boleh bersarang. --}}
    @php $menunggu = $users->filter(fn($u) => $u->id !== $mandorId && ($konfirmasi[$u->id] ?? null) && !$konfirmasi[$u->id]->jawaban); @endphp
    @if($menunggu->isNotEmpty())
        <div class="stat-card" style="padding:14px 16px;margin-top:16px;">
            <div style="font-size:13px;color:#94a3b8;margin-bottom:8px;">Menunggu jawaban — tandai hadir manual bila sudah dikonfirmasi lewat cara lain:</div>
            @foreach($menunggu as $u)
                <form method="POST" action="{{ route('penugasan.hadir', $u->id) }}" style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;font-size:13px;">
                    @csrf
                    <span>{{ $u->name }} @if(!$u->telegram_chat_id)<span style="color:#F59E0B;font-size:11px;">(belum sambung Telegram)</span>@endif</span>
                    <button type="submit" class="btn-primary" style="padding:5px 12px;font-size:12px;">Tandai Hadir</button>
                </form>
            @endforeach
        </div>
    @endif
</div>
@endsection
