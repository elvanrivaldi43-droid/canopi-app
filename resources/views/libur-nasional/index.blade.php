{{-- FILE: resources/views/libur-nasional/index.blade.php --}}
@extends('layouts.app')

@section('page-title', 'Libur Nasional')

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

@php
    $isOwner = auth()->user()->level == 1;

    // Tanggal -> nama libur (buat highlight + cari libur_nasional_id pas diklik)
    $liburPerTanggal = [];
    foreach ($liburBulanIni as $lb) {
        $cur = $lb->tanggal_mulai->copy();
        while ($cur->lte($lb->tanggal_selesai)) {
            $liburPerTanggal[$cur->format('Y-m-d')] = ['id' => $lb->id, 'nama' => $lb->nama];
            $cur->addDay();
        }
    }

    $namaHari = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
    $mulaiMinggu = (clone $awal)->startOfWeek(\Carbon\Carbon::SUNDAY);
@endphp

@section('content')
<div style="max-width:900px;margin:0 auto;">

    @if(session('success'))
    <div style="padding:14px;border-radius:10px;background:rgba(16,185,129,0.15);border:1px solid #10b981;color:#6ee7b7;margin-bottom:16px;font-size:13px;">✅ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="padding:14px;border-radius:10px;background:rgba(239,68,68,0.15);border:1px solid #ef4444;color:#fca5a5;margin-bottom:16px;font-size:13px;">⚠️ {{ session('error') }}</div>
    @endif

    {{-- Navigasi bulan --}}
    <div class="stat-card" style="padding:14px 16px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
        <a href="{{ route('libur-nasional.index', ['bulan'=>$bulan==1?12:$bulan-1,'tahun'=>$bulan==1?$tahun-1:$tahun]) }}" style="color:#94a3b8;text-decoration:none;font-size:18px;">←</a>
        <div style="font-weight:700;color:#fbbf24;font-size:15px;">{{ $awal->translatedFormat('F Y') }}</div>
        <a href="{{ route('libur-nasional.index', ['bulan'=>$bulan==12?1:$bulan+1,'tahun'=>$bulan==12?$tahun+1:$tahun]) }}" style="color:#94a3b8;text-decoration:none;font-size:18px;">→</a>
    </div>

    @if($isOwner)
    <button type="button" onclick="bukaTambahLibur()" style="width:100%;background:#fbbf24;color:#0f172a;border:none;border-radius:10px;padding:12px;font-weight:700;margin-bottom:16px;cursor:pointer;">
        + Tambah Libur Nasional
    </button>
    @endif

    {{-- Kalender grid --}}
    <div class="stat-card" style="padding:12px;margin-bottom:16px;">
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:6px;">
            @foreach($namaHari as $nh)
            <div style="text-align:center;font-size:11px;color:#64748b;font-weight:700;">{{ $nh }}</div>
            @endforeach
        </div>
        <div id="calGrid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;">
            @php $cur = $mulaiMinggu->copy(); @endphp
            @while($cur->lte($akhir->copy()->endOfWeek(\Carbon\Carbon::SATURDAY)))
                @php
                    $tglStr = $cur->format('Y-m-d');
                    $diBulanIni = $cur->month === $bulan;
                    $info = $liburPerTanggal[$tglStr] ?? null;
                    $piketHariItu = $piketBulanIni[$tglStr] ?? collect();
                @endphp
                <div class="cal-day{{ $info ? ' cal-day-libur' : '' }}"
                     data-tanggal="{{ $tglStr }}"
                     data-libur-id="{{ $info['id'] ?? '' }}"
                     data-libur-nama="{{ $info['nama'] ?? '' }}"
                     style="min-height:52px;border-radius:8px;padding:6px;font-size:11px;cursor:{{ $isOwner ? 'pointer' : 'default' }};
                        opacity:{{ $diBulanIni ? 1 : 0.3 }};
                        background:{{ $info ? 'rgba(251,191,36,0.15)' : '#0f172a' }};
                        border:1px solid {{ $info ? '#fbbf24' : '#334155' }};"
                     @if($isOwner) onclick="klikTanggal(this)" @endif>
                    <div style="font-weight:700;color:{{ $info ? '#fbbf24' : '#e2e8f0' }};">{{ $cur->day }}</div>
                    @if($info)
                    <div style="color:#fbbf24;font-size:9px;line-height:1.2;margin-top:2px;">{{ \Illuminate\Support\Str::limit($info['nama'], 14) }}</div>
                    @endif
                    @if($piketHariItu->count())
                    <div style="color:#38bdf8;font-size:9px;margin-top:2px;">📌 {{ $piketHariItu->count() }} piket</div>
                    @endif
                </div>
                @php $cur->addDay(); @endphp
            @endwhile
        </div>
    </div>

    {{-- Daftar semua libur nasional (manajemen, tidak terikat bulan yang ditampilkan) --}}
    <div class="stat-card" style="padding:16px;margin-bottom:80px;">
        <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">📋 Semua Libur Nasional</div>
        @forelse($liburSemua as $lb)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #1e293b;">
            <div>
                <div style="font-size:13px;font-weight:600;color:#f1f5f9;">{{ $lb->nama }}</div>
                <div style="font-size:11px;color:#64748b;">{{ $lb->labelRentang() }}</div>
            </div>
            @if($isOwner)
            <form method="POST" action="{{ route('libur-nasional.destroy', $lb) }}" data-nama="{{ $lb->nama }}" onsubmit="return confirm('Hapus libur nasional \'' + this.dataset.nama + '\'? Semua data piket di dalamnya ikut terhapus.');">
                @csrf
                @method('DELETE')
                <button type="submit" style="background:transparent;border:1px solid #ef4444;color:#ef4444;border-radius:6px;padding:4px 10px;font-size:11px;cursor:pointer;">Hapus</button>
            </form>
            @endif
        </div>
        @empty
        <div style="color:#64748b;font-size:13px;text-align:center;padding:20px;">Belum ada libur nasional yang ditambahkan.</div>
        @endforelse
    </div>

