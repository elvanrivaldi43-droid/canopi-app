{{-- FILE: resources/views/absensi/rekap.blade.php --}}
@extends('layouts.app')

@section('page-title', 'Rekap Absensi')

@section('sidebar-menu')
    @include('partials.sidebar-owner')
@endsection

@section('bottom-nav')
    @include('partials.bottomnav-owner')
@endsection

@section('content')
<div style="max-width:900px;margin:0 auto;">

    @if(session('success'))
    <div style="padding:14px;border-radius:10px;background:rgba(16,185,129,0.15);border:1px solid #10b981;color:#6ee7b7;margin-bottom:16px;font-size:13px;">✅ {{ session('success') }}</div>
    @endif

    {{-- Filter --}}
    <div class="stat-card" style="margin-bottom:16px;padding:16px;">
        <form method="GET" action="{{ route('absensi.rekap') }}" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input type="date" name="tanggal" value="{{ $tanggal }}"
                style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:8px 12px;font-size:13px;">
            <select name="level" style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:8px 12px;font-size:13px;">
                <option value="0" {{ $levelFilter==0?'selected':'' }}>Semua Level</option>
                <option value="2" {{ $levelFilter==2?'selected':'' }}>Admin</option>
                <option value="3" {{ $levelFilter==3?'selected':'' }}>Supervisor</option>
                <option value="4" {{ $levelFilter==4?'selected':'' }}>Marketing</option>
                <option value="5" {{ $levelFilter==5?'selected':'' }}>Teknisi</option>
                <option value="6" {{ $levelFilter==6?'selected':'' }}>Driver</option>
                <option value="7" {{ $levelFilter==7?'selected':'' }}>Toko</option>
            </select>
            <button type="submit" style="background:#fbbf24;color:#0f172a;border:none;border-radius:8px;padding:8px 16px;font-weight:600;font-size:13px;">
                🔍 Tampilkan
            </button>
        </form>
    </div>

    {{-- Statistik Hari Ini --}}
    @php
        $totalKaryawan = $karyawan->count();
        $sudahMasuk    = $karyawan->filter(fn($k) => $k->absensi->first()?->jam_masuk)->count();
        $belumMasuk    = $karyawan->filter(fn($k) => !$k->absensi->first()?->jam_masuk && !in_array($k->absensi->first()?->status, ['sakit','izin','cuti','dinas_luar']))->count();
        $izin          = $karyawan->filter(fn($k) => in_array($k->absensi->first()?->status, ['sakit','izin','cuti','dinas_luar']))->count();
        $alpha         = $karyawan->filter(fn($k) => $k->absensi->first()?->status === 'alpha')->count();
        $belumPulang = $karyawan->filter(fn($k) => 
    $k->absensi->first()?->jam_masuk && 
    !$k->absensi->first()?->jam_pulang &&
    $k->absensi->first()?->status !== 'alpha'
)->count();
    @endphp

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;">
        <div class="stat-card" style="padding:14px;text-align:center;">
            <div style="font-size:22px;font-weight:700;color:#10b981;">{{ $sudahMasuk }}</div>
            <div style="font-size:11px;color:#64748b;">✅ Sudah Masuk</div>
        </div>
        <div class="stat-card" style="padding:14px;text-align:center;">
            <div style="font-size:22px;font-weight:700;color:#ef4444;">{{ $alpha }}</div>
            <div style="font-size:11px;color:#64748b;">❌ Alpha</div>
        </div>
        <div class="stat-card" style="padding:14px;text-align:center;">
            <div style="font-size:22px;font-weight:700;color:#06b6d4;">{{ $izin }}</div>
            <div style="font-size:11px;color:#64748b;">📋 Izin/Sakit</div>
        </div>
        <div class="stat-card" style="padding:14px;text-align:center;">
            <div style="font-size:22px;font-weight:700;color:#f59e0b;">{{ $belumPulang }}</div>
            <div style="font-size:11px;color:#64748b;">⏳ Belum Pulang</div>
        </div>
        <div class="stat-card" style="padding:14px;text-align:center;">
            <div style="font-size:22px;font-weight:700;color:#64748b;">{{ $belumMasuk }}</div>
            <div style="font-size:11px;color:#64748b;">❓ Belum Absen</div>
        </div>
        <div class="stat-card" style="padding:14px;text-align:center;">
            <div style="font-size:22px;font-weight:700;color:#fbbf24;">{{ $totalKaryawan }}</div>
            <div style="font-size:11px;color:#64748b;">👥 Total</div>
        </div>
    </div>

    {{-- Tabel Rekap --}}
    <div class="stat-card" style="padding:0;overflow:hidden;">
        <div style="padding:14px 16px;border-bottom:1px solid #334155;">
            <div style="font-size:13px;font-weight:600;color:#94a3b8;">
                📋 Detail Absensi — {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}
            </div>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid #334155;">
                        <th style="padding:10px 16px;text-align:left;font-size:11px;color:#64748b;">Karyawan</th>
                        <th style="padding:10px 8px;text-align:center;font-size:11px;color:#64748b;">Masuk</th>
                        <th style="padding:10px 8px;text-align:center;font-size:11px;color:#64748b;">Siang</th>
                        <th style="padding:10px 8px;text-align:center;font-size:11px;color:#64748b;">Pulang</th>
                        <th style="padding:10px 8px;text-align:center;font-size:11px;color:#64748b;">Status</th>
                        <th style="padding:10px 8px;text-align:center;font-size:11px;color:#64748b;">GPS</th>
                        <th style="padding:10px 8px;text-align:center;font-size:11px;color:#64748b;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($karyawan as $k)
                    @php
                        $absen = $k->absensi->first();
                        $levelColors = [2=>'#06b6d4',3=>'#8b5cf6',4=>'#f59e0b',5=>'#10b981',6=>'#3b82f6',7=>'#ec4899'];
                        $lc = $levelColors[$k->level] ?? '#64748b';
                        $statusColors = ['hadir'=>'#10b981','telat'=>'#f59e0b','setengah_hari'=>'#8b5cf6','alpha'=>'#ef4444','sakit'=>'#06b6d4','izin'=>'#6366f1','cuti'=>'#06b6d4','dinas_luar'=>'#94a3b8'];
                        $sc = $statusColors[$absen?->status ?? ''] ?? '#64748b';
                    @endphp
                    <tr style="border-bottom:1px solid #1e293b;">
                        <td style="padding:12px 16px;">
                            <div style="font-size:13px;font-weight:600;color:#f1f5f9;">{{ $k->name }}</div>
                            <div style="display:flex;gap:6px;align-items:center;margin-top:2px;">
                                <span style="font-size:10px;padding:2px 6px;border-radius:10px;background:{{ $lc }}20;color:{{ $lc }};">{{ $k->namaLevel() }}</span>
                                <span style="font-size:11px;color:#64748b;">{{ $k->jabatan }}</span>
                            </div>
                        </td>
                        <td style="padding:12px 8px;text-align:center;font-size:13px;color:{{ $absen?->jam_masuk ? '#10b981' : '#475569' }};">
                            {{ $absen?->jam_masuk ? substr($absen->jam_masuk,0,5) : '—' }}
                        </td>
                        <td style="padding:12px 8px;text-align:center;font-size:13px;color:{{ $absen?->jam_absen_siang ? '#f59e0b' : '#475569' }};">
                            {{ $absen?->jam_absen_siang ? substr($absen->jam_absen_siang,0,5) : '—' }}
                        </td>
                        <td style="padding:12px 8px;text-align:center;font-size:13px;color:{{ $absen?->jam_pulang ? '#3b82f6' : '#475569' }};">
                            {{ $absen?->jam_pulang ? substr($absen->jam_pulang,0,5) : '—' }}
                        </td>
                        <td style="padding:12px 8px;text-align:center;">
                            @if($absen)
                            <span style="font-size:10px;padding:3px 8px;border-radius:20px;background:{{ $sc }}20;color:{{ $sc }};border:1px solid {{ $sc }}40;">
                                {{ $absen->statusLabel() }}
                            </span>
                            @if($absen->dikoreksi ?? false)
                            <div style="font-size:10px;color:#94a3b8;margin-top:2px;">✏️ dikoreksi</div>
                            @endif
                            @else
                            <span style="font-size:10px;color:#475569;">Belum absen</span>
                            @endif
                        </td>
                        <td style="padding:12px 8px;text-align:center;font-size:14px;">
                            @if(!$absen || !$absen->jam_masuk)
                                <span style="color:#475569;">—</span>
                            @elseif($absen->gps_valid_masuk)
                                <span title="GPS valid">✅</span>
                            @else
                                <span title="GPS tidak valid">⚠️</span>
                            @endif
                        </td>
                        <td style="padding:12px 8px;text-align:center;">
                            <button onclick="bukaKoreksi({{ $k->id }}, '{{ $k->name }}', '{{ $absen?->id }}', '{{ $absen?->jam_masuk ? substr($absen->jam_masuk,0,5) : '' }}', '{{ $absen?->jam_pulang ? substr($absen->jam_pulang,0,5) : '' }}', '{{ $absen?->status ?? '' }}')"
                                style="font-size:11px;background:#334155;color:#e2e8f0;border:none;border-radius:6px;padding:4px 10px;cursor:pointer;">
                                ✏️ Koreksi
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- Modal Koreksi Absen --}}
<div id="modalKoreksi" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;width:100%;max-width:420px;">
        <div style="font-weight:700;color:#fbbf24;font-size:15px;margin-bottom:4px;">✏️ Koreksi Absen</div>
        <div style="color:#64748b;font-size:13px;margin-bottom:16px;" id="namaKoreksi"></div>

        <form method="POST" id="formKoreksi">
            @csrf
            @method('POST')

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                <div>
                    <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Jam Masuk</label>
                    <input type="time" name="jam_masuk" id="inputJamMasuk"
                        style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:10px;width:100%;font-size:13px;">
                </div>
                <div>
                    <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Jam Pulang</label>
                    <input type="time" name="jam_pulang" id="inputJamPulang"
                        style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:10px;width:100%;font-size:13px;">
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Status</label>
                <select name="status" id="inputStatus"
                    style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:10px;width:100%;font-size:13px;">
                    <option value="hadir">✅ Hadir</option>
                    <option value="telat">⏰ Telat</option>
                    <option value="setengah_hari">🌗 Setengah Hari</option>
                    <option value="sakit">🏥 Sakit</option>
                    <option value="izin">📋 Izin</option>
                    <option value="dinas_luar">🚗 Dinas Luar</option>
                    <option value="alpha">❌ Alpha</option>
                </select>
            </div>

            <div style="margin-bottom:16px;">
                <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Alasan Koreksi <span style="color:#ef4444;">*</span></label>
                <textarea name="alasan" rows="3" required placeholder="Jelaskan alasan koreksi..."
                    style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:10px;width:100%;font-size:13px;resize:none;"></textarea>
            </div>

            <div style="display:flex;gap:8px;">
                <button type="submit" style="flex:1;background:#fbbf24;color:#0f172a;border:none;border-radius:8px;padding:10px;font-weight:700;cursor:pointer;">
                    💾 Simpan Koreksi
                </button>
                <button type="button" onclick="tutupModal()"
                    style="flex:1;background:#334155;color:#e2e8f0;border:none;border-radius:8px;padding:10px;font-weight:600;cursor:pointer;">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaKoreksi(userId, nama, absenId, jamMasuk, jamPulang, status) {
    document.getElementById('namaKoreksi').textContent = nama;
    document.getElementById('inputJamMasuk').value = jamMasuk;
    document.getElementById('inputJamPulang').value = jamPulang;
    document.getElementById('inputStatus').value = status || 'hadir';

    // Set action — kalau belum ada absen, kirim ke route buat baru
    const action = absenId
        ? `/absensi/${absenId}/koreksi`
        : `/absensi/koreksi-baru/${userId}`;
    document.getElementById('formKoreksi').action = action;
    document.getElementById('modalKoreksi').style.display = 'flex';
}

function tutupModal() {
    document.getElementById('modalKoreksi').style.display = 'none';
}
</script>
@endsection