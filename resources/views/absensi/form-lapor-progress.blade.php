{{-- FILE: resources/views/absensi/form-lapor-progress.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Lapor Progress</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  * { box-sizing:border-box; }
  body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; margin:0; padding:0; }
  .topbar { background:#1e293b; border-bottom:1px solid #334155; padding:14px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
  .topbar-title { font-weight:700; color:#fbbf24; font-size:16px; }
  .topbar-sub { color:#64748b; font-size:12px; }
  .content { padding:16px; padding-bottom:120px; }
  .card-dark { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:16px; margin-bottom:14px; }
  .section-label { color:#94a3b8; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:10px; }
  .foto-slot { aspect-ratio:1; max-width:200px; border-radius:8px; overflow:hidden; background:#0f172a; border:2px dashed #334155; display:flex; align-items:center; justify-content:center; cursor:pointer; position:relative; margin:0 auto; }
  .foto-slot img { width:100%; height:100%; object-fit:cover; }
  .foto-slot .plus { color:#475569; font-size:32px; }
  .foto-slot .hapus { position:absolute; top:4px; right:4px; background:rgba(239,68,68,0.8); color:#fff; border:none; border-radius:50%; width:22px; height:22px; font-size:12px; cursor:pointer; }
  textarea.form-control-dark {
    background:#0f172a; border:1px solid #475569; color:#f1f5f9;
    border-radius:8px; padding:10px 12px; width:100%; font-size:14px; resize:none;
  }
  textarea.form-control-dark:focus { border-color:#fbbf24; outline:none; }
  .toggle-group { display:flex; gap:10px; }
  .toggle-item { flex:1; text-align:center; background:#0f172a; border:1px solid #334155; border-radius:8px; padding:12px; cursor:pointer; font-weight:600; }
  .toggle-item.selected-ya { border-color:#ef4444; background:rgba(239,68,68,0.1); color:#fca5a5; }
  .toggle-item.selected-tidak { border-color:#10b981; background:rgba(16,185,129,0.1); color:#6ee7b7; }
  .kendala-box { background:rgba(239,68,68,0.1); border:1px solid #ef4444; border-radius:10px; padding:14px; margin-top:10px; display:none; }
  .submit-bar { position:fixed; bottom:60px; left:0; right:0; padding:12px 16px; background:rgba(15,23,42,0.97); border-top:1px solid #334155; z-index:200; }
  .btn-submit { width:100%; padding:14px; border-radius:12px; border:none; font-weight:700; font-size:16px; background:#f59e0b; color:#0f172a; }
  .btn-submit:disabled { background:#334155; color:#64748b; }
  .gps-bar { display:flex; align-items:center; gap:10px; }
  .alert-box { border-radius:10px; padding:12px 14px; margin-bottom:12px; font-size:13px; }
  .alert-success { background:rgba(16,185,129,0.2); border:1px solid #10b981; color:#6ee7b7; }
  .alert-error { background:rgba(239,68,68,0.2); border:1px solid #ef4444; color:#fca5a5; }
  .pertanyaan-box { background:rgba(99,102,241,0.1); border:1px solid #6366f1; border-radius:10px; padding:14px; font-size:15px; font-weight:600; color:#a5b4fc; margin-bottom:12px; }
  #kameraModal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:999; flex-direction:column; align-items:center; justify-content:center; }
  #kameraModal video { width:100%; max-width:480px; border-radius:12px; }
  #kameraModal .btn-capture { margin-top:16px; background:#fbbf24; color:#0f172a; border:none; border-radius:10px; padding:12px 32px; font-weight:700; font-size:16px; }
  #kameraModal .btn-tutup { position:absolute; top:16px; right:16px; background:rgba(255,255,255,0.2); color:#fff; border:none; border-radius:8px; padding:8px 14px; }
</style>
</head>
<body>

<div class="topbar">
  <a href="{{ route('absensi.index') }}" style="color:#64748b; font-size:20px; text-decoration:none;">←</a>
  <div>
    <div class="topbar-title">Lapor Progress</div>
    <div class="topbar-sub">Sebelum istirahat · {{ now()->format('H:i') }}</div>
  </div>
  <div style="margin-left:auto; font-size:13px; color:#fbbf24;" id="jamLive">--:--</div>
</div>

<div class="content">

  <div id="alertBox" style="display:none;"></div>

  {{-- GPS --}}
  <div class="card-dark">
    <div class="section-label">📍 Lokasi</div>
    <div class="gps-bar">
      <span id="gpsIcon" style="font-size:22px;">📍</span>
      <div style="flex:1;">
        <div style="font-size:13px; font-weight:600;" id="gpsStatus">Mendeteksi lokasi...</div>
        <div style="font-size:11px; color:#64748b;" id="gpsDetail"></div>
      </div>
      <button onclick="refreshGPS()" style="background:#334155; border:none; color:#94a3b8; border-radius:8px; padding:6px 10px; font-size:12px;">Refresh</button>
    </div>
  </div>

  {{-- Foto --}}
  <div class="card-dark">
    <div class="section-label">📸 Foto Lokasi (wajib langsung dari kamera)</div>
    <div class="foto-slot" id="slot0" onclick="bukaKamera()">
      <span class="plus">+</span>
    </div>
  </div>

  {{-- Pertanyaan Progress --}}
  <div class="card-dark">
    <div class="section-label">📋 Progress Hari Ini</div>
    <div class="pertanyaan-box">{{ $pertanyaan }}</div>
    <textarea id="jawabanProgress" class="form-control-dark" rows="3" placeholder="Ketik jawabanmu..."></textarea>
  </div>

  {{-- Kendala --}}
  <div class="card-dark">
    <div class="section-label">⚠️ Ada Kendala?</div>
    <div class="toggle-group">
      <div class="toggle-item" id="toggleTidak" onclick="pilihKendala(0)">Tidak</div>
      <div class="toggle-item" id="toggleYa" onclick="pilihKendala(1)">Ya</div>
    </div>

    <div class="kendala-box" id="kendalaBox">
      <div class="mb-3">
        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:6px;">Apa kendalanya?</label>
        <textarea id="kendalaApa" class="form-control-dark" rows="2" placeholder="Ceritakan kendalanya..."></textarea>
      </div>
      <div>
        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:6px;">Kenapa itu bisa terjadi?</label>
        <textarea id="kendalaKenapa" class="form-control-dark" rows="2" placeholder="Apa penyebabnya..."></textarea>
      </div>
    </div>
  </div>

</div>

{{-- Submit --}}
<div class="submit-bar">
  <button class="btn-submit" id="btnSubmit" disabled onclick="submitLaporan()">
    📤 Kirim Laporan
  </button>
</div>

{{-- Modal Kamera --}}
<div id="kameraModal">
  <button class="btn-tutup" onclick="tutupKamera()">✕ Tutup</button>
  <video id="kameraVideo" autoplay playsinline muted></video>
  <canvas id="kameraCanvas" style="display:none;"></canvas>
  <button class="btn-capture" onclick="jepret()">📷 Jepret</button>
</div>

<script>
let fotoData = null;
let lat = null, lng = null;
let gpsValid = false;
let adaKendala = null;
let kameraStream = null;

setInterval(() => {
  const now = new Date();
  document.getElementById('jamLive').textContent =
    [now.getHours(), now.getMinutes()].map(n=>String(n).padStart(2,'0')).join(':');
}, 1000);

function refreshGPS() {
  gpsValid = false; cekSubmit();
  navigator.geolocation.getCurrentPosition(pos => {
    lat = pos.coords.latitude; lng = pos.coords.longitude;
    fetch('/absensi/cek-gps', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
      body: JSON.stringify({lat,lng,tipe:'siang'})
    }).then(r=>r.json()).then(data => {
      gpsValid = data.valid;
      document.getElementById('gpsIcon').textContent = data.valid ? '✅' : '❌';
      document.getElementById('gpsStatus').innerHTML = data.valid
        ? '<span style="color:#10b981">Lokasi valid ✓</span>'
        : '<span style="color:#ef4444">Di luar radius!</span>';
      document.getElementById('gpsDetail').textContent = data.jarak + ' dari kantor';
      cekSubmit();
    });
  }, () => {
    document.getElementById('gpsStatus').textContent = 'GPS gagal — coba refresh';
  }, {enableHighAccuracy:true, timeout:10000});
}
refreshGPS();

function bukaKamera() {
  document.getElementById('kameraModal').style.display = 'flex';
  navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'},audio:false})
    .then(stream => {
      kameraStream = stream;
      document.getElementById('kameraVideo').srcObject = stream;
    })
    .catch(err => {
      showAlert('error', 'Kamera ditolak atau tidak tersedia. Tutup dan coba lagi.');
      tutupKamera();
    });
}

function tutupKamera() {
  if (kameraStream) kameraStream.getTracks().forEach(t=>t.stop());
  document.getElementById('kameraModal').style.display = 'none';
}

function jepret() {
  const video = document.getElementById('kameraVideo');
  const canvas = document.getElementById('kameraCanvas');
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0);

  ctx.fillStyle = 'rgba(0,0,0,0.6)';
  ctx.fillRect(0, canvas.height-36, canvas.width, 36);
  ctx.fillStyle = '#fff';
  ctx.font = '13px monospace';
  ctx.fillText(new Date().toLocaleString('id-ID'), 8, canvas.height-12);

  fotoData = canvas.toDataURL('image/jpeg', 0.8);

  const slot = document.getElementById('slot0');
  slot.innerHTML = `<img src="${fotoData}">`;
  slot.style.border = '2px solid #10b981';

  tutupKamera();
  cekSubmit();
}

function pilihKendala(val) {
  adaKendala = val;
  document.getElementById('toggleYa').classList.toggle('selected-ya', val === 1);
  document.getElementById('toggleTidak').classList.toggle('selected-tidak', val === 0);
  document.getElementById('kendalaBox').style.display = val === 1 ? 'block' : 'none';
  cekSubmit();
}

function cekSubmit() {
  const jawabanOk = document.getElementById('jawabanProgress').value.trim().length > 0;
  const kendalaLengkap = adaKendala !== 1 || (
    document.getElementById('kendalaApa').value.trim() &&
    document.getElementById('kendalaKenapa').value.trim()
  );
  const btn = document.getElementById('btnSubmit');
  btn.disabled = !(gpsValid && fotoData && jawabanOk && adaKendala !== null && kendalaLengkap);
}

document.getElementById('jawabanProgress').addEventListener('input', cekSubmit);
document.getElementById('kendalaApa').addEventListener('input', cekSubmit);
document.getElementById('kendalaKenapa').addEventListener('input', cekSubmit);

function submitLaporan() {
  const btn = document.getElementById('btnSubmit');
  btn.disabled = true;
  btn.textContent = 'Mengirim...';

  const body = new FormData();
  body.append('_token', '{{ csrf_token() }}');
  body.append('lat', lat);
  body.append('lng', lng);
  body.append('foto', fotoData);
  body.append('jawaban_progress', document.getElementById('jawabanProgress').value);
  body.append('ada_kendala', adaKendala);
  if (adaKendala === 1) {
    body.append('kendala_apa', document.getElementById('kendalaApa').value);
    body.append('kendala_kenapa', document.getElementById('kendalaKenapa').value);
  }

  fetch('{{ route("absensi.lapor-progress") }}', {
    method:'POST', headers:{'Accept':'application/json'}, body
  })
  .then(r=>r.json())
  .then(data => {
    if (data.success) {
      showAlert('success', data.message);
      setTimeout(() => window.location.href = data.redirect, 1500);
    } else {
      showAlert('error', data.message);
      btn.disabled = false;
      btn.textContent = '📤 Kirim Laporan';
    }
  })
  .catch(err => {
    showAlert('error', 'Gagal mengirim, coba lagi.');
    btn.disabled = false;
    btn.textContent = '📤 Kirim Laporan';
  });
}

function showAlert(type, msg) {
  const box = document.getElementById('alertBox');
  box.className = 'alert-box alert-' + type;
  box.textContent = msg;
  box.style.display = 'block';
  window.scrollTo(0,0);
}
</script>
</body>
</html>
