@extends('layouts.app')
@section('title', 'Detail RAB ' . $rab->nomor_rab)
@section('content')
<style>
.wrap { max-width: 860px; margin: 0 auto; padding: 16px 16px 100px; }
.page-header {
    display: flex; align-items: center; gap: 12px; margin-bottom: 20px;
}
.back-btn {
    width: 36px; height: 36px; background: #1e293b;
    border: 1px solid #334155; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; color: #94a3b8;
}
.page-header h1 { font-size: 16px; font-weight: 700; color: #f1f5f9; margin: 0; flex: 1; }

.card {
    background: #1e293b; border: 1px solid #334155;
    border-radius: 12px; padding: 16px; margin-bottom: 14px;
}
.card-title {
    font-size: 12px; font-weight: 600; color: #64748b;
    text-transform: uppercase; letter-spacing: .5px;
    margin: 0 0 14px;
}
.info-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.info-item .label { font-size: 11px; color: #475569; margin-bottom: 3px; }
.info-item .value { font-size: 14px; color: #f1f5f9; font-weight: 500; }
.info-item .value.gold { color: #fbbf24; font-size: 22px; font-weight: 800; }

/* STATUS BADGE */
.status-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
}
.badge-deal        { background:#052e16; color:#4ade80; }
.badge-draft       { background:#1e293b; color:#64748b; border:1px solid #334155; }
.badge-sent        { background:#1e3a5f; color:#93c5fd; }
.badge-negotiating { background:#1c1a0a; color:#fbbf24; }
.badge-batal       { background:#2d0a0a; color:#f87171; }

/* ITEM TABLE */
.item-table { width: 100%; border-collapse: collapse; }
.item-table th {
    font-size: 11px; color: #475569; text-align: left;
    padding: 8px 10px; border-bottom: 1px solid #334155;
    text-transform: uppercase; letter-spacing: .3px;
}
.item-table td {
    padding: 10px; border-bottom: 1px solid #1e293b;
    font-size: 13px; color: #f1f5f9; vertical-align: middle;
}
.item-table tr:last-child td { border-bottom: none; }
.tipe-badge {
    display: inline-block; padding: 2px 8px;
    border-radius: 10px; font-size: 10px; font-weight: 600;
}
.tipe-rangka  { background:#1e3a5f; color:#93c5fd; }
.tipe-atap    { background:#1a2e1a; color:#86efac; }
.tipe-jasa    { background:#1c1a0a; color:#fde68a; }
.tipe-addon   { background:#2d1a2d; color:#d8b4fe; }
.tipe-kondisi { background:#2d1a00; color:#fdba74; }

/* 3 VERSI */
.versi-row {
    display: grid; grid-template-columns: repeat(3,1fr); gap: 10px;
}
@media(max-width:500px){ .versi-row { grid-template-columns:1fr; } }
.versi-box {
    background: #0f172a; border: 2px solid #334155;
    border-radius: 10px; padding: 14px; text-align: center;
}
.versi-box.dipilih { border-color: #fbbf24; background: #1c1a0a; }
.versi-box .v-label { font-size: 11px; font-weight: 700; color: #fbbf24; margin-bottom: 4px; }
.versi-box .v-harga { font-size: 16px; font-weight: 800; color: #f1f5f9; }
.versi-box .v-note  { font-size: 10px; color: #475569; margin-top: 4px; }

/* TTD */
.ttd-box {
    background: #0f172a; border: 1px solid #334155;
    border-radius: 8px; padding: 14px;
    display: flex; align-items: center; gap: 14px;
}
.ttd-img {
    width: 120px; height: 60px;
    object-fit: contain; background: #0f172a;
    border-radius: 6px; border: 1px solid #1e293b;
}

/* AKSI BUTTONS */
.aksi-row { display: flex; gap: 8px; flex-wrap: wrap; }
.btn {
    padding: 10px 18px; border-radius: 8px;
    font-size: 13px; font-weight: 600;
    cursor: pointer; border: none; text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
    transition: all .2s;
}
.btn-gold   { background: #fbbf24; color: #0f172a; }
.btn-green  { background: #22c55e; color: #0f172a; }
.btn-outline { background: transparent; border: 1px solid #334155; color: #94a3b8; }
.btn-outline:hover { border-color: #fbbf24; color: #fbbf24; }
.btn-red    { background: #ef4444; color: white; }

/* PRINT QUOTATION */
@media print {
    .no-print { display: none !important; }
    body { background: white !important; }
    .wrap { padding: 0 !important; max-width: 100% !important; }
    .card { background: white !important; border: 1px solid #ddd !important; }
    .card-title { color: #333 !important; }
    .info-item .label { color: #666 !important; }
    .info-item .value { color: #111 !important; }
    .item-table th { color: #333 !important; border-color: #ddd !important; }
    .item-table td { color: #111 !important; border-color: #eee !important; }
    #sectionInternal { display: none !important; }
}
</style>

<div class="wrap">

    {{-- HEADER --}}
    <div class="page-header no-print">
        <a href="{{ route('rab.index') }}" class="back-btn">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M5 12l7 7M5 12l7-7"/></svg>
        </a>
        <h1>{{ $rab->nomor_rab ?? 'Draft RAB' }}</h1>
        <span class="status-badge badge-{{ $rab->status }}">
            {{ $rab->statusLabel() }}
        </span>
    </div>

    {{-- INFO CUSTOMER --}}
    <div class="card">
        <div class="card-title">Informasi Customer & Proyek</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="label">Customer</div>
                <div class="value">{{ $rab->lead?->nama_customer ?? '—' }}</div>
            </div>
            <div class="info-item">
                <div class="label">No HP</div>
                <div class="value">{{ $rab->lead?->no_hp ?? '—' }}</div>
            </div>
            <div class="info-item">
                <div class="label">Produk</div>
                <div class="value">{{ $rab->produk_kode }}</div>
            </div>
            <div class="info-item">
                <div class="label">Ukuran</div>
                <div class="value">{{ $rab->panjang }} × {{ $rab->lebar }} m ({{ $rab->m2_total }} m²)</div>
            </div>
            <div class="info-item">
                <div class="label">Konstruksi</div>
                <div class="value">{{ $rab->paketKonstruksi?->label_display ?? '—' }}</div>
            </div>
            <div class="info-item">
                <div class="label">Atap</div>
                <div class="value">{{ $rab->atap?->nama ?? '—' }}</div>
            </div>
            <div class="info-item">
                <div class="label">Dibuat oleh</div>
                <div class="value">{{ $rab->pembuat?->name ?? '—' }}</div>
            </div>
            <div class="info-item">
                <div class="label">Tanggal</div>
                <div class="value">{{ $rab->created_at->format('d M Y') }}</div>
            </div>
        </div>
    </div>

    {{-- HARGA CUSTOMER (tampil jelas) --}}
    <div class="card" style="border-color:#fbbf24">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
            <div>
                <div style="font-size:12px;color:#64748b;margin-bottom:4px">Total Harga Customer</div>
                <div style="font-size:28px;font-weight:800;color:#fbbf24">
                    Rp {{ number_format($rab->harga_final, 0, ',', '.') }}
                </div>
                @if($rab->diskon_persen > 0)
                    <div style="font-size:12px;color:#94a3b8;margin-top:4px">
                        Diskon {{ $rab->diskon_persen }}%
                        (Rp {{ number_format($rab->diskon_nominal, 0, ',', '.') }})
                        dari Rp {{ number_format($rab->harga_sebelum_diskon, 0, ',', '.') }}
                    </div>
                @endif
            </div>
            <div style="text-align:right">
                <div style="font-size:11px;color:#475569;margin-bottom:4px">Harga per m²</div>
                <div style="font-size:16px;font-weight:700;color:#f1f5f9">
                    Rp {{ $rab->m2_total > 0 ? number_format($rab->harga_final / $rab->m2_total, 0, ',', '.') : 0 }}/m²
                </div>
            </div>
        </div>
    </div>

    {{-- 3 VERSI PAKET --}}
    @if($rab->versi->count())
    <div class="card">
        <div class="card-title">3 Pilihan Paket</div>
        <div class="versi-row">
            @foreach($rab->versi as $v)
            <div class="versi-box {{ $v->dipilih ? 'dipilih' : '' }}">
                <div class="v-label">{{ $v->label }} {{ $v->dipilih ? '✓' : '' }}</div>
                <div class="v-harga">{{ $v->hargaFormatted() }}</div>
                <div class="v-note">{{ $v->paket?->label_display ?? '' }}</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- TTD --}}
    @if($rab->ttd)
    <div class="card">
        <div class="card-title">Tanda Tangan Digital</div>
        <div class="ttd-box">
            <img src="{{ $rab->ttd->ttd_data }}" class="ttd-img" alt="TTD">
            <div>
                <div style="font-size:13px;font-weight:600;color:#f1f5f9">
                    {{ $rab->ttd->nama_penandatangan }}
                </div>
                <div style="font-size:11px;color:#64748b;margin-top:3px">
                    {{ \Carbon\Carbon::parse($rab->ttd->signed_at)->format('d M Y H:i') }} WIB
                </div>
                @if($rab->ttd->lokasi_lat)
                <div style="font-size:10px;color:#475569;margin-top:2px">
                    GPS: {{ $rab->ttd->lokasi_lat }}, {{ $rab->ttd->lokasi_lng }}
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- DETAIL BIAYA (INTERNAL — hanya owner) --}}
    @if(auth()->user()->level == 1)
    <div class="card" id="sectionInternal">
        <div class="card-title" style="color:#ef4444">
            🔒 Detail Biaya Internal (Owner Only)
        </div>
        <div class="info-grid" style="margin-bottom:14px">
            <div class="info-item">
                <div class="label">Biaya Rangka</div>
                <div class="value">Rp {{ number_format($rab->biaya_rangka, 0, ',', '.') }}</div>
            </div>
            <div class="info-item">
                <div class="label">Biaya Atap</div>
                <div class="value">Rp {{ number_format($rab->biaya_atap, 0, ',', '.') }}</div>
            </div>
            <div class="info-item">
                <div class="label">Biaya Jasa</div>
                <div class="value">Rp {{ number_format($rab->biaya_jasa, 0, ',', '.') }}</div>
            </div>
            <div class="info-item">
                <div class="label">Biaya Add-on</div>
                <div class="value">Rp {{ number_format($rab->biaya_addon, 0, ',', '.') }}</div>
            </div>
            <div class="info-item">
                <div class="label">Biaya Kondisi</div>
                <div class="value">Rp {{ number_format($rab->biaya_kondisi, 0, ',', '.') }}</div>
            </div>
            <div class="info-item">
                <div class="label">Total Pokok</div>
                <div class="value">Rp {{ number_format($rab->biaya_pokok_total, 0, ',', '.') }}</div>
            </div>
            <div class="info-item">
                <div class="label">+ Buffer {{ $rab->buffer_persen }}%</div>
                <div class="value">Rp {{ number_format($rab->biaya_setelah_buffer, 0, ',', '.') }}</div>
            </div>
            <div class="info-item">
                <div class="label">Margin {{ $rab->margin_persen }}%</div>
                <div class="value" style="color:#22c55e;font-weight:700">
                    Rp {{ number_format($rab->harga_sebelum_diskon - $rab->biaya_setelah_buffer, 0, ',', '.') }}
                </div>
            </div>
        </div>

        {{-- Item table internal --}}
        @if($rab->items->count())
        <div style="overflow-x:auto">
            <table class="item-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Tipe</th>
                        <th>Qty</th>
                        <th>Harga Sat</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rab->items->sortBy('urutan') as $item)
                    <tr>
                        <td>{{ $item->nama_item }}</td>
                        <td>
                            <span class="tipe-badge tipe-{{ $item->tipe }}">{{ $item->tipe }}</span>
                        </td>
                        <td>{{ $item->qty }} {{ $item->satuan }}</td>
                        <td>Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                        <td style="font-weight:600;color:#fbbf24">
                            Rp {{ number_format($item->total_computed, 0, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
    @endif

    {{-- CATATAN --}}
    @if($rab->catatan_surveyor)
    <div class="card">
        <div class="card-title">Catatan Surveyor</div>
        <p style="font-size:13px;color:#94a3b8;margin:0;line-height:1.6">{{ $rab->catatan_surveyor }}</p>
    </div>
    @endif

    {{-- AKSI --}}
    <div class="card no-print">
        <div class="card-title">Aksi</div>
        <div class="aksi-row">
            @if($rab->status === 'draft')
                <button class="btn btn-gold" onclick="showTtdDeal()">
                    ✍️ Deal Sekarang
                </button>
                <a href="{{ route('rab.create', ['lead_id' => $rab->pipeline_lead_id]) }}" class="btn btn-outline">
                    ✏️ Revisi RAB
                </a>
            @endif

            @if(in_array($rab->status, ['draft','sent','deal']))
                <button class="btn btn-outline" onclick="printQuotation()">
                    🖨️ Print Quotation
                </button>
                <button class="btn btn-outline" onclick="kirimWaQuote()">
                    📱 Kirim WA
                </button>
            @endif

            @if($rab->status === 'deal' && !$rab->project_id)
                <form method="POST" action="{{ route('rab.deal', $rab->id) }}" style="display:inline">
                    @csrf
                    <button type="submit" class="btn btn-green">
                        📁 Buat Project
                    </button>
                </form>
            @endif

            @if($rab->project_id)
                <a href="{{ route('projects.show', $rab->project_id) }}" class="btn btn-green">
                    📁 Lihat Project
                </a>
            @endif
        </div>
    </div>

</div>

{{-- MODAL TTD (untuk deal dari halaman show) --}}
<div id="ttdModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:300;overflow-y:auto" class="no-print">
    <div style="max-width:420px;margin:20px auto;padding:16px">
        <div style="background:#1e293b;border-radius:14px;padding:20px">
            <h3 style="font-size:16px;font-weight:700;color:#f1f5f9;margin:0 0 16px">Tanda Tangan Deal</h3>
            <div class="info-item" style="margin-bottom:14px">
                <div class="label">Harga Deal</div>
                <div style="font-size:22px;font-weight:800;color:#fbbf24">
                    Rp {{ number_format($rab->harga_final, 0, ',', '.') }}
                </div>
            </div>
            <div style="margin-bottom:12px">
                <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:6px">Nama Penandatangan</label>
                <input type="text" id="ttdNama" placeholder="Nama customer"
                    style="width:100%;background:#0f172a;border:1px solid #334155;border-radius:8px;padding:12px;font-size:16px;color:#f1f5f9;outline:none">
            </div>
            <div style="margin-bottom:14px">
                <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:6px">Tanda Tangan</label>
                <canvas id="ttdCanvas" width="360" height="140"
                    style="width:100%;height:140px;background:#0f172a;border:1px solid #334155;border-radius:8px;touch-action:none;display:block">
                </canvas>
                <button onclick="clearTtd()" style="margin-top:4px;background:none;border:none;color:#64748b;font-size:11px;cursor:pointer">
                    ✕ Hapus
                </button>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <button class="btn btn-outline" onclick="closeTtdModal()">Batal</button>
                <button class="btn btn-green" onclick="submitDeal()">Konfirmasi</button>
            </div>
        </div>
    </div>
</div>

<script>
const rabId = {{ $rab->id }};
let ttdCanvas, ttdCtx, isDrawing = false;

function showTtdDeal() { document.getElementById('ttdModal').style.display='block'; initCanvas(); }
function closeTtdModal() { document.getElementById('ttdModal').style.display='none'; }

function initCanvas() {
    ttdCanvas = document.getElementById('ttdCanvas');
    ttdCtx = ttdCanvas.getContext('2d');
    ttdCtx.strokeStyle = '#fbbf24';
    ttdCtx.lineWidth = 2.5;
    ttdCtx.lineCap = 'round';
    const getPos = (e) => {
        const r = ttdCanvas.getBoundingClientRect();
        const sx = ttdCanvas.width/r.width, sy = ttdCanvas.height/r.height;
        if(e.touches) return [(e.touches[0].clientX-r.left)*sx,(e.touches[0].clientY-r.top)*sy];
        return [(e.clientX-r.left)*sx,(e.clientY-r.top)*sy];
    };
    ttdCanvas.onmousedown = ttdCanvas.ontouchstart = (e)=>{
        e.preventDefault(); isDrawing=true;
        const[x,y]=getPos(e); ttdCtx.beginPath(); ttdCtx.moveTo(x,y);
    };
    ttdCanvas.onmousemove = ttdCanvas.ontouchmove = (e)=>{
        e.preventDefault(); if(!isDrawing)return;
        const[x,y]=getPos(e); ttdCtx.lineTo(x,y); ttdCtx.stroke();
    };
    ttdCanvas.onmouseup = ttdCanvas.ontouchend = ()=>{ isDrawing=false; };
}
function clearTtd() { ttdCtx?.clearRect(0,0,ttdCanvas.width,ttdCanvas.height); }

async function submitDeal() {
    const nama = document.getElementById('ttdNama').value.trim();
    if (!nama) { alert('Isi nama'); return; }
    const ttdData = ttdCanvas.toDataURL();
    const resp = await fetch('/rab/'+rabId+'/deal', {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},
        body:JSON.stringify({nama_penandatangan:nama,ttd_data:ttdData})
    });
    const data = await resp.json();
    if(data.success) {
        window.location.href = data.project_id ? '/projects/'+data.project_id : '/rab';
    } else { alert('Gagal'); }
}

function printQuotation() { window.print(); }

function kirimWaQuote() {
    const harga = 'Rp {{ number_format($rab->harga_final, 0, ",", ".") }}';
    const produk = '{{ $rab->produk_kode }}';
    const ukuran = '{{ $rab->panjang }}x{{ $rab->lebar }}m';
    const pesan = encodeURIComponent(
        `Halo, berikut penawaran dari Pusat Kanopi BSD:\n\n` +
        `No RAB: {{ $rab->nomor_rab ?? "Draft" }}\n` +
        `Produk: ${produk}\n` +
        `Ukuran: ${ukuran}\n` +
        `Harga: ${harga}\n\n` +
        `Info lebih lanjut: {{ config('app.url') }}`
    );
    @if($rab->lead?->no_hp)
        window.open('https://wa.me/{{ preg_replace("/[^0-9]/", "", $rab->lead->no_hp) }}?text='+pesan, '_blank');
    @else
        window.open('https://wa.me/?text='+pesan, '_blank');
    @endif
}
</script>
@endsection
