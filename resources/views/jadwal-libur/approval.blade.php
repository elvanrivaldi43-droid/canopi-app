{{-- FILE: resources/views/jadwal-libur/approval.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approval Jadwal Libur</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; }
  .topbar { background:#1e293b; border-bottom:1px solid #334155; padding:14px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
  .content { padding:16px; max-width:600px; margin:0 auto; padding-bottom:40px; }
  .card-dark { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:16px; margin-bottom:12px; }
  .section-title { color:#94a3b8; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px; }
  .btn-approve { background:#10b981; color:#fff; border:none; border-radius:8px; padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer; }
  .btn-reject { background:transparent; color:#ef4444; border:1px solid #ef4444; border-radius:8px; padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer; }
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
    <div style="font-weight:700; color:#fbbf24; font-size:16px;">Approval Jadwal Libur</div>
    <div style="color:#64748b; font-size:12px;">{{ $pending->count() }} menunggu persetujuan</div>
  </div>
</div>

<div class="content">

  @if(session('success'))
  <div class="alert-success">{{ session('success') }}</div>
  @endif

  <div class="section-title">⏳ Menunggu Persetujuan ({{ $pending->count() }})</div>

  @forelse($pending as $jadwal)
  <div class="card-dark">
    <div class="d-flex justify-content-between align-items-start mb-3">
      <div>
        <div style="font-size:15px; font-weight:700; color:#f1f5f9;">{{ $jadwal->user->name }}</div>
        <div style="font-size:12px; color:#64748b;">{{ $jadwal->user->jabatan }}</div>
      </div>
      <span class="badge badge-pending" style="font-size:11px; padding:4px 10px; border-radius:20px;">
        {{ $jadwal->jenisLabel() }}
      </span>
    </div>

    <div style="font-size:13px; color:#94a3b8; margin-bottom:4px;">
      📅 {{ $jadwal->labelTanggal('l, d F Y') }}
    </div>
    @if($jadwal->alasan)
    <div style="font-size:13px; color:#e2e8f0; margin-bottom:12px;">
      {{ $jadwal->alasan }}
    </div>
    @endif

    <div class="d-flex gap-2 mt-3">
      <form method="POST" action="{{ route('jadwal-libur.approve', $jadwal) }}" class="flex-fill">
        @csrf
        @method('PATCH')
        <button type="submit" class="btn-approve" style="width:100%;">✅ Setujui</button>
      </form>
      <form method="POST" action="{{ route('jadwal-libur.reject', $jadwal) }}" class="flex-fill">
        @csrf
        @method('PATCH')
        <button type="submit" class="btn-reject" style="width:100%;">❌ Tolak</button>
      </form>
    </div>
  </div>
  @empty
  <div class="card-dark text-center" style="padding:30px;">
    <div style="font-size:28px; margin-bottom:8px;">✅</div>
    <div style="color:#64748b; font-size:13px;">Tidak ada ajuan yang menunggu persetujuan</div>
  </div>
  @endforelse

  @if($riwayat->count() > 0)
  <div class="section-title mt-4">📋 Riwayat Terbaru</div>
  @foreach($riwayat as $jadwal)
  <div class="card-dark">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <div style="font-size:13px; font-weight:600; color:#f1f5f9;">{{ $jadwal->user->name }}</div>
        <div style="font-size:11px; color:#64748b;">
          {{ $jadwal->jenisLabel() }} · {{ $jadwal->labelTanggal() }}
        </div>
      </div>
      <span class="badge badge-{{ $jadwal->status }}" style="font-size:11px; padding:4px 10px; border-radius:20px;">
        {{ $jadwal->statusLabel() }}
      </span>
    </div>
  </div>
  @endforeach
  @endif

</div>
</body>
</html>
