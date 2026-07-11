@extends('layouts.app')
@section('title', 'Detail Tugas')

@section('content')
<style>
* { box-sizing: border-box; }
body { background: #0f172a; color: #e2e8f0; }

.detail-card {
    background: #1e293b; border-radius: 16px;
    padding: 24px; border: 1px solid #334155;
    margin-bottom: 16px;
}
.detail-title {
    font-size: 1.25rem; font-weight: 800; color: #f1f5f9;
    margin: 0 0 16px 0;
}
.meta-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 12px; margin-bottom: 16px;
}
.meta-item { }
.meta-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
.meta-value { font-size: 0.92rem; color: #e2e8f0; font-weight: 500; margin-top: 2px; }

.badge {
    display: inline-block; padding: 4px 12px; border-radius: 20px;
    font-size: 0.78rem; font-weight: 600;
}
.badge-tinggi { background:rgba(239,68,68,.2); color:#f87171; }
.badge-sedang { background:rgba(245,158,11,.2); color:#fbbf24; }
.badge-rendah { background:rgba(34,197,94,.2); color:#4ade80; }

.deskripsi-box {
    background: #0f172a; border-radius: 10px; padding: 14px;
    font-size: 0.88rem; color: #cbd5e1; line-height: 1.7;
    margin-top: 12px;
}

/* Assignees table */
.assignee-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
.assignee-table th {
    text-align: left; font-size: 0.75rem; color: #64748b;
    text-transform: uppercase; letter-spacing: 0.5px;
    padding: 8px 10px; border-bottom: 1px solid #334155;
}
.assignee-table td {
    padding: 10px; font-size: 0.88rem; border-bottom: 1px solid #1e293b;
    vertical-align: middle;
}
.assignee-table tr:last-child td { border-bottom: none; }

.status-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600;
}

/* Form update status (untuk karyawan sendiri) */
.update-status-card {
    background: #1e293b; border-radius: 16px;
    padding: 24px; border: 1px solid #fbbf24;
    margin-bottom: 16px;
}
.update-title { font-size: 1rem; font-weight: 700; color: #fbbf24; margin: 0 0 16px 0; }

.status-options {
    display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px;
}
.status-opt {
    flex: 1; min-width: 120px; padding: 14px 10px;
    border: 2px solid #334155; border-radius: 12px;
    text-align: center; cursor: pointer;
    background: #0f172a; transition: all 0.15s;
}
.status-opt:hover { border-color: #475569; }
.status-opt input[type=radio] { display: none; }
.status-opt.selected { border-color: var(--clr); background: rgba(var(--clr-rgb), 0.1); }
.status-opt-icon { font-size: 1.5rem; margin-bottom: 4px; }
.status-opt-label { font-size: 0.82rem; font-weight: 600; color: #e2e8f0; }

.form-control {
    width: 100%; background: #0f172a; color: #e2e8f0;
    border: 1px solid #334155; border-radius: 10px;
    padding: 12px 14px; font-size: 16px;
}
.form-control:focus { outline: none; border-color: #fbbf24; }
textarea.form-control { min-height: 80px; resize: vertical; }

.btn-update {
    background: #fbbf24; color: #0f172a; border: none;
    padding: 12px 28px; border-radius: 10px; font-weight: 700;
    font-size: 0.95rem; cursor: pointer; margin-top: 12px;
    width: 100%;
}
.btn-update:hover { background: #f59e0b; }

/* Timeline waktu */
.timeline { display: flex; flex-direction: column; gap: 8px; margin-top: 8px; }
.timeline-item {
    display: flex; align-items: flex-start; gap: 10px;
    font-size: 0.82rem; color: #94a3b8;
}
.timeline-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: #475569; flex-shrink: 0; margin-top: 4px;
}
.timeline-dot.active { background: #fbbf24; }

/* Tombol aksi atas */
.top-actions { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }
.btn-back { color: #94a3b8; font-size: 0.85rem; text-decoration: none; }
.btn-edit-top {
    background: #334155; color: #e2e8f0; border: none;
    padding: 8px 16px; border-radius: 8px; font-size: 0.85rem;
    cursor: pointer; text-decoration: none;
}
</style>

<div class="container" style="max-width:760px; margin:0 auto; padding:16px;">

    <div class="top-actions">
        <a href="{{ route('tugas.index', ['tanggal' => $tugas->tanggal->toDateString()]) }}" class="btn-back">← Kembali</a>
        @if(in_array(Auth::user()->level, [1,2,3]))
        <a href="{{ route('tugas.edit', $tugas->id) }}" class="btn-edit-top">Edit Tugas</a>
        @endif
    </div>

    {{-- Alert --}}
    @if(session('success'))
    <div style="background:rgba(34,197,94,.15); border:1px solid #22c55e; color:#4ade80; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:0.9rem;">
        {{ session('success') }}
    </div>
    @endif

    {{-- Detail Tugas --}}
    <div class="detail-card">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:16px;">
            <h2 class="detail-title" style="margin:0;">{{ $tugas->judul }}</h2>
            <span class="badge badge-{{ $tugas->prioritas }}">{{ ucfirst($tugas->prioritas) }}</span>
        </div>

        <div class="meta-grid">
            <div class="meta-item">
                <div class="meta-label">Tanggal</div>
                <div class="meta-value">{{ $tugas->tanggal->translatedFormat('l, d F Y') }}</div>
            </div>
            @if($tugas->jam_mulai)
            <div class="meta-item">
                <div class="meta-label">Jam</div>
                <div class="meta-value">
                    {{ substr($tugas->jam_mulai, 0, 5) }}
                    @if($tugas->jam_selesai_target) — {{ substr($tugas->jam_selesai_target, 0, 5) }} @endif
                </div>
            </div>
            @endif
            @if($tugas->lokasi)
            <div class="meta-item">
                <div class="meta-label">Lokasi</div>
                <div class="meta-value">{{ $tugas->lokasi }}</div>
            </div>
            @endif
            <div class="meta-item">
                <div class="meta-label">Dibuat Oleh</div>
                <div class="meta-value">{{ $tugas->pembuat->name ?? '-' }}</div>
            </div>
        </div>

        @if($tugas->deskripsi)
        <div class="meta-item" style="margin-bottom:4px;">
            <div class="meta-label">Detail / Instruksi</div>
        </div>
        <div class="deskripsi-box">{{ $tugas->deskripsi }}</div>
        @endif
    </div>

    {{-- Form update status (karyawan yang di-assign) --}}
    @if($myAssignee)
    <div class="update-status-card">
        <div class="update-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:6px;"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            Update Status Tugasmu
        </div>

        @php
            $statusSaatIni = $myAssignee->status;
        @endphp

        <div style="background:#0f172a; border-radius:10px; padding:12px 14px; margin-bottom:16px; font-size:0.85rem;">
            Status saat ini:
            <span class="status-badge" style="background:{{ $myAssignee->warnaStatus() }}20; color:{{ $myAssignee->warnaStatus() }};">
                {{ $myAssignee->labelStatus() }}
            </span>
            @if($myAssignee->waktu_mulai)
            <span style="color:#64748b; margin-left:10px;">Mulai: {{ $myAssignee->waktu_mulai->format('H:i') }}</span>
            @endif
            @if($myAssignee->waktu_selesai)
            <span style="color:#64748b; margin-left:10px;">Selesai: {{ $myAssignee->waktu_selesai->format('H:i') }}</span>
            @endif
        </div>

        <form method="POST" action="{{ route('tugas.updateStatus', $tugas->id) }}">
            @csrf

            <div class="status-options">
                <label class="status-opt {{ $statusSaatIni=='dikerjakan'?'selected':'' }}"
                       style="--clr:#3b82f6; --clr-rgb:59,130,246;"
                       onclick="selectStatus(this,'dikerjakan')">
                    <input type="radio" name="status" value="dikerjakan" {{ $statusSaatIni=='dikerjakan'?'checked':'' }}>
                    <div class="status-opt-icon">🔵</div>
                    <div class="status-opt-label">Sedang Dikerjakan</div>
                </label>
                <label class="status-opt {{ $statusSaatIni=='selesai'?'selected':'' }}"
                       style="--clr:#22c55e; --clr-rgb:34,197,94;"
                       onclick="selectStatus(this,'selesai')">
                    <input type="radio" name="status" value="selesai" {{ $statusSaatIni=='selesai'?'checked':'' }}>
                    <div class="status-opt-icon">✅</div>
                    <div class="status-opt-label">Selesai</div>
                </label>
                <label class="status-opt {{ $statusSaatIni=='tidak_selesai'?'selected':'' }}"
                       style="--clr:#ef4444; --clr-rgb:239,68,68;"
                       onclick="selectStatus(this,'tidak_selesai')">
                    <input type="radio" name="status" value="tidak_selesai" {{ $statusSaatIni=='tidak_selesai'?'checked':'' }}>
                    <div class="status-opt-icon">❌</div>
                    <div class="status-opt-label">Tidak Selesai</div>
                </label>
            </div>

            <div style="margin-bottom:4px; font-size:0.82rem; color:#94a3b8;">
                Catatan (opsional — untuk laporan ke atasan)
            </div>
            <textarea name="catatan_karyawan" class="form-control"
                      placeholder="Tulis kendala, catatan, atau laporan singkat..."
                      rows="3">{{ $myAssignee->catatan_karyawan }}</textarea>

            <button type="submit" class="btn-update">Simpan Update</button>
        </form>
    </div>
    @endif

    {{-- Daftar semua assignee (untuk owner/admin/supervisor) --}}
    @if(in_array(Auth::user()->level, [1,2,3]) && $tugas->assignees->count() > 0)
    <div class="detail-card">
        <div style="font-size:0.95rem; font-weight:700; color:#fbbf24; margin-bottom:12px;">
            Progres Karyawan ({{ $tugas->assignees->count() }} orang)
        </div>
        <div style="overflow-x:auto;">
        <table class="assignee-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Status</th>
                    <th>Mulai</th>
                    <th>Selesai</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tugas->assignees as $a)
                <tr>
                    <td>
                        <div style="font-weight:600;">{{ $a->user->name ?? '-' }}</div>
                        <div style="font-size:0.75rem; color:#64748b;">{{ $a->user->jabatan ?? '' }}</div>
                    </td>
                    <td>
                        <span class="status-badge" style="background:{{ $a->warnaStatus() }}20; color:{{ $a->warnaStatus() }};">
                            {{ $a->labelStatus() }}
                        </span>
                    </td>
                    <td style="color:#94a3b8; font-size:0.82rem;">
                        {{ $a->waktu_mulai ? $a->waktu_mulai->format('H:i') : '-' }}
                    </td>
                    <td style="color:#94a3b8; font-size:0.82rem;">
                        {{ $a->waktu_selesai ? $a->waktu_selesai->format('H:i') : '-' }}
                    </td>
                    <td style="color:#94a3b8; font-size:0.82rem; max-width:200px;">
                        {{ $a->catatan_karyawan ?? '-' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
    @endif

</div>

<script>
function selectStatus(el, val) {
    document.querySelectorAll('.status-opt').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    el.querySelector('input[type=radio]').checked = true;
}
</script>
@endsection