</div>

@if($isOwner)
{{-- Modal Tambah Libur Nasional --}}
<div id="modalTambah" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;width:100%;max-width:400px;">
        <div style="font-weight:700;color:#fbbf24;font-size:15px;margin-bottom:16px;">+ Tambah Libur Nasional</div>
        <form method="POST" action="{{ route('libur-nasional.store') }}">
            @csrf
            <div style="margin-bottom:12px;">
                <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Nama Libur</label>
                <input type="text" name="nama" required placeholder="mis. Lebaran 2026"
                    style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:10px;width:100%;font-size:13px;">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
                <div>
                    <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Tanggal Mulai</label>
                    <input type="date" name="tanggal_mulai" id="inputMulai" required
                        style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:10px;width:100%;font-size:13px;">
                </div>
                <div>
                    <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Tanggal Selesai</label>
                    <input type="date" name="tanggal_selesai" id="inputSelesai" required
                        style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:10px;width:100%;font-size:13px;">
                </div>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" style="flex:1;background:#fbbf24;color:#0f172a;border:none;border-radius:8px;padding:10px;font-weight:700;cursor:pointer;">💾 Simpan</button>
                <button type="button" onclick="tutupModal('modalTambah')" style="flex:1;background:#334155;color:#e2e8f0;border:none;border-radius:8px;padding:10px;font-weight:600;cursor:pointer;">Batal</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Kelola Piket --}}
<div id="modalPiket" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;width:100%;max-width:400px;">
        <div style="font-weight:700;color:#fbbf24;font-size:15px;margin-bottom:4px;">📌 Kelola Piket</div>
        <div id="piketTanggalLabel" style="color:#64748b;font-size:13px;margin-bottom:16px;"></div>

        <div id="piketListExisting" style="margin-bottom:16px;"></div>

        <form id="formPiketTambah" method="POST">
            @csrf
            <input type="hidden" name="tanggal" id="piketInputTanggal">
            <div style="margin-bottom:12px;">
                <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Tunjuk Karyawan Piket</label>
                <select name="user_id" required style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:10px;width:100%;font-size:13px;">
                    <option value="">-- Pilih Karyawan --</option>
                    @foreach($karyawan as $k)
                    <option value="{{ $k->id }}">{{ $k->name }} ({{ $k->jabatan }})</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" style="flex:1;background:#38bdf8;color:#0f172a;border:none;border-radius:8px;padding:10px;font-weight:700;cursor:pointer;">+ Tambah Piket</button>
                <button type="button" onclick="tutupModal('modalPiket')" style="flex:1;background:#334155;color:#e2e8f0;border:none;border-radius:8px;padding:10px;font-weight:600;cursor:pointer;">Tutup</button>
            </div>
        </form>
    </div>
