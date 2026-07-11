@extends('layouts.app')

@section('page-title', 'Absen Pulang')

@section('sidebar-menu')
    @if(auth()->user()->level == 1)
        @include('partials.sidebar-owner')
    @else
        @include('partials.sidebar-pipeline')
    @endif
@endsection

@section('bottom-nav')
    @include('partials.bottomnav-karyawan')
@endsection

@section('content')
<div style="max-width:480px;margin:0 auto;padding-bottom:120px;" x-data="absenPulang()" x-init="init()">

    {{-- Header --}}
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
        <a href="{{ route('absensi.index') }}" style="display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,0.05);text-decoration:none;font-size:18px;">←</a>
        <div>
            <h2 style="font-size:17px;font-weight:700;margin:0;" :style="darkMode ? 'color:#F1F5F9' : 'color:#1E293B'">Absen Pulang</h2>
            <p style="font-size:12px;color:#94A3B8;margin:0;">{{ now()->isoFormat('dddd, D MMMM Y · HH:mm') }}</p>
        </div>
    </div>

    {{-- Info jam masuk tadi --}}
    <div class="stat-card" style="margin-bottom:16px;padding:14px 16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
            <div>
                <div style="font-size:11px;color:#64748B;margin-bottom:3px;">JAM MASUK TADI</div>
                <div style="font-size:20px;font-weight:700;color:#10B981;">{{ substr($absenHariIni->jam_masuk, 0, 5) }}</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:11px;color:#64748B;margin-bottom:3px;">DURASI KERJA</div>
                <div style="font-size:16px;font-weight:700;" :style="darkMode ? 'color:#E2E8F0' : 'color:#1E293B'" x-text="durasiKerja"></div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:11px;color:#64748B;margin-bottom:3px;">SEKARANG</div>
                <div style="font-size:16px;font-weight:700;color:#C9A84C;" x-text="jamSekarang"></div>
            </div>
        </div>
    </div>

    {{-- Alert GPS di luar radius --}}
    <div x-show="gpsDiLuar" style="padding:14px 16px;border-radius:12px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);margin-bottom:16px;">
        <div style="font-size:13px;font-weight:600;color:#EF4444;margin-bottom:4px;">❌ Lokasi di luar radius!</div>
        <div style="font-size:12px;color:#94A3B8;" x-text="lokasiDetail"></div>
        <button @click="getGPS()" type="button" style="margin-top:8px;padding:6px 14px;border-radius:8px;border:1px solid rgba(239,68,68,0.4);background:transparent;color:#EF4444;font-size:12px;cursor:pointer;">
            🔄 Refresh GPS
        </button>
    </div>

    {{-- Kamera Preview --}}
    <div style="position:relative;border-radius:20px;overflow:hidden;background:#0F1117;margin-bottom:16px;aspect-ratio:3/4;max-height:440px;">
        <video x-ref="video" x-show="!fotoCaptured" autoplay playsinline muted
               style="width:100%;height:100%;object-fit:cover;display:block;"></video>

        <img x-show="fotoCaptured" :src="fotoPreview"
             style="width:100%;height:100%;object-fit:cover;display:block;">

        <div style="position:absolute;bottom:0;left:0;right:0;padding:16px;background:linear-gradient(transparent,rgba(0,0,0,0.8));">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <span x-text="lokasiIcon" style="font-size:16px;"></span>
                    <span x-text="lokasiText" style="font-size:12px;color:white;font-weight:600;"></span>
                </div>
                <div style="font-size:12px;color:rgba(255,255,255,0.7);" x-text="jamSekarang"></div>
            </div>
        </div>

        <div x-show="kameraError" style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#0F1117;padding:24px;text-align:center;">
            <div style="font-size:40px;margin-bottom:12px;">📷</div>
            <div style="font-size:14px;font-weight:600;color:white;margin-bottom:8px;">Kamera tidak tersedia</div>
            <div style="font-size:12px;color:#94A3B8;" x-text="kameraErrorMsg"></div>
        </div>
    </div>

    <canvas x-ref="canvas" style="display:none;"></canvas>

    {{-- Tombol Capture / Retake --}}
    <div style="display:flex;gap:10px;margin-bottom:16px;">
        <button x-show="!fotoCaptured" @click="capture()" type="button"
                :disabled="kameraError || !kameraReady"
                style="flex:1;padding:16px;border-radius:14px;font-size:15px;font-weight:700;border:none;cursor:pointer;color:#0F1117;background:linear-gradient(135deg,#C9A84C,#A8872E);min-height:54px;"
                :style="(kameraError || !kameraReady) ? 'opacity:0.5;cursor:not-allowed;' : ''">
            📸 Ambil Foto Selfie
        </button>
        <button x-show="fotoCaptured" @click="retake()" type="button"
                style="flex:1;padding:16px;border-radius:14px;font-size:15px;font-weight:600;border:1.5px solid rgba(201,168,76,0.4);cursor:pointer;background:transparent;color:#C9A84C;min-height:54px;">
            🔄 Foto Ulang
        </button>
    </div>

    {{-- Warning pulang awal --}}
    <div x-show="pulangAwal" style="padding:14px 16px;border-radius:12px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);margin-bottom:16px;">
        <div style="font-size:13px;font-weight:600;color:#F59E0B;margin-bottom:4px;">⚠️ Pulang lebih awal dari jam normal</div>
        <div style="font-size:12px;color:#94A3B8;">Jika kurang dari 50% jam kerja, akan dihitung Setengah Hari.</div>
    </div>

    {{-- Form Submit --}}
    <form method="POST" action="{{ route('absensi.pulang') }}" id="formAbsenPulang">
        @csrf
        <input type="hidden" name="foto" x-model="fotoBase64">
        <input type="hidden" name="lat" x-model="lat">
        <input type="hidden" name="lng" x-model="lng">

        <button type="submit"
                :disabled="!fotoCaptured || !gpsValid || sedangKirim"
                @click.prevent="submitAbsen()"
                style="width:100%;padding:18px;border-radius:14px;font-size:16px;font-weight:700;border:none;cursor:pointer;color:white;background:linear-gradient(135deg,#3B82F6,#1D4ED8);min-height:58px;"
                :style="(!fotoCaptured || !gpsValid || sedangKirim) ? 'opacity:0.4;cursor:not-allowed;' : ''">
            <span x-show="!sedangKirim">🏠 Absen Pulang Sekarang</span>
            <span x-show="sedangKirim">⏳ Menyimpan...</span>
        </button>

        <div x-show="!fotoCaptured || !gpsValid" style="text-align:center;margin-top:10px;font-size:12px;color:#64748B;">
            <span x-show="!fotoCaptured">Ambil foto selfie dulu · </span>
            <span x-show="!gpsValid">Menunggu GPS valid...</span>
        </div>
    </form>

