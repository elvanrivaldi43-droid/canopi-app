@extends('layouts.app')

@section('content')
<div style="padding:16px; max-width:1100px; margin:0 auto;">

    {{-- Header --}}
    <div style="display:flex; align-items:flex-start; gap:12px; margin-bottom:20px; flex-wrap:wrap;">
        <a href="{{ route('projects.index') }}" style="color:#94a3b8; text-decoration:none; margin-top:4px;">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </a>
        <div style="flex:1;">
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:4px;">
                <span style="color:#64748b; font-size:12px; font-family:monospace;">{{ $project->kode_project }}</span>
                <span style="background:{{ $project->status_color }}22; color:{{ $project->status_color }}; padding:3px 12px; border-radius:20px; font-size:13px; font-weight:600;">
                    {{ $project->status_label }}
                </span>
                @if($project->rateKondisi && $project->rateKondisi->kode !== 'STD')
                <span style="background:#7c3aed22; color:#a78bfa; padding:3px 10px; border-radius:20px; font-size:12px;">
                    ⚡ {{ $project->rateKondisi->nama }}@if(auth()->user()->level == 1) (×{{ $project->multiplier_upah }})@endif
                </span>
                @endif
            </div>
            <h1 style="color:#e2e8f0; font-size:20px; font-weight:700; margin:0;">{{ $project->nama_customer }}</h1>
            <p style="color:#94a3b8; font-size:14px; margin:4px 0 0;">{{ $project->jenis_project }} @if($project->alamat_project) — {{ $project->alamat_project }} @endif</p>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
    <div style="background:#064e3b; border:1px solid #10b981; color:#6ee7b7; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:14px;">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
    <div style="background:#451a03; border:1px solid #f59e0b; color:#fcd34d; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:14px;">⚠️ {{ session('warning') }}</div>
    @endif

    {{-- Approval kondisi khusus --}}
    @if($project->rateKondisi && $project->rateKondisi->kode !== 'STD' && !$project->kondisi_approved_at && auth()->user()->level == 1)
    <div style="background:#451a03; border:1px solid #f59e0b; border-radius:10px; padding:16px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div>
            <p style="color:#fcd34d; font-weight:700; margin:0 0 4px;">⚠️ Kondisi Kerja Khusus Belum Diapprove</p>
            <p style="color:#fbbf24; font-size:13px; margin:0;">{{ $project->rateKondisi->nama }} — Rate tukang Rp {{ number_format($project->rateKondisi->rate_tukang_final,0,',','.') }}/hari</p>
        </div>
        <form method="POST" action="{{ route('projects.approve-kondisi', $project) }}">
            @csrf
            <button type="submit" style="background:#f59e0b; color:#0f172a; padding:10px 20px; border-radius:8px; border:none; font-weight:700; cursor:pointer;">Approve Kondisi</button>
        </form>
    </div>
    @endif

    {{-- Material pending approval --}}
    @if($materialPendingApproval->count() > 0 && auth()->user()->level == 1)
    <div style="background:#3b0764; border:1px solid #8b5cf6; border-radius:10px; padding:16px; margin-bottom:16px;">
        <p style="color:#c4b5fd; font-weight:700; margin:0 0 10px;">🔔 {{ $materialPendingApproval->count() }} Pembelian Melebihi RAB — Menunggu Approval</p>
        @foreach($materialPendingApproval as $mp)
        <div style="display:flex; justify-content:space-between; align-items:center; background:#1e1b4b; border-radius:8px; padding:10px 14px; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
            <div>
                <span style="color:#e2e8f0; font-weight:600;">{{ $mp->nama_material }}</span>
                <span style="color:#94a3b8; font-size:13px; margin-left:8px;">Rp {{ number_format($mp->total,0,',','.') }}</span>
            </div>
            <form method="POST" action="{{ route('projects.material.approve', $mp) }}">
                @csrf
                <button type="submit" style="background:#8b5cf6; color:#fff; padding:6px 14px; border-radius:6px; border:none; font-size:13px; font-weight:600; cursor:pointer;">Approve</button>
            </form>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Ringkasan Profit (owner only) --}}
    @if(auth()->user()->level == 1)
    <div style="background:#1e293b; border:1px solid #334155; border-radius:12px; padding:20px; margin-bottom:20px;">
        <h3 style="color:#94a3b8; font-size:13px; font-weight:600; margin:0 0 14px; text-transform:uppercase; letter-spacing:0.05em;">Ringkasan Profit</h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:12px;">
            <div>
                <p style="color:#64748b; font-size:12px; margin:0 0 4px;">Nilai Kontrak</p>
                <p style="color:#fbbf24; font-size:16px; font-weight:700; margin:0;">Rp {{ number_format($project->nilai_kontrak,0,',','.') }}</p>
            </div>
            <div>
                <p style="color:#64748b; font-size:12px; margin:0 0 4px;">Material Aktual</p>
                <p style="color:#f87171; font-size:16px; font-weight:700; margin:0;">Rp {{ number_format($totalMaterialAktual,0,',','.') }}</p>
            </div>
            <div>
                <p style="color:#64748b; font-size:12px; margin:0 0 4px;">Upah Tim</p>
                <p style="color:#f87171; font-size:16px; font-weight:700; margin:0;">Rp {{ number_format($totalUpahTim,0,',','.') }}</p>
            </div>
            <div style="border-left:2px solid #334155; padding-left:12px;">
                <p style="color:#64748b; font-size:12px; margin:0 0 4px;">Profit Bersih</p>
                @php $profit = $project->nilai_kontrak - $totalMaterialAktual - $totalUpahTim; @endphp
                <p style="color:{{ $profit >= 0 ? '#10b981' : '#ef4444' }}; font-size:20px; font-weight:700; margin:0;">Rp {{ number_format($profit,0,',','.') }}</p>
            </div>
            <div>
                <p style="color:#64748b; font-size:12px; margin:0 0 4px;">Margin</p>
                @php $margin = $project->nilai_kontrak > 0 ? round(($profit/$project->nilai_kontrak)*100,1) : 0; @endphp
                <p style="color:{{ $margin >= 20 ? '#10b981' : ($margin >= 0 ? '#f59e0b' : '#ef4444') }}; font-size:20px; font-weight:700; margin:0;">{{ $margin }}%</p>
            </div>
            <div>
                <p style="color:#64748b; font-size:12px; margin:0 0 4px;">Sisa Tagihan</p>
                <p style="color:#60a5fa; font-size:16px; font-weight:700; margin:0;">Rp {{ number_format($project->sisa_tagihan,0,',','.') }}</p>
            </div>
        </div>

        {{-- Progress bar RAB vs Aktual --}}
        @if($totalRabPokok > 0)
        <div style="margin-top:14px; padding-top:14px; border-top:1px solid #334155;">
            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                <span style="color:#94a3b8; font-size:12px;">Material Aktual vs RAB</span>
                <span style="color:#94a3b8; font-size:12px;">Rp {{ number_format($totalMaterialAktual,0,',','.') }} / Rp {{ number_format($totalRabPokok,0,',','.') }}</span>
            </div>
            @php $pct = min(100, round(($totalMaterialAktual/$totalRabPokok)*100)); @endphp
            <div style="background:#334155; border-radius:99px; height:8px; overflow:hidden;">
                <div style="background:{{ $pct > 100 ? '#ef4444' : ($pct > 80 ? '#f59e0b' : '#10b981') }}; width:{{ $pct }}%; height:100%; border-radius:99px; transition:width 0.5s;"></div>
            </div>
            <p style="color:{{ $selisihMaterial >= 0 ? '#10b981' : '#ef4444' }}; font-size:12px; margin:6px 0 0; text-align:right;">
                {{ $selisihMaterial >= 0 ? 'Sisa RAB: Rp '.number_format($selisihMaterial,0,',','.') : 'MELEBIHI RAB: Rp '.number_format(abs($selisihMaterial),0,',','.') }}
            </p>
        </div>
        @endif
    </div>
    @endif

    {{-- Update Status --}}
    @if(auth()->user()->level <= 3)
    <div style="background:#1e293b; border:1px solid #334155; border-radius:12px; padding:16px 20px; margin-bottom:20px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <span style="color:#94a3b8; font-size:13px; font-weight:600;">Update Status:</span>
        <form method="POST" action="{{ route('projects.update-status', $project) }}" style="display:flex; gap:8px; flex-wrap:wrap;">
            @csrf @method('PATCH')
            @foreach(\App\Models\Project::$statusLabel as $k => $l)
            <button type="submit" name="status" value="{{ $k }}"
                    style="background:{{ $project->status === $k ? \App\Models\Project::$statusColor[$k].'33' : '#334155' }}; color:{{ $project->status === $k ? \App\Models\Project::$statusColor[$k] : '#94a3b8' }}; border:1px solid {{ $project->status === $k ? \App\Models\Project::$statusColor[$k] : '#334155' }}; padding:6px 14px; border-radius:20px; font-size:13px; font-weight:{{ $project->status === $k ? '700' : '400' }}; cursor:pointer;">
                {{ $l }}
            </button>
            @endforeach
        </form>
    </div>
    @endif

    {{-- TABS --}}
    <div x-data="{ tab: 'tim' }">
        {{-- Tab Nav --}}
        <div style="display:flex; gap:4px; background:#1e293b; border:1px solid #334155; border-radius:12px; padding:6px; margin-bottom:16px; overflow-x:auto;">
            @foreach([['tim','Tim & Jadwal'],['material','Material Aktual'],['pembayaran','Pembayaran'],['rab','RAB Rencana']] as [$key,$label])
            <button @click="tab='{{ $key }}'"
                    :style="tab==='{{ $key }}' ? 'background:#fbbf24; color:#0f172a; font-weight:700;' : 'background:transparent; color:#94a3b8;'"
                    style="flex:1; padding:9px 12px; border-radius:8px; border:none; font-size:13px; cursor:pointer; white-space:nowrap; min-width:100px; transition:all 0.2s;">
                {{ $label }}
            </button>
            @endforeach
        </div>

        {{-- TAB: TIM & JADWAL --}}
        <div x-show="tab==='tim'">
            <div style="background:#1e293b; border:1px solid #334155; border-radius:12px; padding:20px; margin-bottom:16px;">
                <h3 style="color:#e2e8f0; font-size:15px; font-weight:700; margin:0 0 16px;">Tim Project</h3>

                @if($project->tim->count() > 0)
                <div style="overflow-x:auto; margin-bottom:20px;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="border-bottom:1px solid #334155;">
                                <th style="padding:8px 12px; text-align:left; color:#64748b; font-weight:600;">Nama</th>
                                <th style="padding:8px 12px; text-align:left; color:#64748b; font-weight:600;">Jabatan</th>
                                <th style="padding:8px 12px; text-align:left; color:#64748b; font-weight:600;">Periode</th>
                                <th style="padding:8px 12px; text-align:center; color:#64748b; font-weight:600;">Hari</th>
                                @if(auth()->user()->level == 1)
                                <th style="padding:8px 12px; text-align:right; color:#64748b; font-weight:600;">Rate/Hari</th>
                                <th style="padding:8px 12px; text-align:right; color:#64748b; font-weight:600;">Total Upah</th>
                                @endif
                                <th style="padding:8px 12px; text-align:center; color:#64748b; font-weight:600;">Status</th>
                                @if(auth()->user()->level <= 3)
                                <th style="padding:8px 12px;"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($project->tim as $t)
                            <tr style="border-bottom:1px solid #1e293b;">
                                <td style="padding:10px 12px; color:#e2e8f0; font-weight:600;">{{ $t->user->name ?? '-' }}</td>
                                <td style="padding:10px 12px;">
                                    <span style="background:{{ $t->jabatan_lapangan==='tukang' ? '#1d4ed822' : '#06474822' }}; color:{{ $t->jabatan_lapangan==='tukang' ? '#60a5fa' : '#6ee7b7' }}; padding:2px 8px; border-radius:20px; font-size:12px;">
                                        {{ $t->jabatan_label }}
                                    </span>
                                </td>
                                <td style="padding:10px 12px; color:#94a3b8;">
                                    {{ \Carbon\Carbon::parse($t->tgl_masuk)->format('d M') }} → {{ \Carbon\Carbon::parse($t->tgl_keluar)->format('d M Y') }}
                                </td>
                                <td style="padding:10px 12px; text-align:center; color:#e2e8f0; font-weight:600;">{{ $t->jumlah_hari }}</td>
                                @if(auth()->user()->level == 1)
                                <td style="padding:10px 12px; text-align:right; color:#94a3b8;">Rp {{ number_format($t->rate_final,0,',','.') }}</td>
                                <td style="padding:10px 12px; text-align:right; color:#fbbf24; font-weight:700;">Rp {{ number_format($t->total_upah,0,',','.') }}</td>
                                @endif
                                <td style="padding:10px 12px; text-align:center;">
                                    <span style="background:{{ $t->status==='disetujui' ? '#064e3b' : '#451a03' }}; color:{{ $t->status==='disetujui' ? '#6ee7b7' : '#fcd34d' }}; padding:2px 8px; border-radius:20px; font-size:11px;">
                                        {{ $t->status === 'disetujui' ? 'Disetujui' : 'Pending' }}
                                    </span>
                                </td>
                                @if(auth()->user()->level <= 3)
                                <td style="padding:10px 12px;">
                                    <form method="POST" action="{{ route('projects.tim.destroy', $t) }}" onsubmit="return confirm('Hapus anggota tim ini?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" style="background:#7f1d1d; color:#fca5a5; padding:4px 10px; border-radius:6px; border:none; font-size:11px; cursor:pointer;">Hapus</button>
                                    </form>
                                </td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="border-top:2px solid #334155;">
                                @if(auth()->user()->level == 1)
                                <td colspan="5" style="padding:10px 12px; color:#94a3b8; font-weight:600; font-size:13px;">Total Upah Tim</td>
                                <td style="padding:10px 12px; text-align:right; color:#fbbf24; font-size:16px; font-weight:700;">Rp {{ number_format($totalUpahTim,0,',','.') }}</td>
                                <td colspan="2"></td>
                                @else
                                <td colspan="4" style="padding:10px 12px; color:#94a3b8; font-weight:600; font-size:13px;">Total Anggota Tim</td>
                                <td style="padding:10px 12px; text-align:center; color:#fbbf24; font-size:16px; font-weight:700;">{{ $project->tim->count() }} orang</td>
                                <td colspan="2"></td>
                                @endif
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @endif

                {{-- Form Assign Tim (SPV & Admin) --}}
                @if(auth()->user()->level <= 3)
                <div style="border-top:1px solid #334155; padding-top:16px;">
                    <h4 style="color:#94a3b8; font-size:13px; font-weight:600; margin:0 0 12px;">+ Tambah Anggota Tim</h4>
                    <form method="POST" action="{{ route('projects.tim.store', $project) }}">
                        @csrf
                        <div style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr auto; gap:10px; align-items:end; flex-wrap:wrap;">
                            <div>
                                <label style="display:block; color:#64748b; font-size:12px; margin-bottom:4px;">Karyawan</label>
                                <select name="id_user" required style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:9px 12px; border-radius:8px; font-size:13px; outline:none;">
                                    <option value="">-- Pilih --</option>
                                    @foreach($karyawan as $k)
                                    <option value="{{ $k->id }}">{{ $k->name }} ({{ $k->jabatan }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label style="display:block; color:#64748b; font-size:12px; margin-bottom:4px;">Jabatan Lapangan</label>
                                <select name="jabatan_lapangan" required style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:9px 12px; border-radius:8px; font-size:13px; outline:none;">
                                    @if(auth()->user()->level == 1)
                                    <option value="tukang">Tukang (Rp {{ number_format(round(170000 * $project->multiplier_upah),0,',','.') }}/hari)</option>
                                    <option value="kenek">Kenek (Rp {{ number_format(round(120000 * $project->multiplier_upah),0,',','.') }}/hari)</option>
                                    @else
                                    <option value="tukang">Tukang</option>
                                    <option value="kenek">Kenek</option>
                                    @endif
                                </select>
                            </div>
                            <div>
                                <label style="display:block; color:#64748b; font-size:12px; margin-bottom:4px;">Tgl Masuk</label>
                                <input type="date" name="tgl_masuk" required style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:9px 12px; border-radius:8px; font-size:13px; outline:none; box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="display:block; color:#64748b; font-size:12px; margin-bottom:4px;">Tgl Keluar</label>
                                <input type="date" name="tgl_keluar" required style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:9px 12px; border-radius:8px; font-size:13px; outline:none; box-sizing:border-box;">
                            </div>
                            <button type="submit" style="background:#3b82f6; color:#fff; padding:9px 16px; border-radius:8px; border:none; font-weight:600; font-size:13px; cursor:pointer; white-space:nowrap;">+ Tambah</button>
                        </div>
                    </form>
                </div>
                @endif
            </div>
        </div>

        {{-- TAB: MATERIAL AKTUAL --}}
        <div x-show="tab==='material'">
            <div style="background:#1e293b; border:1px solid #334155; border-radius:12px; padding:20px; margin-bottom:16px;">
                <h3 style="color:#e2e8f0; font-size:15px; font-weight:700; margin:0 0 16px;">Material Aktual</h3>

                @if($project->materialAktual->count() > 0)
                <div style="overflow-x:auto; margin-bottom:20px;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="border-bottom:1px solid #334155;">
                                <th style="padding:8px 12px; text-align:left; color:#64748b;">Tanggal</th>
                                <th style="padding:8px 12px; text-align:left; color:#64748b;">Material</th>
                                <th style="padding:8px 12px; text-align:center; color:#64748b;">Qty</th>
                                <th style="padding:8px 12px; text-align:right; color:#64748b;">Harga Satuan</th>
                                <th style="padding:8px 12px; text-align:right; color:#64748b;">Total</th>
                                <th style="padding:8px 12px; text-align:center; color:#64748b;">Status</th>
                                <th style="padding:8px 12px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($project->materialAktual->sortByDesc('tanggal_beli') as $m)
                            <tr style="border-bottom:1px solid #0f172a;">
                                <td style="padding:9px 12px; color:#64748b; font-size:12px;">{{ \Carbon\Carbon::parse($m->tanggal_beli)->format('d M') }}</td>
                                <td style="padding:9px 12px; color:#e2e8f0;">{{ $m->nama_material }}</td>
                                <td style="padding:9px 12px; text-align:center; color:#94a3b8;">{{ $m->qty_aktual }} {{ $m->satuan }}</td>
                                <td style="padding:9px 12px; text-align:right; color:#94a3b8;">Rp {{ number_format($m->harga_satuan,0,',','.') }}</td>
                                <td style="padding:9px 12px; text-align:right; color:#fbbf24; font-weight:600;">Rp {{ number_format($m->total,0,',','.') }}</td>
                                <td style="padding:9px 12px; text-align:center;">
                                    @if($m->status_vs_rab === 'melebihi_rab')
                                    <span style="background:#7c2d1222; color:#fca5a5; padding:2px 8px; border-radius:20px; font-size:11px;">⚠️ Melebihi RAB</span>
                                    @elseif($m->status_vs_rab === 'approved')
                                    <span style="background:#7c3aed22; color:#a78bfa; padding:2px 8px; border-radius:20px; font-size:11px;">✓ Approved</span>
                                    @else
                                    <span style="background:#06284822; color:#6ee7b7; padding:2px 8px; border-radius:20px; font-size:11px;">Normal</span>
                                    @endif
                                </td>
                                <td style="padding:9px 12px;">
                                    @if(auth()->user()->level <= 2)
                                    <form method="POST" action="{{ route('projects.material.destroy', $m) }}" onsubmit="return confirm('Hapus material ini?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" style="background:#7f1d1d; color:#fca5a5; padding:3px 8px; border-radius:6px; border:none; font-size:11px; cursor:pointer;">Hapus</button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="border-top:2px solid #334155;">
                                <td colspan="4" style="padding:10px 12px; color:#94a3b8; font-weight:600;">Total Material</td>
                                <td style="padding:10px 12px; text-align:right; color:#fbbf24; font-size:16px; font-weight:700;">Rp {{ number_format($totalMaterialAktual,0,',','.') }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @endif

                {{-- Form Input Material (Admin) --}}
                @if(auth()->user()->level <= 2)
                <div style="border-top:1px solid #334155; padding-top:16px;">
                    <h4 style="color:#94a3b8; font-size:13px; font-weight:600; margin:0 0 12px;">+ Catat Pembelian Material</h4>
                    <form method="POST" action="{{ route('projects.material.store', $project) }}" id="formMaterial">
                        @csrf
                        <input type="hidden" name="id_master_material" id="hiddenMasterId">
                        <input type="hidden" name="satuan" id="hiddenSatuan" value="pcs">

                        <div style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr auto; gap:10px; align-items:end; margin-bottom:10px;">
                            <div>
                                <label style="display:block; color:#64748b; font-size:12px; margin-bottom:4px;">Nama Material</label>
                                <input type="text" name="nama_material" id="inputMaterial" placeholder="Ketik untuk cari atau input bebas..." required autocomplete="off"
                                       style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:9px 12px; border-radius:8px; font-size:13px; outline:none; box-sizing:border-box;">
                                <div id="autocompleteList" style="position:absolute; background:#1e293b; border:1px solid #334155; border-radius:8px; z-index:100; max-height:200px; overflow-y:auto; display:none; min-width:300px;"></div>
                            </div>
                            <div>
                                <label style="display:block; color:#64748b; font-size:12px; margin-bottom:4px;">Qty</label>
                                <input type="number" name="qty_aktual" step="0.01" min="0.01" required
                                       style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:9px 12px; border-radius:8px; font-size:13px; outline:none; box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="display:block; color:#64748b; font-size:12px; margin-bottom:4px;">Harga Satuan</label>
                                <input type="number" name="harga_satuan" id="inputHarga" min="0" required
                                       style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:9px 12px; border-radius:8px; font-size:13px; outline:none; box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="display:block; color:#64748b; font-size:12px; margin-bottom:4px;">Tanggal Beli</label>
                                <input type="date" name="tanggal_beli" value="{{ date('Y-m-d') }}"
                                       style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:9px 12px; border-radius:8px; font-size:13px; outline:none; box-sizing:border-box;">
                            </div>
                            <button type="submit" style="background:#3b82f6; color:#fff; padding:9px 16px; border-radius:8px; border:none; font-weight:600; font-size:13px; cursor:pointer; white-space:nowrap;">+ Catat</button>
                        </div>
                    </form>
                </div>
                @endif
            </div>
        </div>

        {{-- TAB: PEMBAYARAN --}}
        <div x-show="tab==='pembayaran'">
            <div style="background:#1e293b; border:1px solid #334155; border-radius:12px; padding:20px; margin-bottom:16px;">
                <h3 style="color:#e2e8f0; font-size:15px; font-weight:700; margin:0 0 16px;">Pembayaran Customer</h3>

                {{-- Progress --}}
                @php $pctBayar = $project->nilai_kontrak > 0 ? min(100,round(($project->total_bayar/$project->nilai_kontrak)*100)) : 0; @endphp
                <div style="margin-bottom:20px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                        <span style="color:#94a3b8; font-size:13px;">Total Terbayar</span>
                        <span style="color:#e2e8f0; font-size:13px; font-weight:600;">Rp {{ number_format($project->total_bayar,0,',','.') }} / Rp {{ number_format($project->nilai_kontrak,0,',','.') }} ({{ $pctBayar }}%)</span>
                    </div>
                    <div style="background:#334155; border-radius:99px; height:10px; overflow:hidden;">
                        <div style="background:{{ $pctBayar >= 100 ? '#10b981' : '#3b82f6' }}; width:{{ $pctBayar }}%; height:100%; border-radius:99px;"></div>
                    </div>
                    <p style="color:#64748b; font-size:12px; margin:6px 0 0; text-align:right;">Sisa: Rp {{ number_format($project->sisa_tagihan,0,',','.') }}</p>
                </div>

                @if($project->pembayaran->count() > 0)
                <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:20px;">
                    @foreach($project->pembayaran->sortByDesc('tanggal_bayar') as $pb)
                    <div style="background:#0f172a; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                        <div>
                            <span style="color:#e2e8f0; font-weight:600;">{{ $pb->jenis_label }}</span>
                            <span style="color:#64748b; font-size:12px; margin-left:8px;">{{ \Carbon\Carbon::parse($pb->tanggal_bayar)->format('d M Y') }}</span>
                            @if($pb->metode) <span style="color:#64748b; font-size:12px;">— {{ $pb->metode }}</span> @endif
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="color:#fbbf24; font-weight:700; font-size:15px;">Rp {{ number_format($pb->nominal,0,',','.') }}</span>
                            @if($pb->status === 'pending' && auth()->user()->level == 1)
                            <form method="POST" action="{{ route('projects.pembayaran.konfirmasi', $pb) }}">
                                @csrf
                                <button type="submit" style="background:#10b981; color:#fff; padding:5px 12px; border-radius:6px; border:none; font-size:12px; font-weight:600; cursor:pointer;">Konfirmasi</button>
                            </form>
                            @else
                            <span style="background:{{ $pb->status==='dikonfirmasi' ? '#064e3b' : '#451a03' }}; color:{{ $pb->status==='dikonfirmasi' ? '#6ee7b7' : '#fcd34d' }}; padding:3px 10px; border-radius:20px; font-size:11px;">
                                {{ $pb->status === 'dikonfirmasi' ? '✓ Dikonfirmasi' : 'Pending' }}
                            </span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Form Catat Pembayaran (Admin) --}}
                @if(auth()->user()->level <= 2)
                <div style="border-top:1px solid #334155; padding-top:16px;">
                    <h4 style="color:#94a3b8; font-size:13px; font-weight:600; margin:0 0 12px;">+ Catat Pembayaran Masuk</h4>
                    <form method="POST" action="{{ route('projects.pembayaran.store', $project) }}">
                        @csrf
                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:10px; margin-bottom:10px;">
                            <div>
                                <label style="display:block; color:#64748b; font-size:12px; margin-bottom:4px;">Jenis</label>
                                <select name="jenis" required style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:9px 12px; border-radius:8px; font-size:13px; outline:none;">
                                    <option value="dp">DP</option>
                                    <option value="termin">Termin</option>
                                    <option value="lunas">Pelunasan</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; color:#64748b; font-size:12px; margin-bottom:4px;">Nominal (Rp)</label>
                                <input type="number" name="nominal" min="1" required style="width:100%; background:#0f172a; border:1px solid #334155; color:#fbbf24; padding:9px 12px; border-radius:8px; font-size:13px; font-weight:700; outline:none; box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="display:block; color:#64748b; font-size:12px; margin-bottom:4px;">Tanggal</label>
                                <input type="date" name="tanggal_bayar" value="{{ date('Y-m-d') }}" required style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:9px 12px; border-radius:8px; font-size:13px; outline:none; box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="display:block; color:#64748b; font-size:12px; margin-bottom:4px;">Metode</label>
                                <input type="text" name="metode" placeholder="Transfer BCA, Cash..." style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:9px 12px; border-radius:8px; font-size:13px; outline:none; box-sizing:border-box;">
                            </div>
                        </div>
                        <button type="submit" style="background:#3b82f6; color:#fff; padding:9px 20px; border-radius:8px; border:none; font-weight:600; font-size:13px; cursor:pointer;">Catat Pembayaran</button>
                    </form>
                </div>
                @endif
            </div>
        </div>

        {{-- TAB: RAB RENCANA --}}
        <div x-show="tab==='rab'">
            <div style="background:#1e293b; border:1px solid #334155; border-radius:12px; padding:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                    <h3 style="color:#e2e8f0; font-size:15px; font-weight:700; margin:0;">RAB Rencana</h3>
                    @if(auth()->user()->level == 1)
                    <div style="text-align:right;">
                        <p style="color:#64748b; font-size:12px; margin:0 0 2px;">Total RAB Pokok</p>
                        <p style="color:#fbbf24; font-size:16px; font-weight:700; margin:0;">Rp {{ number_format($totalRabPokok,0,',','.') }}</p>
                    </div>
                    @endif
                </div>

                @if($project->rabItems->count() > 0)
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="border-bottom:1px solid #334155;">
                                <th style="padding:8px 12px; text-align:left; color:#64748b;">Item</th>
                                <th style="padding:8px 12px; text-align:center; color:#64748b;">Qty</th>
                                <th style="padding:8px 12px; text-align:center; color:#64748b;">Satuan</th>
                                @if(auth()->user()->level == 1)
                                <th style="padding:8px 12px; text-align:right; color:#64748b;">Harga Pokok</th>
                                <th style="padding:8px 12px; text-align:right; color:#64748b;">Total Pokok</th>
                                @endif
                                <th style="padding:8px 12px; text-align:right; color:#64748b;">Total Customer</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($project->rabItems as $ri)
                            <tr style="border-bottom:1px solid #0f172a;">
                                <td style="padding:9px 12px; color:#e2e8f0;">{{ $ri->nama_item }}</td>
                                <td style="padding:9px 12px; text-align:center; color:#94a3b8;">{{ $ri->qty_rencana }}</td>
                                <td style="padding:9px 12px; text-align:center; color:#94a3b8;">{{ $ri->satuan }}</td>
                                @if(auth()->user()->level == 1)
                                <td style="padding:9px 12px; text-align:right; color:#94a3b8;">Rp {{ number_format($ri->harga_pokok,0,',','.') }}</td>
                                <td style="padding:9px 12px; text-align:right; color:#94a3b8;">Rp {{ number_format($ri->total_pokok,0,',','.') }}</td>
                                @endif
                                <td style="padding:9px 12px; text-align:right; color:#fbbf24; font-weight:600;">Rp {{ number_format($ri->total_customer,0,',','.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div style="text-align:center; padding:40px; color:#64748b;">
                    <p style="margin:0 0 8px;">RAB belum dibuat untuk project ini</p>
                    <p style="margin:0; font-size:12px;">RAB Builder akan tersedia segera</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
// Autocomplete material
const input = document.getElementById('inputMaterial');
const list = document.getElementById('autocompleteList');
const hiddenId = document.getElementById('hiddenMasterId');
const hiddenSatuan = document.getElementById('hiddenSatuan');
const inputHarga = document.getElementById('inputHarga');

if (input) {
    let debounce;
    input.addEventListener('input', function() {
        clearTimeout(debounce);
        const q = this.value.trim();
        if (q.length < 2) { list.style.display = 'none'; return; }
        debounce = setTimeout(async () => {
            const res = await fetch(`/api/material/search?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            if (!data.length) { list.style.display = 'none'; return; }
            list.innerHTML = data.map(m =>
                `<div onclick="pilihMaterial(${m.id},'${m.nama.replace(/'/g,"\\'")}','${m.satuan}',${m.harga_pokok})"
                      style="padding:10px 14px; cursor:pointer; color:#e2e8f0; font-size:13px; border-bottom:1px solid #334155;"
                      onmouseover="this.style.background='#334155'" onmouseout="this.style.background='transparent'">
                    <span style="font-weight:600;">${m.nama}</span>
                    <span style="color:#64748b; margin-left:8px;">${m.satuan} — Rp ${m.harga_pokok.toLocaleString('id-ID')}</span>
                </div>`
            ).join('');
            list.style.display = 'block';
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !list.contains(e.target)) list.style.display = 'none';
    });
}

function pilihMaterial(id, nama, satuan, harga) {
    document.getElementById('inputMaterial').value = nama;
    document.getElementById('hiddenMasterId').value = id;
    document.getElementById('hiddenSatuan').value = satuan;
    document.getElementById('inputHarga').value = harga;
    list.style.display = 'none';
}
</script>
@endsection