</div>

@php
    // Ekspresi ini SENGAJA dipindah ke variable dulu: @json() Blade parse argumennya
    // dengan explode(','), jadi ekspresi yang mengandung koma (kayak closure array di
    // bawah ini) bikin flag JSON_HEX_* salah baca. Variable tunggal = argumen tunggal.
    $piketJs = $piketBulanIni->map(fn($list) => $list->map(fn($p) => ['id' => $p->id, 'nama' => $p->user->name]))->all();
@endphp
<script>
// Modal position:fixed harus di-reparent ke <body>, bukan bersarang di .page-content
// (.page-content overflow-y:auto + -webkit-overflow-scrolling:touch bikin fixed rusak
// di iOS Safari — sudah kejadian nyata di DenahEditor, lihat CLAUDE.md).
['modalTambah', 'modalPiket'].forEach(id => {
    const el = document.getElementById(id);
    if (el) document.body.appendChild(el);
});

// Data piket per tanggal dikirim dari server (dipakai isi modal tanpa reload)
const PIKET_DATA = @json($piketJs);

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

let modeTambah = false;
let tglMulaiPilih = null;

function bukaTambahLibur() {
    modeTambah = true;
    tglMulaiPilih = null;
    document.querySelectorAll('.cal-day').forEach(el => el.style.outline = '2px dashed #38bdf8');
    alert('Mode tambah aktif: klik tanggal MULAI, lalu klik tanggal SELESAI di kalender.');
}

function klikTanggal(el) {
    const tgl = el.dataset.tanggal;

    if (modeTambah) {
        if (!tglMulaiPilih) {
            tglMulaiPilih = tgl;
            el.style.background = 'rgba(56,189,248,0.3)';
            return;
        }
        let mulai = tglMulaiPilih, selesai = tgl;
        if (selesai < mulai) { [mulai, selesai] = [selesai, mulai]; }

        document.getElementById('inputMulai').value = mulai;
        document.getElementById('inputSelesai').value = selesai;
        document.querySelectorAll('.cal-day').forEach(e => e.style.outline = '');
        modeTambah = false;
        tglMulaiPilih = null;
        document.getElementById('modalTambah').style.display = 'flex';
        return;
    }

    // Bukan mode tambah: kalau tanggal itu sudah termasuk libur nasional -> buka kelola piket
    const liburId = el.dataset.liburId;
    if (!liburId) return;

    document.getElementById('piketTanggalLabel').textContent = el.dataset.liburNama + ' — ' + tgl;
    document.getElementById('piketInputTanggal').value = tgl;
    document.getElementById('formPiketTambah').action = `/libur-nasional/${liburId}/piket`;

    const existing = PIKET_DATA[tgl] || [];
    const listEl = document.getElementById('piketListExisting');
    if (existing.length === 0) {
        listEl.innerHTML = '<div style="color:#64748b;font-size:12px;">Belum ada yang piket tanggal ini.</div>';
    } else {
        listEl.innerHTML = existing.map(p => `
            <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #334155;">
                <span style="font-size:13px;color:#f1f5f9;">${escapeHtml(p.nama)}</span>
                <form method="POST" action="/libur-nasional/piket/${p.id}" style="display:inline;">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" style="background:transparent;border:1px solid #ef4444;color:#ef4444;border-radius:6px;padding:2px 8px;font-size:11px;cursor:pointer;">Batal</button>
                </form>
            </div>
        `).join('');
    }

    document.getElementById('modalPiket').style.display = 'flex';
}

function tutupModal(id) {
    document.getElementById(id).style.display = 'none';
}
</script>
@endif
@endsection
