{{-- FILE: resources/views/pipeline/show.blade.php --}}
@extends('layouts.app')
@section('page-title', 'Detail Lead')
@section('sidebar-menu')
    @if(auth()->user()->level == 1)
        @include('partials.sidebar-owner')
    @else
        @include('partials.sidebar-pipeline')
    @endif
@endsection
@section('bottom-nav')
    @include('partials.bottomnav')
@endsection
@section('content')
<div style="max-width:600px;margin:0 auto;">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <a href="{{ route('pipeline.index') }}" style="font-size:13px;color:#64748b;text-decoration:none;">← Pipeline</a>
        <a href="{{ route('pipeline.edit', $pipeline) }}" style="padding:8px 14px;background:#334155;color:#e2e8f0;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;">✏️ Edit</a>
    </div>

    {{-- ✅ BANNER DEAL — Muncul kalau status = deal --}}
    @if($pipeline->status === 'deal')
    <div style="background:linear-gradient(135deg,#064e3b,#065f46);border:1px solid #10b981;border-radius:12px;padding:18px 20px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="color:#6ee7b7;font-size:13px;font-weight:700;margin-bottom:4px;">🎉 Lead ini sudah DEAL!</div>
            <div style="color:#a7f3d0;font-size:12px;">Buat project untuk mulai kelola tim, material, dan pembayaran.</div>
        </div>
        @php
            $sudahAdaProject = \App\Models\Project::where('id_lead', $pipeline->id)->exists();
        @endphp
        @if($sudahAdaProject)
            @php $existingProject = \App\Models\Project::where('id_lead', $pipeline->id)->first(); @endphp
            <a href="{{ route('projects.show', $existingProject) }}"
               style="background:#10b981;color:#fff;padding:10px 20px;border-radius:8px;font-weight:700;font-size:13px;text-decoration:none;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M13 7l5 5-5 5M6 7l5 5-5 5"/></svg>
                Lihat Project
            </a>
        @else
            <a href="{{ route('projects.create', ['id_lead' => $pipeline->id]) }}"
               style="background:#fbbf24;color:#0f172a;padding:10px 20px;border-radius:8px;font-weight:700;font-size:13px;text-decoration:none;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                Buat Project
            </a>
        @endif
    </div>
    @endif

    {{-- Header Card --}}
    <div class="stat-card" style="margin-bottom:14px;border-left:4px solid {{ $pipeline->statusColor() }};">
        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px;">
            <div>
                <div style="font-size:20px;font-weight:800;color:#f1f5f9;">{{ $pipeline->nama_customer }}</div>
                <div style="font-size:13px;color:#64748b;">📱 {{ $pipeline->no_hp }}</div>
                @if($pipeline->alamat)
                <div style="font-size:12px;color:#64748b;margin-top:4px;">📍 {{ $pipeline->alamat }}</div>
                @endif
            </div>
            <span style="background:{{ $pipeline->statusColor() }}20;color:{{ $pipeline->statusColor() }};border:1px solid {{ $pipeline->statusColor() }}40;font-size:11px;padding:4px 12px;border-radius:20px;font-weight:600;">
                {{ $pipeline->statusLabel() }}
            </span>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <span style="background:rgba(251,191,36,0.15);color:#fbbf24;font-size:11px;padding:3px 10px;border-radius:20px;">{{ $pipeline->produkLabel() }}</span>
            <span style="background:rgba(100,116,139,0.15);color:#94a3b8;font-size:11px;padding:3px 10px;border-radius:20px;">{{ $pipeline->sumberLabel() }}</span>
            @if($pipeline->estimasi_nilai > 0)
            <span style="background:rgba(16,185,129,0.15);color:#10b981;font-size:11px;padding:3px 10px;border-radius:20px;font-weight:700;">Rp {{ number_format($pipeline->estimasi_nilai,0,',','.') }}</span>
            @endif
            @if($pipeline->is_aging)
            <span style="background:rgba(239,68,68,0.15);color:#ef4444;font-size:11px;padding:3px 10px;border-radius:20px;">⚠️ {{ $pipeline->aging }} hari tidak diupdate</span>
            @endif
        </div>

        @if($pipeline->tgl_kunjungan)
        <div style="margin-top:10px;background:rgba(139,92,246,0.1);border-radius:8px;padding:10px;font-size:12px;color:#a78bfa;">
            📅 Jadwal kunjungan: <strong>{{ $pipeline->tgl_kunjungan->translatedFormat('d F Y, H:i') }}</strong>
        </div>
        @endif

        @if($pipeline->catatan)
        <div style="margin-top:10px;font-size:12px;color:#94a3b8;background:#0f172a;border-radius:8px;padding:10px;">
            📝 {{ $pipeline->catatan }}
        </div>
        @endif

        <div style="margin-top:10px;font-size:11px;color:#475569;">
            Diinput oleh {{ $pipeline->inputOleh->name }} · {{ $pipeline->created_at->diffForHumans() }}
        </div>
    </div>

    {{-- 💰 RAB & PENAWARAN (mesin blok menempel ke lead ini) --}}
    @if(in_array(auth()->user()->level, [1,2,3]))
    <div class="stat-card" style="margin-bottom:14px;">
        <div style="font-size:13px;font-weight:700;color:#fbbf24;margin-bottom:12px;">💰 RAB & Penawaran</div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
            <div style="background:#0f172a;border-radius:8px;padding:10px;">
                <div style="font-size:10px;color:#64748b;">Estimasi Awal (admin)</div>
                @if(!empty($pipeline->estimasi_max))
                    <div style="font-size:13px;color:#cbd5e1;font-weight:700;margin-top:4px;">Rp {{ number_format($pipeline->estimasi_min ?? 0,0,',','.') }}</div>
                    <div style="font-size:11px;color:#64748b;">– Rp {{ number_format($pipeline->estimasi_max,0,',','.') }}</div>
                @else
                    <div style="font-size:12px;color:#475569;margin-top:4px;">belum ada</div>
                @endif
            </div>
            <div style="background:#0f172a;border-radius:8px;padding:10px;">
                <div style="font-size:10px;color:#64748b;">Harga Final (surveyor)</div>
                @if(!empty($pipeline->harga_final))
                    <div style="font-size:16px;color:#fbbf24;font-weight:800;margin-top:4px;">Rp {{ number_format($pipeline->harga_final,0,',','.') }}</div>
                    @if(!empty($pipeline->final_at))<div style="font-size:10px;color:#475569;margin-top:2px;">{{ \Carbon\Carbon::parse($pipeline->final_at)->diffForHumans() }}</div>@endif
                @else
                    <div style="font-size:12px;color:#475569;margin-top:4px;">belum disurvey</div>
                @endif
            </div>
        </div>

        @if(!empty($pipeline->harga_final) && !empty($pipeline->estimasi_max) && $pipeline->harga_final > $pipeline->estimasi_max * 1.15)
        <div style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.25);border-radius:8px;padding:8px 10px;font-size:11px;color:#fca5a5;margin-bottom:12px;">
            ⚠️ Harga final {{ round(($pipeline->harga_final / $pipeline->estimasi_max - 1) * 100) }}% di atas estimasi admin. Jelaskan ke customer sebelum closing.
        </div>
        @endif

        <div style="text-align:center;padding:10px;background:#0f172a;border-radius:8px;font-size:11px;color:#64748b;">
            Buat / edit RAB lewat <b style="color:#94a3b8;">Profil Lokasi → Lanjut → Buat RAB</b> di bawah.
        </div>
    </div>
    @endif

    {{-- 📍 PROFIL LOKASI --}}
    @if(in_array(auth()->user()->level, [1,2,3]))
    <div class="stat-card" style="margin-bottom:14px;">
        <div style="font-size:13px;font-weight:700;color:#fbbf24;margin-bottom:10px;">📍 Profil Lokasi</div>
        @if(!empty($pipeline->lokasi_area) || !empty($pipeline->lokasi_lat) || !empty($pipeline->lokasi_jarak_km))
        <div style="font-size:12px;color:#cbd5e1;background:#0f172a;border-radius:8px;padding:10px;margin-bottom:10px;">
            @if(!empty($pipeline->lokasi_area))<div>🏘️ {{ $pipeline->lokasi_area }}</div>@endif
            @if(!empty($pipeline->lokasi_jarak_km))<div>📏 {{ $pipeline->lokasi_jarak_km }} km</div>@endif
            @if(!empty($pipeline->lokasi_listrik))<div>⚡ Listrik: {{ ucfirst($pipeline->lokasi_listrik) }}</div>@endif
            @if(!empty($pipeline->lokasi_lat))<div>📍 GPS tersimpan ({{ $pipeline->lokasi_lat }}, {{ $pipeline->lokasi_lng }})</div>@endif
        </div>
        @else
        <div style="font-size:12px;color:#475569;margin-bottom:10px;">Belum diisi.</div>
        @endif
        <a href="{{ url('/lokasi/'.$pipeline->id) }}"
           style="display:block;text-align:center;padding:12px;background:#334155;color:#e2e8f0;border-radius:8px;font-weight:700;font-size:13px;text-decoration:none;">
            {{ (!empty($pipeline->lokasi_area) || !empty($pipeline->lokasi_lat)) ? '✏️ Edit Profil Lokasi' : '📍 Isi Profil Lokasi' }}
        </a>
    </div>
    @endif

    {{-- Update Status --}}
    <div class="stat-card" style="margin-bottom:14px;">
        <div style="font-size:13px;font-weight:700;color:#fbbf24;margin-bottom:12px;">🔄 Update Status</div>
        <form id="formStatus">
            @csrf
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
                @foreach($statuses as $key => $label)
                <button type="button" onclick="updateStatus('{{ $key }}')"
                    style="padding:6px 12px;border-radius:20px;border:1px solid {{ $colors[$key] }}40;background:{{ $pipeline->status==$key ? $colors[$key].'30' : 'transparent' }};color:{{ $colors[$key] }};font-size:11px;font-weight:600;cursor:pointer;">
                    {{ $label }}
                </button>
                @endforeach
            </div>
            <div id="jadwalInput" style="display:{{ $pipeline->status=='dijadwalkan'?'block':'none' }};margin-top:8px;">
                <label style="font-size:12px;color:#64748b;display:block;margin-bottom:6px;">📅 Jadwal Kunjungan</label>
                <input type="datetime-local" id="tglKunjungan" value="{{ $pipeline->tgl_kunjungan?->format('Y-m-d\TH:i') }}"
                    style="width:100%;background:#0f172a;border:1px solid #334155;color:#f1f5f9;border-radius:8px;padding:10px 12px;font-size:13px;">
            </div>
        </form>
        <div id="statusMsg" style="font-size:12px;margin-top:8px;display:none;"></div>
    </div>

    {{-- Follow-up Tracker --}}
    <div class="stat-card" style="margin-bottom:14px;">
        <div style="font-size:13px;font-weight:700;color:#fbbf24;margin-bottom:12px;">📞 Catat Follow-up</div>
        <form method="POST" action="{{ route('pipeline.followup', $pipeline) }}">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                <div>
                    <label style="font-size:12px;color:#64748b;display:block;margin-bottom:6px;">Metode</label>
                    <select name="metode" style="width:100%;background:#0f172a;border:1px solid #334155;color:#f1f5f9;border-radius:8px;padding:9px 12px;font-size:13px;">
                        @foreach($metodeList as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;color:#64748b;display:block;margin-bottom:6px;">Follow-up Berikutnya</label>
                    <input type="date" name="tgl_followup_berikutnya"
                        style="width:100%;background:#0f172a;border:1px solid #334155;color:#f1f5f9;border-radius:8px;padding:9px 12px;font-size:13px;">
                </div>
            </div>
            <textarea name="catatan" rows="2" placeholder="Hasil follow-up..." required
                style="width:100%;background:#0f172a;border:1px solid #334155;color:#f1f5f9;border-radius:8px;padding:10px 12px;font-size:13px;resize:none;margin-bottom:10px;"></textarea>
            <button type="submit"
                style="width:100%;padding:10px;background:#06b6d4;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
                📝 Simpan Follow-up
            </button>
        </form>
    </div>

    {{-- Riwayat Follow-up --}}
    @if($pipeline->followups->count() > 0)
    <div class="stat-card">
        <div style="font-size:13px;font-weight:700;color:#fbbf24;margin-bottom:12px;">📜 Riwayat Follow-up</div>
        @foreach($pipeline->followups as $fu)
        <div style="border-left:2px solid #334155;padding:8px 12px;margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:11px;color:#06b6d4;">{{ $fu->metodeLabel() }}</span>
                <span style="font-size:10px;color:#475569;">{{ $fu->created_at->format('d/m/Y H:i') }}</span>
            </div>
            <div style="font-size:12px;color:#e2e8f0;margin-top:4px;">{{ $fu->catatan }}</div>
            @if($fu->tgl_followup_berikutnya)
            <div style="font-size:11px;color:#f59e0b;margin-top:4px;">📅 Next: {{ $fu->tgl_followup_berikutnya->format('d/m/Y') }}</div>
            @endif
            <div style="font-size:10px;color:#475569;margin-top:2px;">oleh {{ $fu->user->name }}</div>
        </div>
        @endforeach
    </div>
    @endif

</div>

<script>
function updateStatus(status) {
    const tgl = document.getElementById('tglKunjungan').value;
    document.getElementById('jadwalInput').style.display = status === 'dijadwalkan' ? 'block' : 'none';

    fetch('{{ route("pipeline.update-status", $pipeline) }}', {
        method: 'PATCH',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
        body: JSON.stringify({status: status, tgl_kunjungan: tgl})
    })
    .then(r => r.json())
    .then(data => {
        const msg = document.getElementById('statusMsg');
        msg.style.display = 'block';
        msg.style.color = data.success ? '#10b981' : '#ef4444';
        msg.textContent = data.success ? '✅ Status diupdate!' : '❌ Gagal';
        setTimeout(() => location.reload(), 1000);
    });
}
</script>
@endsection