</div>

<script>
function absenPulang() {
    const jamMasuk = '{{ substr($absenHariIni->jam_masuk, 0, 5) }}';
    const jamPulangNormal = '{{ auth()->user()->jam_pulang ? substr(auth()->user()->jam_pulang, 0, 5) : "16:30" }}';
    const csrfToken = '{{ csrf_token() }}';

    return {
        fotoBase64: '',
        fotoPreview: '',
        fotoCaptured: false,
        kameraError: false,
        kameraErrorMsg: '',
        kameraReady: false,
        lat: null,
        lng: null,
        gpsValid: false,
        gpsDiLuar: false,
        lokasiText: 'Mendapatkan lokasi...',
        lokasiDetail: '',
        lokasiIcon: '🔄',
        jamSekarang: '',
        durasiKerja: '-',
        pulangAwal: false,
        stream: null,
        sedangKirim: false,

        async init() {
            this.updateJam();
            setInterval(() => {
                this.updateJam();
                this.hitungDurasi();
                this.cekPulangAwal();
            }, 1000);
            await this.startCamera();
            this.getGPS();
        },

        updateJam() {
            const now = new Date();
            this.jamSekarang = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        },

        hitungDurasi() {
            const [jM, mM] = jamMasuk.split(':').map(Number);
            const now = new Date();
            const totalMenit = (now.getHours() - jM) * 60 + (now.getMinutes() - mM);
            if (totalMenit < 0) { this.durasiKerja = '-'; return; }
            const jam = Math.floor(totalMenit / 60);
            const mnt = totalMenit % 60;
            this.durasiKerja = `${jam}j ${mnt}m`;
        },

        cekPulangAwal() {
            const now = new Date();
            const [jP, mP] = jamPulangNormal.split(':').map(Number);
            const nowMenit = now.getHours() * 60 + now.getMinutes();
            const normalMenit = jP * 60 + mP;
            this.pulangAwal = nowMenit < normalMenit;
        },

        async startCamera() {
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 720 }, height: { ideal: 960 } },
                    audio: false
                });
                this.$refs.video.srcObject = this.stream;
                this.$refs.video.onloadedmetadata = () => {
                    this.$refs.video.play();
                    this.kameraReady = true;
                };
            } catch (err) {
                this.kameraError = true;
                this.kameraErrorMsg = err.name === 'NotAllowedError'
                    ? 'Izin kamera ditolak. Silakan izinkan akses kamera di browser.'
                    : 'Kamera tidak dapat diakses: ' + err.message;
            }
        },

        capture() {
            const video  = this.$refs.video;
            const canvas = this.$refs.canvas;
            canvas.width  = video.videoWidth  || 720;
            canvas.height = video.videoHeight || 960;
            const ctx = canvas.getContext('2d');
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            // Timestamp di foto
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.fillStyle = 'rgba(0,0,0,0.6)';
            ctx.fillRect(0, canvas.height - 40, canvas.width, 40);
            ctx.fillStyle = '#fff';
            ctx.font = '14px monospace';
            ctx.fillText(new Date().toLocaleString('id-ID'), 10, canvas.height - 15);

            this.fotoBase64  = canvas.toDataURL('image/jpeg', 0.85);
            this.fotoPreview = this.fotoBase64;
            this.fotoCaptured = true;
            if (this.stream) this.stream.getTracks().forEach(t => t.stop());
        },

        retake() {
            this.fotoCaptured = false;
            this.fotoBase64   = '';
            this.fotoPreview  = '';
            this.startCamera();
        },

        getGPS() {
            this.lokasiText = 'Mendapatkan lokasi...';
            this.lokasiIcon = '🔄';
            this.gpsValid   = false;
            this.gpsDiLuar  = false;

            if (!navigator.geolocation) {
                this.lokasiText = 'GPS tidak didukung';
                this.lokasiIcon = '❌';
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.lat = pos.coords.latitude;
                    this.lng = pos.coords.longitude;

                    // Cek GPS ke server
                    fetch('/absensi/cek-gps', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ lat: this.lat, lng: this.lng })
                    })
                    .then(r => r.json())
                    .then(data => {
                        this.gpsValid  = data.valid;
                        this.gpsDiLuar = !data.valid;
                        this.lokasiText   = data.valid ? `Lokasi valid ✓ (${data.jarak})` : `Di luar radius!`;
                        this.lokasiDetail = `${data.jarak} dari kantor (maks 100m)`;
                        this.lokasiIcon   = data.valid ? '✅' : '❌';
                    });
                },
                () => {
                    this.lokasiText = 'GPS tidak tersedia';
                    this.lokasiIcon = '⚠️';
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        },

        submitAbsen() {
            if (!this.fotoCaptured || !this.gpsValid) return;
            if (this.sedangKirim) return;
            this.sedangKirim = true;

            var self = this;
            var form = document.getElementById('formAbsenPulang');
            var formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.success) {
                    window.location.href = data.redirect ? data.redirect : '/absensi';
                } else {
                    self.sedangKirim = false;
                    alert(data && data.message ? data.message : 'Absen gagal, coba lagi.');
                }
            })
            .catch(function () {
                self.sedangKirim = false;
                alert('Koneksi bermasalah. Cek internet lalu coba lagi.');
            });
        }
    }
}
</script>
@endsection