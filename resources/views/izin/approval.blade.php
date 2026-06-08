{{-- FILE: resources/views/izin/approval.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approval Izin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; }
  .topbar { background:#1e293b; border-bottom:1px solid #334155; padding:14px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
  .content { padding:16px; max-width:600px; margin:0 auto; padding-bottom:40px; }
  .card-dark { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:16px; margin-bottom:12px; }
  .section-title { color:#94a3b8; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px; }
  .btn-approve { background:#10b981; color:#fff; border:none; border-radius:8px; padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer; }
  .btn-reject { background:transparent; color:#ef4444; border:1px solid #ef4444; border-radius:8px; padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer; }
  .modal-bg { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:999; align-items:center; justify-content:center; padding:16px; }
  .modal-box { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:20px; width:100%; max-width:400px; }
  .form-control-dark { background:#0f172a; border:1px solid #475569; color:#f1f5f9; border-radius:8px; padding:10px; width:100%; font-size:13px; }
  .alert-success { background:rgba(16,185,129,0.2); border:1px solid #10b981; color:#6ee7b7; border-radius:10px; padding:12px; margin-bottom:12px; font-size:13px; }
  .badge-pending { background:rgba(245,158,11,0.2); color:#f59e0b; border:1px solid rgba(245,158,11,0.3); }
  .badge-approved { background:rgba(16,185,129,0.2); color:#10b981; border:1px solid rgba(16,185,129,0.3); }
  .badge-rejected { background:rgba(239,68,68,0.2); color:#ef4444; border:1px solid rgba(239,68,68,0.3); }
</style>
</head>
<body>

<div class="topbar">
  <a href="{{ url('/dashboard') }}" style="color:#64748b; font-size:20px; text-decoration:none;">←</a>
  <div>
    <div style="font-weight:700; color:#fbbf24; font-size:16px;">Approval Izin</div>
    <div style="color:#64748b; font-size:12px;">{{ $pending->count() }} menunggu persetujuan</div>
  </div>
</div>

<div class="content">

  @if(session('success'))
  <div class="alert-success">{{ session('success') }}</div>
  @endif

  {{-- Pending --}}
  <div class="section-title">⏳ Menunggu Persetujuan ({{ $pending->count() }})</div>

  @forelse($pending as $izin)
  <div class="card-dark">
    <div class="d-flex justify-content-between align-items-start mb-3">
      <div>
        <div style="font-size:15px; font-weight:700; color:#f1f5f9;">{{ $izin->user->name }}</div>
        <div style="font-size:12px; color:#64748b;">{{ $izin->user->jabatan }}</div>
      </div>
      <span class="badge badge-pending" style="font-size:11px; padding:4px 10px; border-radius:20px;">
        {{ $izin->tipeLabel() }}
      </span>
    </div>

    <div style="font-size:13px; color:#94a3b8; margin-bottom:4px;">
      📅 {{ $izin->tanggal->translatedFormat('l, d F Y') }}
    </div>
    <div style="font-size:13px; color:#e2e8f0; margin-bottom:12px;">
      {{ $izin->alasan }}
    </div>

    @if($izin->foto_surat)
    <a href="{{ Storage::url($izin->foto_surat) }}" target="_blank"
       style="font-size:12px; color:#fbbf24; text-decoration:none;">
      📎 Lihat Surat Lampiran
    </a>
    @endif

    <div class="d-flex gap-2 mt-3">
      <button class="btn-approve flex-fill" onclick="bukaApprove({{ $izin->id }}, '{{ $izin->user->name }}')">
        ✅ Setujui
      </button>
      <button class="btn-reject flex-fill" onclick="bukaReject({{ $izin->id }}, '{{ $izin->user->name }}')">
        ❌ Tolak
      </button>
    </div>
  </div>
  @empty
  <div class="card-dark text-center" style="padding:30px;">
    <div style="font-size:28px; margin-bottom:8px;">✅</div>
    <div style="color:#64748b; font-size:13px;">Tidak ada izin yang menunggu persetujuan</div>
  </div>
  @endforelse

  {{-- Riwayat --}}
  @if($riwayat->count() > 0)
  <div class="section-title mt-4">📋 Riwayat Terbaru</div>
  @foreach($riwayat as $izin)
  <div class="card-dark">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <div style="font-size:13px; font-weight:600; color:#f1f5f9;">{{ $izin->user->name }}</div>
        <div style="font-size:11px; color:#64748b;">
          {{ $izin->tipeLabel() }} · {{ $izin->tanggal->format('d/m/Y') }}
        </div>
      </div>
      <span class="badge badge-{{ $izin->status }}" style="font-size:11px; padding:4px 10px; border-radius:20px;">
        {{ $izin->statusLabel() }}
      </span>
    </div>
  </div>
  @endforeach
  @endif

</div>

{{-- Modal Approve --}}
<div class="modal-bg" id="modalApprove">
  <div class="modal-box">
    <div style="font-weight:700; color:#fbbf24; margin-bottom:4px;">✅ Setujui Izin</div>
    <div style="color:#64748b; font-size:13px; margin-bottom:16px;" id="namaApprove"></div>
    <form method="POST" id="formApprove">
      @csrf
      @method('PATCH')
      <div style="margin-bottom:12px;">
        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:6px;">Catatan (opsional)</label>
        <textarea name="catatan" class="form-control-dark" rows="3" placeholder="Tambah catatan untuk karyawan..."></textarea>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn-approve flex-fill">✅ Konfirmasi Setujui</button>
        <button type="button" class="btn-reject flex-fill" onclick="tutupModal()">Batal</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Reject --}}
<div class="modal-bg" id="modalReject">
  <div class="modal-box">
    <div style="font-weight:700; color:#ef4444; margin-bottom:4px;">❌ Tolak Izin</div>
    <div style="color:#64748b; font-size:13px; margin-bottom:16px;" id="namaReject"></div>
    <form method="POST" id="formReject">
      @csrf
      @method('PATCH')
      <div style="margin-bottom:12px;">
        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:6px;">Alasan Penolakan <span style="color:#ef4444;">*</span></label>
        <textarea name="catatan" class="form-control-dark" rows="3" placeholder="Jelaskan alasan penolakan..." required></textarea>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" style="background:#ef4444; color:#fff; border:none; border-radius:8px; padding:8px 16px; font-weight:600; flex:1;">❌ Konfirmasi Tolak</button>
        <button type="button" class="btn-reject flex-fill" onclick="tutupModal()">Batal</button>
      </div>
    </form>
  </div>
</div>

<script>
function bukaApprove(id, nama) {
  document.getElementById('namaApprove').textContent = 'Izin dari: ' + nama;
  document.getElementById('formApprove').action = '/izin/' + id + '/approve';
  document.getElementById('modalApprove').style.display = 'flex';
}

function bukaReject(id, nama) {
  document.getElementById('namaReject').textContent = 'Izin dari: ' + nama;
  document.getElementById('formReject').action = '/izin/' + id + '/reject';
  document.getElementById('modalReject').style.display = 'flex';
}

function tutupModal() {
  document.getElementById('modalApprove').style.display = 'none';
  document.getElementById('modalReject').style.display = 'none';
}
</script>
</body>
</html>