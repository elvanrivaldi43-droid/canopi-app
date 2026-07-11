{{-- FILE: resources/views/penggajian/kasbon.blade.php --}}
@extends('layouts.app')

@section('page-title', 'Kasbon')

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
<div style="max-width:800px;margin:0 auto;">

    @if(session('success'))
    <div style="padding:14px;border-radius:10px;background:rgba(16,185,129,0.15);border:1px solid #10b981;color:#6ee7b7;margin-bottom:16px;font-size:13px;">✅ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="padding:14px;border-radius:10px;background:rgba(239,68,68,0.15);border:1px solid #ef4444;color:#fca5a5;margin-bottom:16px;font-size:13px;">⚠️ {{ session('error') }}</div>
    @endif

    {{-- Form Tambah Kasbon --}}
    <div class="stat-card" style="margin-bottom:20px;padding:20px;">
        <div style="font-size:14px;font-weight:700;color:#fbbf24;margin-bottom:16px;">➕ Catat Kasbon Baru</div>
        <form method="POST" action="{{ route('penggajian.kasbon.store') }}">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Karyawan</label>
                    <select name="user_id" required style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:10px;width:100%;font-size:13px;">
                        <option value="">-- Pilih Karyawan --</option>
                        @foreach($karyawan as $k)
                        <option value="{{ $k->id }}">{{ $k->name }} ({{ $k->jabatan }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Nominal Kasbon</label>
                    <input type="number" name="nominal" placeholder="Rp" required min="50000"
                        style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:10px;width:100%;font-size:13px;">
                </div>
                <div>
                    <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Jumlah Cicilan (bulan)</label>
                    <input type="number" name="jumlah_cicilan" placeholder="maks 24" required min="1" max="24"
                        style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:10px;width:100%;font-size:13px;">
                </div>
                <div>
                    <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Keterangan</label>
                    <input type="text" name="keterangan" placeholder="Alasan kasbon" required
                        style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:10px;width:100%;font-size:13px;">
                </div>
            </div>
            <button type="submit" style="background:#fbbf24;color:#0f172a;border:none;border-radius:8px;padding:10px 24px;font-weight:700;font-size:13px;cursor:pointer;">
                💾 Simpan Kasbon
            </button>
        </form>
    </div>

    {{-- Daftar Kasbon Aktif --}}
    <div class="stat-card" style="padding:0;overflow:hidden;">
        <div style="padding:14px 16px;border-bottom:1px solid #334155;">
            <div style="font-size:13px;font-weight:600;color:#94a3b8;">💳 Kasbon Aktif</div>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid #334155;">
                        <th style="padding:10px 16px;text-align:left;font-size:11px;color:#64748b;">Karyawan</th>
                        <th style="padding:10px 8px;text-align:right;font-size:11px;color:#64748b;">Nominal</th>
                        <th style="padding:10px 8px;text-align:right;font-size:11px;color:#64748b;">Sisa</th>
                        <th style="padding:10px 8px;text-align:center;font-size:11px;color:#64748b;">Cicilan/Bulan</th>
                        <th style="padding:10px 8px;text-align:center;font-size:11px;color:#64748b;">Progress</th>
                        <th style="padding:10px 8px;text-align:center;font-size:11px;color:#64748b;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @php $adaKasbon = false; @endphp
                    @foreach($karyawan as $k)
                        @foreach($k->kasbon as $kb)
                        @php $adaKasbon = true; @endphp
                        <tr style="border-bottom:1px solid #1e293b;">
                            <td style="padding:12px 16px;">
                                <div style="font-size:13px;font-weight:600;color:#f1f5f9;">{{ $k->name }}</div>
                                <div style="font-size:11px;color:#64748b;">{{ $kb->keterangan }}</div>
                                @if($kb->ditunda_sampai && $kb->status === 'ditunda')
                                <div style="font-size:10px;color:#f59e0b;margin-top:2px;">⏸ Ditunda s/d {{ $kb->ditunda_sampai->format('d/m/Y') }}</div>
                                @endif
                            </td>
                            <td style="padding:12px 8px;text-align:right;font-size:13px;color:#e2e8f0;">
                                Rp {{ number_format($kb->nominal,0,',','.') }}
                            </td>
                            <td style="padding:12px 8px;text-align:right;font-size:13px;color:#ef4444;font-weight:600;">
                                Rp {{ number_format($kb->sisa_kasbon,0,',','.') }}
                            </td>
                            <td style="padding:12px 8px;text-align:center;font-size:13px;color:#fbbf24;">
                                Rp {{ number_format($kb->cicilan_per_bulan,0,',','.') }}
                            </td>
                            <td style="padding:12px 8px;text-align:center;">
                                <div style="font-size:11px;color:#64748b;">{{ $kb->cicilan_ke }}/{{ $kb->jumlah_cicilan }}</div>
                                <div style="background:#334155;border-radius:4px;height:6px;margin-top:4px;overflow:hidden;">
                                    <div style="background:#10b981;height:100%;width:{{ ($kb->cicilan_ke/$kb->jumlah_cicilan)*100 }}%;"></div>
                                </div>
                            </td>
                            <td style="padding:12px 8px;text-align:center;">
                                @if($kb->status === 'pending')
                                <div style="display:flex;gap:6px;justify-content:center;">
                                    <form method="POST" action="{{ route('penggajian.kasbon.approve', $kb) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit"
                                            style="font-size:11px;background:#10b981;color:#0f172a;border:none;border-radius:6px;padding:4px 10px;cursor:pointer;font-weight:600;">
                                            ✅ Approve
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('penggajian.kasbon.tolak', $kb) }}" style="display:inline;" onsubmit="return confirm('Yakin tolak kasbon {{ $k->name }} ini?');">
                                        @csrf
                                        <button type="submit"
                                            style="font-size:11px;background:#ef4444;color:#f1f5f9;border:none;border-radius:6px;padding:4px 10px;cursor:pointer;font-weight:600;">
                                            ❌ Tolak
                                        </button>
                                    </form>
                                </div>
                                @else
                                {{-- Form tunda --}}
                                <button onclick="bukaTunda({{ $kb->id }}, '{{ $k->name }}')"
                                    style="font-size:11px;background:#f59e0b;color:#0f172a;border:none;border-radius:6px;padding:4px 10px;cursor:pointer;">
                                    ⏸ Tunda
                                </button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    @endforeach
                    @if(!$adaKasbon)
                    <tr>
                        <td colspan="6" style="text-align:center;padding:30px;color:#475569;">Tidak ada kasbon aktif</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- Modal Tunda Kasbon --}}
