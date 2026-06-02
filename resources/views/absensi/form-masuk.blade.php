@extends('layouts.app')

@section('page-title', 'Absen Masuk')

@section('sidebar-menu')
    @include('partials.sidebar-owner')
@endsection

@section('bottom-nav')
    @include('partials.bottomnav-owner')
@endsection

@section('content')
<div style="max-width:480px;margin:0 auto;" x-data="absenMasuk()" x-init="init()">

    {{-- Header --}}
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
        <a href="{{ route('absensi.index') }}" style="display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,0.05);text-decoration:none;font-size:18px;">←</a>
        <div>
            <h2 style="font-size:17px;font-weight:700;margin:0;" :style="darkMode ? 'color:#F1F5F9' : 'color:#1E293B'">Absen Masuk</h2>
            <p style="font-size:12px;color:#94A3B8;margin:0;">{{ now()->isoFormat('dddd, D MMMM Y · HH:mm') }}</p>
        </div>
    </div>

    {{-- Kamera Preview --}}
    <div style="position:relative;border-radius:20px;overflow:hidden;background:#0F1117;margin-bottom:16px;aspect-ratio:3/4;max-height:440px;">
        {{-- Live camera --}}
        <video x-ref="video" x-show="!fotoCaptured" autoplay playsinline muted
               style="width:100%;height:100%;object-fit:cover;display:block;"></video>

        {{-- Preview foto --}}
        <img x-show="fotoCaptured" :src="fotoPreview"
             style="width:100%;height:100%;object-fit:cover;display:block;">

        {{-- Overlay info GPS --}}
        <div style="position:absolute;bottom:0;left:0;right:0;padding:16px;background:linear-gradient(transparent,rgba(0,0,0,0.8));">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <span x-text="lokasiIcon" style="font-size:16px;"></span>
                    <span x-text="lokasiText" style="font-size:12px;color:white;font-weight:600;"></span>
                </div>
                <div style="font-size:12px;color:rgba(255,255,255,0.7);" x-text="jamSekarang"></div>
            </div>
        </div>

        {{-- Error kamera --}}
        <div x-show="kameraError" style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#0F1117;padding:24px;text-align:center;">
            <div style="font-size:40px;margin-bottom:12px;">📷</div>
            <div style="font-size:14px;font-weight:600;color:white;margin-bottom:8px;">Kamera tidak tersedia</div>
            <div style="font-size:12px;color:#94A3B8;" x-text="kameraErrorMsg"></div>
        </div>
    </div>

    {{-- Canvas tersembunyi untuk capture --}}
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

    {{-- Info GPS --}}
    <div class="stat-card" style="margin-bottom:16px;padding:14px 16px;">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span style="font-size:18px;">📍</span>
            <div style="flex:1;">
                <div style="font-size:12px;font-weight:600;margin-bottom:2px;" :style="darkMode ? 'color:#E2E8F0' : 'color:#1E293B'" x-text="lokasiText"></div>
                <div style="font-size:11px;color:#64748B;" x-show="latitude">
                    Koordinat: <span x-text="latitude ? latitude.toFixed(5) + ', ' + longitude.toFixed(5) : '-'"></span>
                </div>
            </div>
            <button @click="getGPS()" type="button"
                    style="padding:8px 14px;border-radius:8px;font-size:12px;font-weight:600;border:1.5px solid rgba(201,168,76,0.4);cursor:pointer;background:transparent;color:#C9A84C;">
                Refresh GPS
            </button>
        </div>
    </div>

    {{-- Form Submit --}}
    <form method="POST" action="{{ route('absensi.absen-masuk') }}" id="formAbsenMasuk">
        @csrf
        <input type="hidden" name="foto" x-model="fotoBase64">
        <input type="hidden" name="latitude" x-model="latitude">
        <input type="hidden" name="longitude" x-model="longitude">

        <button type="submit"
                :disabled="!fotoCaptured || !latitude"
                @click.prevent="submitAbsen()"
                style="width:100%;padding:18px;border-radius:14px;font-size:16px;font-weight:700;border:none;cursor:pointer;color:#0F1117;background:linear-gradient(135deg,#C9A84C,#A8872E);min-height:58px;letter-spacing:0.5px;"
                :style="(!fotoCaptured || !latitude) ? 'opacity:0.4;cursor:not-allowed;' : ''">
            ✅ Absen Masuk Sekarang
        </button>

        <div x-show="!fotoCaptured || !latitude" style="text-align:center;margin-top:10px;font-size:12px;color:#64748B;">
            <span x-show="!fotoCaptured">Ambil foto selfie dulu · </span>
            <span x-show="!latitude">Menunggu GPS...</span>
        </div>
    </form>

</div>

<script>
function absenMasuk() {
    return {
        fotoBase64: '',
        fotoPreview: '',
        fotoCaptured: false,
        kameraError: false,
        kameraErrorMsg: '',
        kameraReady: false,
        latitude: null,
        longitude: null,
        lokasiText: 'Mendapatkan lokasi...',
        lokasiIcon: '🔄',
        jamSekarang: '',
        stream: null,

        async init() {
            this.updateJam();
            setInterval(() => this.updateJam(), 1000);
            await this.startCamera();
            this.getGPS();
        },

        updateJam() {
            const now = new Date();
            this.jamSekarang = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
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
            // Mirror selfie
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            this.fotoBase64  = canvas.toDataURL('image/jpeg', 0.85);
            this.fotoPreview = this.fotoBase64;
            this.fotoCaptured = true;
            // Stop camera stream
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
            if (!navigator.geolocation) {
                this.lokasiText = 'GPS tidak didukung';
                this.lokasiIcon = '❌';
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.latitude  = pos.coords.latitude;
                    this.longitude = pos.coords.longitude;
                    const jarak = this.hitungJarak(this.latitude, this.longitude, -6.3269, 106.6882);
                    if (jarak <= 100) {
                        this.lokasiText = `Di kantor (${Math.round(jarak)} m dari workshop)`;
                        this.lokasiIcon = '✅';
                    } else {
                        this.lokasiText = `${Math.round(jarak)} m dari kantor`;
                        this.lokasiIcon = '📍';
                    }
                },
                (err) => {
                    this.lokasiText = 'GPS tidak tersedia — gunakan WiFi kantor';
                    this.lokasiIcon = '⚠️';
                    // Set dummy coords agar bisa absen
                    this.latitude  = -6.3269;
                    this.longitude = 106.6882;
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        },

        hitungJarak(lat1, lng1, lat2, lng2) {
            const R = 6371000;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLng = (lng2 - lng1) * Math.PI / 180;
            const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLng/2)**2;
            return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        },

        submitAbsen() {
            if (!this.fotoCaptured || !this.latitude) return;
            document.getElementById('formAbsenMasuk').submit();
        }
    }
}
</script>
@endsection
