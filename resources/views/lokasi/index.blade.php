@extends('layouts.app')
@section('title', 'Profil Lokasi')
@section('page-title', 'Profil Lokasi')
@section('sidebar-menu')
    @if(auth()->user()->level == 1)
        @include('partials.sidebar-owner')
    @else
        @include('partials.sidebar-pipeline')
    @endif
@endsection
@section('content')
<style>
* { box-sizing:border-box; }
.lk-wrap { max-width:600px; margin:0 auto; padding:14px 12px 40px; }
.lk-title { font-size:18px; font-weight:700; color:#fbbf24; margin:0 0 2px; }
.lk-sub { font-size:12px; color:#64748b; margin:0 0 14px; }
.lk-card { background:#1e293b; border-radius:12px; padding:14px; margin-bottom:12px; }
.lk-field { margin-bottom:12px; }
.lk-field label { display:block; font-size:12px; color:#94a3b8; margin-bottom:5px; }
.lk-field input, .lk-field select, .lk-field textarea {
    width:100%; background:#0f172a; border:1px solid #334155; border-radius:8px;
    padding:11px 10px; color:#f1f5f9; font-size:14px; outline:none; min-height:48px; }
.lk-field textarea { min-height:70px; resize:vertical; }
.lk-field input:focus, .lk-field select:focus, .lk-field textarea:focus { border-color:#fbbf24; }
.btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; border:none; border-radius:10px; padding:13px; min-height:48px; font-size:14px; font-weight:700; cursor:pointer; width:100%; text-decoration:none; }
.btn-gold { background:#fbbf24; color:#0f172a; }
.btn-blue { background:#3b82f6; color:#fff; }
.gps-box { background:#0f172a; border:1px dashed #334155; border-radius:8px; padding:10px; font-size:12px; color:#cbd5e1; margin-top:8px; }
.warn { background:rgba(251,191,36,0.12); border:1px solid rgba(251,191,36,0.3); border-radius:8px; padding:10px; font-size:12px; color:#fbbf24; margin-top:8px; display:none; }
</style>

@php $lv = auth()->user()->level; $bisaGps = in_array($lv, [1,3]); @endphp

<div class="lk-wrap">
    <a href="{{ url('/pipeline/'.$lead->id) }}" style="font-size:13px;color:#64748b;text-decoration:none;">← Kembali ke Lead</a>
    <h1 class="lk-title" style="margin-top:8px;">Profil Lokasi</h1>
    <p class="lk-sub">{{ $lead->nama_customer }} · Lead #{{ $lead->id }}. Admin isi alamat, share-loc & link Maps. Surveyor ambil GPS + pastikan jarak saat survei.</p>

    @if(session('success'))
    <div style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);border-radius:8px;padding:10px;font-size:13px;color:#6ee7b7;margin-bottom:12px;">✅ {{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ url('/lokasi/'.$lead->id) }}">
        @csrf

        {{-- Alamat & Peta (admin isi) --}}
        <div class="lk-card">
            <div style="font-size:13px;font-weight:700;color:#fbbf24;margin-bottom:10px;">Alamat & Peta</div>
            <div class="lk-field">
                <label>Area / Perumahan</label>
                <input type="text" name="lokasi_area" value="{{ $lead->lokasi_area ?? '' }}" placeholder="mis. Perum Alam Sutera blok C">
            </div>
            <div class="lk-field">
                <label>Nomor rumah & patokan</label>
                <input type="text" name="lokasi_patokan" value="{{ $lead->lokasi_patokan ?? '' }}" placeholder="mis. No.12, depan masjid, cat hijau">
            </div>
            <div class="lk-field">
                <label>Share-loc dari customer (link)</label>
                <input type="text" id="shareLok" name="lokasi_sharelok" value="{{ $lead->lokasi_sharelok ?? '' }}" placeholder="tempel link share lokasi WA customer">
            </div>
            <div class="lk-field">
                <label>Link Google Maps</label>
                <input type="text" id="mapsLink" name="lokasi_maps_link" value="{{ $lead->lokasi_maps_link ?? '' }}" placeholder="tempel link Google Maps">
            </div>
            <a href="#" id="bukaMaps" target="_blank" class="btn btn-blue" style="margin-top:4px;">🗺️ Buka di Google Maps</a>
        </div>

        {{-- GPS — hanya surveyor & owner --}}
        @if($bisaGps)
        <div class="lk-card">
            <div style="font-size:13px;font-weight:700;color:#fbbf24;margin-bottom:6px;">Titik GPS (ambil saat di lokasi)</div>
            <div style="font-size:11px;color:#64748b;margin-bottom:8px;">Tekan saat sudah TIBA di lokasi customer — jadi bukti kunjungan.</div>
            <button type="button" id="btnGps" class="btn btn-gold">📍 Ambil GPS Sekarang</button>
            <input type="hidden" id="lat" name="lokasi_lat" value="{{ $lead->lokasi_lat ?? '' }}">
            <input type="hidden" id="lng" name="lokasi_lng" value="{{ $lead->lokasi_lng ?? '' }}">
            <div class="gps-box" id="gpsBox">
                @if(!empty($lead->lokasi_lat) && !empty($lead->lokasi_lng))
                    Tersimpan: {{ $lead->lokasi_lat }}, {{ $lead->lokasi_lng }}
                    @if(!empty($lead->lokasi_gps_at)) · {{ \Carbon\Carbon::parse($lead->lokasi_gps_at)->diffForHumans() }}@endif
                @else
                    Belum ada titik GPS.
                @endif
            </div>
        </div>
        @endif

        {{-- Kondisi --}}
        <div class="lk-card">
            <div style="font-size:13px;font-weight:700;color:#fbbf24;margin-bottom:10px;">Kondisi Lokasi</div>
            <div class="lk-field">
                <label>Jarak workshop → lokasi (km) — isi manual dari Maps</label>
                <input type="text" inputmode="decimal" id="jarak" name="lokasi_jarak_km" value="{{ $lead->lokasi_jarak_km ?? '' }}" placeholder="mis. 12.5" oninput="this.value=this.value.replace(/[^0-9.]/g,'').replace(/(\..*)\./g,'$1');">
            </div>
            <div class="lk-field">
                <label>Daya listrik lokasi</label>
                <select name="lokasi_listrik">
                    @php $lst = $lead->lokasi_listrik ?? ''; @endphp
                    <option value="">— pilih —</option>
                    <option value="cukup"  {{ ($lst=='cukup' || $lst=='')?'selected':'' }}>Cukup</option>
                    <option value="kurang" {{ $lst=='kurang'?'selected':'' }}>Kurang</option>
                    <option value="tidak"  {{ $lst=='tidak'?'selected':'' }}>Tidak ada</option>
                </select>
            </div>
            <div class="lk-field">
                <label>Jarak ke sumber listrik (meter) — untuk siapkan kabel</label>
                <input type="number" name="lokasi_jarak_listrik_m" min="0" value="{{ $lead->lokasi_jarak_listrik_m ?? '' }}" placeholder="mis. 20">
            </div>
            <div class="lk-field">
                <label>Akses ke lokasi</label>
                <select name="lokasi_akses">
                    @php $ak = $lead->lokasi_akses ?? ''; @endphp
                    <option value="">— pilih —</option>
                    <option value="mobil" {{ $ak=='mobil'?'selected':'' }}>Mobil bisa masuk</option>
                    <option value="motor" {{ $ak=='motor'?'selected':'' }}>Motor saja</option>
                    <option value="pikul" {{ $ak=='pikul'?'selected':'' }}>Harus pikul/manual</option>
                </select>
            </div>
            <div class="warn" id="warnJauh">⚠️ Jarak ≥ 15 km (jauh). Kalau pengerjaan ≥ 3 hari, kemungkinan perlu nginap — keputusan & anggaran (hotel/kontrakan) ditentukan otomatis di tahap penawaran setelah lama kerja diketahui.</div>
            <div class="lk-field" style="margin-top:12px;">
                <label>Catatan kondisi (opsional)</label>
                <textarea name="lokasi_catatan" placeholder="mis. atap tetangga mepet, perlu izin RT, jalan sempit...">{{ $lead->lokasi_catatan ?? '' }}</textarea>
            </div>
        </div>

        {{-- Foto Lokasi (upload ke Cloudinary) --}}
        <div class="lk-card">
            <div style="font-size:13px;font-weight:700;color:#fbbf24;margin-bottom:6px;">Foto Lokasi</div>
            <div style="font-size:11px;color:#64748b;margin-bottom:10px;">Foto otomatis dikompres sebelum upload (hemat kuota). Maks 8 foto. Jangan lupa tekan Simpan setelah upload.</div>
            <input type="file" id="fotoInput" accept="image/*" capture="environment" multiple style="display:none">
            <button type="button" id="btnFoto" class="btn" style="background:#334155;color:#e2e8f0;">📷 Tambah Foto</button>
            <div id="fotoStatus" style="font-size:12px;color:#cbd5e1;margin-top:8px;"></div>
            <div id="fotoGrid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-top:10px;"></div>
            <input type="hidden" name="lokasi_foto" id="fotoJson" value="{{ $lead->lokasi_foto ?? '[]' }}">
        </div>

        <button type="submit" class="btn btn-gold">💾 Simpan Profil Lokasi</button>
        <button type="submit" id="btnLanjutRab" formaction="{{ url('/lokasi/'.$lead->id) }}?goto=rab" class="btn" style="background:#3b82f6;color:#fff;margin-top:10px;">Lanjut → Buat RAB →</button>
    </form>
</div>

<script>
var AMBANG_JAUH = 15; // km

function updateMapsLink(){
    var a=document.getElementById('bukaMaps');
    var link=(document.getElementById('mapsLink').value||'').trim();
    var share=(document.getElementById('shareLok').value||'').trim();
    var latEl=document.getElementById('lat'), lngEl=document.getElementById('lng');
    var lat=latEl?(latEl.value||'').trim():'', lng=lngEl?(lngEl.value||'').trim():'';
    if(link){ a.href=link; }
    else if(share){ a.href=share; }
    else if(lat && lng){ a.href='https://www.google.com/maps?q='+lat+','+lng; }
    else { a.href='https://www.google.com/maps'; }
}
function cekJauh(){
    var j=parseFloat(document.getElementById('jarak').value||'0');
    document.getElementById('warnJauh').style.display = (j>=AMBANG_JAUH) ? 'block' : 'none';
}

var btnGps=document.getElementById('btnGps');
if(btnGps){
    btnGps.addEventListener('click', function(){
        var box=document.getElementById('gpsBox');
        if(!navigator.geolocation){ box.textContent='HP/browser tidak mendukung GPS.'; return; }
        box.textContent='Mengambil lokasi... (izinkan akses lokasi)';
        navigator.geolocation.getCurrentPosition(function(pos){
            var lat=pos.coords.latitude.toFixed(7), lng=pos.coords.longitude.toFixed(7);
            document.getElementById('lat').value=lat;
            document.getElementById('lng').value=lng;
            box.innerHTML='Titik diambil: <b style="color:#fbbf24">'+lat+', '+lng+'</b> (tersimpan saat Simpan)';
            var ml=document.getElementById('mapsLink');
            if(ml && !ml.value){ ml.value='https://www.google.com/maps?q='+lat+','+lng; }
            updateMapsLink();
        }, function(err){
            box.textContent='Gagal ambil GPS: '+err.message+'. Pastikan izin lokasi aktif.';
        }, {enableHighAccuracy:true, timeout:15000, maximumAge:0});
    });
}

document.getElementById('mapsLink').addEventListener('input', updateMapsLink);
document.getElementById('shareLok').addEventListener('input', updateMapsLink);
document.getElementById('jarak').addEventListener('input', cekJauh);
updateMapsLink();
cekJauh();

// ================= FOTO -> CLOUDINARY =================
var CLOUD_NAME='rnvp56qs';
var UPLOAD_PRESET='canopi_lokasi';
var LEAD_ID={{ $lead->id }};
var MAKS_FOTO=8;

var fotoList=[];
try{ fotoList=JSON.parse(document.getElementById('fotoJson').value||'[]'); }catch(e){ fotoList=[]; }
if(!fotoList || typeof fotoList.length==='undefined') fotoList=[];

function fotoStatus(t){ var e=document.getElementById('fotoStatus'); if(e) e.textContent=t; }
function renderFoto(){
    var g=document.getElementById('fotoGrid'); if(!g) return;
    var html='';
    for(var i=0;i<fotoList.length;i++){
        html+='<div style="position:relative;">'+
            '<a href="'+fotoList[i]+'" target="_blank"><img src="'+fotoList[i]+'" style="width:100%;height:80px;object-fit:cover;border-radius:6px;border:1px solid #334155;"></a>'+
            '<button type="button" onclick="hapusFoto('+i+')" style="position:absolute;top:2px;right:2px;background:#7f1d1d;color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:13px;cursor:pointer;line-height:1;">×</button>'+
            '</div>';
    }
    g.innerHTML=html;
    document.getElementById('fotoJson').value=JSON.stringify(fotoList);
}
function hapusFoto(i){ fotoList.splice(i,1); renderFoto(); }

// kompres di HP sebelum upload
function kompresFoto(file){
    return new Promise(function(resolve,reject){
        var reader=new FileReader();
        reader.onload=function(ev){
            var img=new Image();
            img.onload=function(){
                var maxW=1280;
                var scale=Math.min(1, maxW/img.width);
                var w=Math.round(img.width*scale), h=Math.round(img.height*scale);
                var c=document.createElement('canvas'); c.width=w; c.height=h;
                c.getContext('2d').drawImage(img,0,0,w,h);
                c.toBlob(function(blob){ blob ? resolve(blob) : reject(new Error('kompres gagal')); }, 'image/jpeg', 0.7);
            };
            img.onerror=function(){ reject(new Error('file bukan gambar')); };
            img.src=ev.target.result;
        };
        reader.onerror=function(){ reject(new Error('baca file gagal')); };
        reader.readAsDataURL(file);
    });
}
function uploadCloudinary(blob){
    var fd=new FormData();
    fd.append('file', blob);
    fd.append('upload_preset', UPLOAD_PRESET);
    fd.append('folder', 'canopi/lokasi/lead_'+LEAD_ID);
    return fetch('https://api.cloudinary.com/v1_1/'+CLOUD_NAME+'/image/upload', {method:'POST', body:fd})
        .then(function(r){ return r.json(); })
        .then(function(d){
            if(d && d.secure_url) return d.secure_url;
            throw new Error((d && d.error && d.error.message) ? d.error.message : 'upload gagal');
        });
}

var btnFoto=document.getElementById('btnFoto');
var fotoInput=document.getElementById('fotoInput');
if(btnFoto && fotoInput){
    btnFoto.addEventListener('click', function(){ fotoInput.click(); });
    fotoInput.addEventListener('change', function(){
        var files=[].slice.call(this.files);
        var self=this;
        var idx=0;
        function next(){
            if(idx>=files.length){ fotoStatus(fotoList.length+' foto siap. Tekan Simpan untuk menyimpan.'); self.value=''; return; }
            if(fotoList.length>=MAKS_FOTO){ fotoStatus('Maksimal '+MAKS_FOTO+' foto.'); self.value=''; return; }
            fotoStatus('Mengupload '+(idx+1)+'/'+files.length+'...');
            kompresFoto(files[idx])
                .then(uploadCloudinary)
                .then(function(url){ fotoList.push(url); renderFoto(); idx++; next(); })
                .catch(function(e){ fotoStatus('Gagal: '+e.message); idx++; next(); });
        }
        next();
    });
}
renderFoto();

// ===== VALIDASI sebelum Lanjut Buat RAB =====
var LV_USER = {{ $lv }};
var btnLanjutRab = document.getElementById('btnLanjutRab');
if(btnLanjutRab){
    btnLanjutRab.addEventListener('click', function(e){
        var masalah = [];
        var jarakEl = document.getElementById('jarak');
        var jarak = jarakEl ? (jarakEl.value||'').trim() : '';
        if(jarak === ''){ masalah.push('Jarak workshop ke lokasi belum diisi'); }
        else if(isNaN(parseFloat(jarak))){ masalah.push('Jarak harus berupa angka (contoh 12.5)'); }

        // GPS wajib HANYA untuk surveyor (level 3)
        if(LV_USER === 3){
            var latEl = document.getElementById('lat');
            var lat = latEl ? (latEl.value||'').trim() : '';
            if(lat === ''){ masalah.push('Titik GPS belum diambil — tekan tombol "Ambil GPS Sekarang" di lokasi'); }
        }

        if(masalah.length > 0){
            e.preventDefault();
            alert('Belum bisa lanjut Buat RAB. Lengkapi dulu:\n\n- ' + masalah.join('\n- '));
        }
        // kalau lengkap: biarkan tombol jalan sendiri (formaction ?goto=rab)
    });
}
</script>
@endsection