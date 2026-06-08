{{-- FILE: resources/views/absensi/form-masuk.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Absen Masuk</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  * { box-sizing:border-box; }
  body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; margin:0; padding:0; }
  .topbar { background:#1e293b; border-bottom:1px solid #334155; padding:14px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
  .topbar-title { font-weight:700; color:#fbbf24; font-size:16px; }
  .content { padding:16px; padding-bottom:140px; }
  .card-dark { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:16px; margin-bottom:14px; }
  .section-label { color:#94a3b8; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:10px; }
  #videoBox { position:relative; width:100%; border-radius:12px; overflow:hidden; background:#000; aspect-ratio:3/4; }
  #video { width:100%; height:100%; object-fit:cover; display:block; }
  #canvas { display:none; }
  #fotoPreview { width:100%; border-radius:12px; display:none; }
  .timestamp-overlay { position:absolute; bottom:10px; left:10px; right:10px; display:flex; justify-content:space-between; }
  .ts-badge { background:rgba(0,0,0,0.7); color:#fff; font-size:11px; padding:4px 8px; border-radius:6px; }
  .gps-card { display:flex; align-items:center; gap:12px; }
  .jarak-ok { color:#10b981; }
  .jarak-jauh { color:#ef4444; }
  .btn-foto { width:100%; padding:12px; border-radius:10px; border:none; font-weight:600; font-size:14px; }
  .btn-ambil { background:#fbbf24; color:#0f172a; }
  .btn-ulang { background:#334155; color:#e2e8f0; }

  /* Kode Absen Input */
  .kode-input-wrap { position:relative; }
  .kode-input { background:#0f172a; border:2px solid #475569; color:#fbbf24; border-radius:10px; padding:14px; width:100%; font-size:28px; font-weight:800; letter-spacing:8px; text-align:center; text-transform:uppercase; }
  .kode-input:focus { border-color:#fbbf24; outline:none; }
  .kode-input.valid { border-color:#10b981; color:#10b981; }
  .kode-input.invalid { border-color:#ef4444; color:#ef4444; }
  .kode-status { font-size:12px; margin-top:6px; text-align:center; }

  .submit-bar { position:fixed; bottom:60px; left:0; right:0; padding:12px 16px; background:rgba(15,23,42,0.97); border-top:1px solid #334155; z-index:200; }
  .btn-submit { width:100%; padding:14px; border-radius:12px; border:none; font-weight:700; font-size:16px; background:#10b981; color:#fff; }
  .btn-submit:disabled { background:#334155; color:#64748b; }
  .btn-submit.setengah { background:#f59e0b; color:#0f172a; }
  .alert-box { border-radius:10px; padding:12px 14px; margin-bottom:12px; font-size:13px; }
  .alert-success { background:rgba(16,185,129,0.2); border:1px solid #10b981; color:#6ee7b7; }
  .alert-error { background:rgba(239,68,68,0.2); border:1px solid #ef4444; color:#fca5a5; }
  .alert-warning { background:rgba(245,158,11,0.2); border:1px solid #f59e0b; color:#fcd34d; }
  .spinner { display:inline-block; width:16px; height:16px; border:2px solid #fff; border-top-color:transparent; border-radius:50%; animation:spin .6s linear infinite; margin-right:6px; }
  @keyframes spin { to { transform:rotate(360deg); } }
</style>
</head>
<body>

<div class="topbar">
  <a href="{{ route('absensi.index') }}" style="color:#64748b; font-size:20px; text-decoration:none;">←</a>
  <div>
    <div class="topbar-title">Absen Masuk</div>
    <div style="color:#64748b; font-size:12px;">{{ now()->translatedFormat('l, d F Y') }}</div>
  </div>
  <div style="margin-left:auto; font-size:13px; color:#fbbf24;" id="jamLive">--:--</div>
</div>

<div class="content">

  <div id="alertBox" style="display:none;"></div>

  @if($setengahHari)
  <div class="alert-box alert-warning">
    ⚠️ Kamu absen di atas jam 10:00 — akan tercatat sebagai <strong>Setengah Hari</strong>.<br>
    <small>Gaji & uang makan dihitung 50%</small>
  </div>
  @endif

  {{-- 1. Kode Absen --}}
  <div class="card-dark">
    <div class="section-label">🔑 Kode Absen Hari Ini</div>
    <div style="color:#64748b; font-size:12px; margin-bottom:10px;">
      Cek kode di WhatsApp kamu yang dikirim jam 06:30
    </div>
    <div class="kode-input-wrap">
      <input type="text" id="inputKode" class="kode-input"
             placeholder="- - - - - -" maxlength="6"
             oninput="this.value=this.value.toUpperCase(); cekKode(this.value)">
    </div>
    <div class="kode-status" id="kodeStatus" style="color:#64748b;">
      Masukkan 6 karakter kode dari WhatsApp
    </div>
  </div>

  {{-- 2. Kamera --}}
  <div class="card-dark">
    <div class="section-label">📸 Selfie</div>
    <div id="videoBox">
      <video id="video" autoplay playsinline muted></video>
      <div class="timestamp-overlay">
        <span class="ts-badge" id="tsJarak">📍 --</span>
        <span class="ts-badge" id="tsJam">--:--:--</span>
      </div>
    </div>
    <img id="fotoPreview" src="" alt="foto">
    <canvas id="canvas"></canvas>
    <div class="d-flex gap-2 mt-3">
      <button class="btn-foto btn-ambil flex-fill" id="btnAmbil" onclick="ambilFoto()">📷 Ambil Foto</button>
      <button class="btn-foto btn-ulang flex-fill" id="btnUlang" onclick="ulangFoto()" style="display:none;">🔄 Ulang</button>
    </div>
  </div>

  {{-- 3. GPS --}}
  <div class="card-dark">
    <div class="section-label">📍 Lokasi GPS</div>
    <div class="gps-card">
      <div style="font-size:24px;" id="gpsIcon">📍</div>
      <div style="flex:1;">
        <div style="font-size:13px; font-weight:600;" id="gpsStatus">Mendeteksi lokasi...</div>
        <div style="font-size:11px; color:#64748b;" id="gpsDetail">Harap izinkan akses lokasi</div>
      </div>
      <button onclick="refreshGPS()" style="background:#334155; border:none; color:#94a3b8; border-radius:8px; padding:6px 12px; font-size:12px;">Refresh</button>
    </div>
  </div>

</div>

{{-- Tombol Submit --}}
<div class="submit-bar">
  @if($setengahHari)
  <div style="text-align:center; color:#fcd34d; font-size:11px; margin-bottom:6px;">⚠️ Akan tercatat SETENGAH HARI</div>
  @endif
  <button class="btn-submit {{ $setengahHari ? 'setengah' : '' }}" id="btnSubmit" disabled onclick="submitAbsen()">
    {{ $setengahHari ? '🟡 Absen Masuk (Setengah Hari)' : '🟢 Absen Masuk Sekarang' }}
  </button>
  <div id="syaratInfo" style="text-align:center; color:#475569; font-size:11px; margin-top:6px;">
    Lengkapi kode, foto, dan GPS untuk absen
  </div>
</div>

<form id="formAbsen" method="POST" action="{{ route('absensi.masuk') }}" style="display:none;">
  @csrf
  <input type="hidden" name="foto" id="inputFoto">
  <input type="hidden" name="kode" id="inputKodeHidden">
  <input type="hidden" name="lat" id="inputLat">
  <input type="hidden" name="lng" id="inputLng">
</form>

<script>
let fotoData = null;
let lat = null, lng = null;
let gpsValid = false;
let kodeValid = false;
const video = document.getElementById('video');
const canvas = document.getElementById('canvas');

// Jam live
setInterval(() => {
  const now = new Date();
  const jam = [now.getHours(),now.getMinutes(),now.getSeconds()].map(n=>String(n).padStart(2,'0')).join(':');
  document.getElementById('jamLive').textContent = jam.substring(0,5);
  document.getElementById('tsJam').textContent = jam;
}, 1000);

// Buka kamera otomatis
async function bukaKamera() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'user'},audio:false});
    video.srcObject = stream;
  } catch(e) {
    showAlert('error', 'Tidak bisa akses kamera: ' + e.message);
  }
}
bukaKamera();

// GPS
function refreshGPS() {
  gpsValid = false; cekSubmit();
  navigator.geolocation.getCurrentPosition(pos => {
    lat = pos.coords.latitude;
    lng = pos.coords.longitude;
    document.getElementById('inputLat').value = lat;
    document.getElementById('inputLng').value = lng;

    fetch('/absensi/cek-gps', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
      body: JSON.stringify({lat, lng})
    })
    .then(r=>r.json())
    .then(data => {
      gpsValid = data.valid;
      document.getElementById('gpsIcon').textContent = data.valid ? '✅' : '❌';
      document.getElementById('gpsStatus').innerHTML = data.valid
        ? '<span class="jarak-ok">Lokasi valid ✓</span>'
        : '<span class="jarak-jauh">Di luar radius!</span>';
      document.getElementById('gpsDetail').textContent = data.jarak + ' dari kantor';
      document.getElementById('tsJarak').textContent = '📍 ' + data.jarak;
      cekSubmit();
    });
  }, () => {
    document.getElementById('gpsStatus').textContent = 'GPS tidak terdeteksi';
    document.getElementById('gpsDetail').textContent = 'Pastikan GPS aktif lalu tekan Refresh';
  }, {enableHighAccuracy:true, timeout:10000});
}
refreshGPS();

// Validasi kode via AJAX
let kodeTimer = null;
function cekKode(kode) {
  clearTimeout(kodeTimer);
  const input = document.getElementById('inputKode');
  const status = document.getElementById('kodeStatus');

  if (kode.length < 6) {
    input.className = 'kode-input';
    status.textContent = 'Masukkan 6 karakter kode dari WhatsApp';
    status.style.color = '#64748b';
    kodeValid = false;
    cekSubmit();
    return;
  }

  status.textContent = 'Memvalidasi kode...';
  status.style.color = '#f59e0b';

  kodeTimer = setTimeout(() => {
    fetch('/absensi/validasi-kode', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
      body: JSON.stringify({kode})
    })
    .then(r=>r.json())
    .then(data => {
      kodeValid = data.valid;
      document.getElementById('inputKodeHidden').value = kode;
      if (data.valid) {
        input.className = 'kode-input valid';
        status.textContent = '✅ Kode valid!';
        status.style.color = '#10b981';
      } else {
        input.className = 'kode-input invalid';
        status.textContent = '❌ Kode salah! Cek WA kamu.';
        status.style.color = '#ef4444';
      }
      cekSubmit();
    });
  }, 500);
}

// Foto
function ambilFoto() {
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0);
  ctx.fillStyle = 'rgba(0,0,0,0.6)';
  ctx.fillRect(0, canvas.height-40, canvas.width, 40);
  ctx.fillStyle = '#fff';
  ctx.font = '14px monospace';
  ctx.fillText(new Date().toLocaleString('id-ID'), 10, canvas.height-15);
  fotoData = canvas.toDataURL('image/jpeg', 0.85);
  document.getElementById('fotoPreview').src = fotoData;
  document.getElementById('fotoPreview').style.display = 'block';
  document.getElementById('videoBox').style.display = 'none';
  document.getElementById('btnAmbil').style.display = 'none';
  document.getElementById('btnUlang').style.display = '';
  if (video.srcObject) video.srcObject.getTracks().forEach(t=>t.stop());
  cekSubmit();
}

function ulangFoto() {
  fotoData = null;
  document.getElementById('fotoPreview').style.display = 'none';
  document.getElementById('videoBox').style.display = 'block';
  document.getElementById('btnAmbil').style.display = '';
  document.getElementById('btnUlang').style.display = 'none';
  bukaKamera();
  cekSubmit();
}

function cekSubmit() {
  const btn = document.getElementById('btnSubmit');
  const info = document.getElementById('syaratInfo');
  const siap = kodeValid && fotoData && gpsValid;
  btn.disabled = !siap;

  // Update info
  const missing = [];
  if (!kodeValid) missing.push('kode WA');
  if (!fotoData) missing.push('foto selfie');
  if (!gpsValid) missing.push('GPS valid');
  info.textContent = siap ? '' : 'Lengkapi: ' + missing.join(', ');
}

function submitAbsen() {
  document.getElementById('inputFoto').value = fotoData;
  const btn = document.getElementById('btnSubmit');
  btn.innerHTML = '<span class="spinner"></span> Menyimpan...';
  btn.disabled = true;

  fetch('{{ route("absensi.masuk") }}', {
    method:'POST',
    headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
    body: new FormData(document.getElementById('formAbsen'))
  })
  .then(r=>r.json())
  .then(data => {
    if (data.success) {
      showAlert('success', data.message);
      setTimeout(() => window.location.href = data.redirect, 1500);
    } else {
      showAlert('error', data.message);
      btn.innerHTML = '{{ $setengahHari ? "🟡 Absen Masuk (Setengah Hari)" : "🟢 Absen Masuk Sekarang" }}';
      btn.disabled = false;
    }
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