<div id="modalTunda" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;width:100%;max-width:380px;">
        <div style="font-weight:700;color:#f59e0b;margin-bottom:4px;">⏸ Tunda Cicilan Kasbon</div>
        <div style="color:#64748b;font-size:13px;margin-bottom:16px;" id="namaTunda"></div>
        <form method="POST" id="formTunda">
            @csrf
            @method('POST')
            <div style="margin-bottom:12px;">
                <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Tunda sampai tanggal</label>
                <input type="date" name="tunda_sampai" required
                    min="{{ today()->addDay()->format('Y-m-d') }}"
                    max="{{ today()->addMonth()->format('Y-m-d') }}"
                    style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:10px;width:100%;">
                <div style="color:#64748b;font-size:11px;margin-top:4px;">Maksimal tunda 1 bulan</div>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" style="flex:1;background:#f59e0b;color:#0f172a;border:none;border-radius:8px;padding:10px;font-weight:600;cursor:pointer;">
                    ⏸ Konfirmasi Tunda
                </button>
                <button type="button" onclick="tutupModal()" style="flex:1;background:#334155;color:#e2e8f0;border:none;border-radius:8px;padding:10px;font-weight:600;cursor:pointer;">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaTunda(id, nama) {
    document.getElementById('namaTunda').textContent = 'Kasbon: ' + nama;
    document.getElementById('formTunda').action = '/penggajian/kasbon/' + id + '/tunda';
    document.getElementById('modalTunda').style.display = 'flex';
}
function tutupModal() {
    document.getElementById('modalTunda').style.display = 'none';
}
</script>
@endsection