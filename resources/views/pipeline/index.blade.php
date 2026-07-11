@extends('layouts.app')
@section('page-title', 'Pipeline Survey')

@section('sidebar-menu')
    @if(auth()->user()->level == 1)
        @include('partials.sidebar-owner')
    @else
        @include('partials.sidebar-pipeline')
    @endif
@endsection

@section('bottom-nav')
    @include('partials.bottomnav-pipeline')
@endsection

@section('content')
<div>

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <div>
            <div style="font-size:20px;font-weight:800;color:#f1f5f9;">Pipeline Survey</div>
            <div style="font-size:12px;color:#64748b;margin-top:2px;">Kelola prospek & konversi customer</div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('pipeline.list') }}" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#1e293b;color:#94a3b8;border-radius:10px;text-decoration:none;font-size:13px;font-weight:600;border:1px solid #334155;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                List
            </a>
            <a href="{{ route('pipeline.create') }}" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#fbbf24;color:#0f172a;border-radius:10px;text-decoration:none;font-size:13px;font-weight:700;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Tambah Lead
            </a>
        </div>
    </div>

    {{-- Stats Bar --}}
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px;">
        <div style="background:#1e293b;border-radius:14px;padding:14px 12px;border:1px solid #334155;text-align:center;">
            <div style="font-size:24px;font-weight:800;color:#fbbf24;">{{ $totalLead }}</div>
            <div style="font-size:10px;font-weight:600;color:#64748b;margin-top:2px;text-transform:uppercase;letter-spacing:.5px;">Prospek Aktif</div>
        </div>
        <div style="background:#1e293b;border-radius:14px;padding:14px 12px;border:1px solid #334155;text-align:center;">
            <div style="font-size:24px;font-weight:800;color:#22c55e;">{{ $grouped['deal']->count() }}</div>
            <div style="font-size:10px;font-weight:600;color:#64748b;margin-top:2px;text-transform:uppercase;letter-spacing:.5px;">Deal</div>
        </div>
        <div style="background:#1e293b;border-radius:14px;padding:14px 12px;border:1px solid #334155;text-align:center;">
            <div style="font-size:16px;font-weight:800;color:#f1f5f9;">Rp {{ number_format($totalNilai/1000000,1) }}jt</div>
            <div style="font-size:10px;font-weight:600;color:#64748b;margin-top:2px;text-transform:uppercase;letter-spacing:.5px;">Total Pipeline</div>
        </div>
    </div>

    {{-- Kanban Board --}}
    <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;margin:0 -16px;padding:0 16px 24px;">
        <div style="display:flex;gap:12px;width:max-content;align-items:flex-start;">
            @foreach($statusList as $key => $label)
            @php $col = $grouped[$key]; $color = $colors[$key]; @endphp
            <div style="width:268px;flex-shrink:0;background:#1e293b;border-radius:16px;border:1px solid #334155;overflow:hidden;">

                {{-- Column Header --}}
                <div style="padding:11px 14px;border-bottom:1px solid #334155;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:8px;height:8px;border-radius:50%;background:{{ $color }};flex-shrink:0;box-shadow:0 0 6px {{ $color }}88;"></div>
                        <span style="font-size:12px;font-weight:700;color:#e2e8f0;letter-spacing:.3px;">{{ strtoupper($label) }}</span>
                    </div>
                    <span style="background:#0f172a;color:#64748b;font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;">{{ $col->count() }}</span>
                </div>

                {{-- Cards Container --}}
                <div class="kanban-cards" data-status="{{ $key }}" style="padding:10px;display:flex;flex-direction:column;gap:8px;min-height:60px;max-height:65vh;overflow-y:auto;">
                    @forelse($col as $lead)
                    <div class="lead-card" data-id="{{ $lead->id }}" data-href="{{ route('pipeline.show', $lead) }}" style="display:block;text-decoration:none;">
                        <div style="background:#0f172a;border-radius:12px;padding:12px;border:1px solid {{ $lead->is_aging ? '#ef444488' : '#1e293b' }};transition:all 0.15s;cursor:pointer;">

                            {{-- Aging badge --}}
                            @if($lead->is_aging)
                            <div style="display:inline-flex;align-items:center;gap:4px;background:#ef444415;border:1px solid #ef444444;color:#ef4444;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;margin-bottom:8px;">
                                <span style="width:5px;height:5px;border-radius:50%;background:#ef4444;display:block;"></span>
                                {{ $lead->aging }} hari tidak diupdate
                            </div>
                            @endif

                            <div style="font-size:13px;font-weight:700;color:#f1f5f9;margin-bottom:5px;line-height:1.3;">{{ $lead->nama_customer }}</div>

                            <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px;">
                                <span style="background:#1e293b;color:#c9a84c;font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px;border:1px solid #2d3f55;">
                                    {{ ucfirst($lead->produk) }}
                                </span>
                                <span style="background:#1e293b;color:#64748b;font-size:10px;padding:2px 8px;border-radius:20px;border:1px solid #2d3f55;">
                                    {{ $lead->sumber_lead }}
                                </span>
                            </div>

                            @if($lead->estimasi_nilai)
                            <div style="font-size:13px;font-weight:700;color:#fbbf24;margin-bottom:5px;">
                                Rp {{ number_format($lead->estimasi_nilai,0,',','.') }}
                            </div>
                            @endif

                            @if($lead->status === 'dijadwalkan' && $lead->tanggal_jadwal)
                            <div style="font-size:10px;color:#a78bfa;margin-bottom:4px;">
                                📅 {{ $lead->tanggal_jadwal->format('d M Y') }}
                                @if($lead->jam_jadwal) · {{ substr($lead->jam_jadwal,0,5) }} WIB @endif
                            </div>
                            @endif

                            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:6px;">
                                <span style="font-size:10px;color:#475569;">{{ $lead->user->name ?? '-' }}</span>
                                <span style="font-size:10px;color:#334155;">{{ ($lead->last_activity_at ?? $lead->updated_at)->diffForHumans() }}</span>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div style="text-align:center;padding:20px 0;color:#334155;font-size:11px;">— kosong —</div>
                    @endforelse
                </div>
            </div>
            @endforeach
        </div>
    </div>

</div>

<style>
.lead-card {
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}
.drag-ghost {
    opacity: 0.4;
}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
(function(){
    if (typeof Sortable === 'undefined') { return; }
    var CSRF = '{{ csrf_token() }}';
    var cols = document.querySelectorAll('.kanban-cards');
    for (var i = 0; i < cols.length; i++) {
        new Sortable(cols[i], {
            group: 'pipeline',
            animation: 150,
            delay: 150,
            delayOnTouchOnly: true,
            draggable: '.lead-card',
            ghostClass: 'drag-ghost',
            scroll: true,
            scrollSensitivity: 100,
            scrollSpeed: 12,
            bubbleScroll: true,
            onEnd: function(evt){
                if (evt.from === evt.to) return;
                var id = evt.item.getAttribute('data-id');
                var status = evt.to.getAttribute('data-status');
                fetch('{{ url("/pipeline") }}/' + id + '/status', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ status: status })
                }).then(function(r){ return r.json(); })
                  .then(function(res){
                      if (!res || res.success === false) {
                          alert('Gagal ubah status, halaman dimuat ulang.');
                          location.reload();
                      }
                  })
                  .catch(function(){
                      alert('Gagal ubah status, halaman dimuat ulang.');
                      location.reload();
                  });
            }
        });
    }

    // Tap singkat = buka detail lead. Tekan-geser = pindah kolom (drag).
    // Mencegah menu "Buka di tab baru" bawaan browser yang muncul kalau kartu berupa link asli.
    document.querySelectorAll('.lead-card').forEach(function(card){
        var startX = 0, startY = 0, moved = false;
        card.addEventListener('touchstart', function(e){
            var t = e.touches[0];
            startX = t.clientX; startY = t.clientY; moved = false;
        }, { passive: true });
        card.addEventListener('touchmove', function(e){
            var t = e.touches[0];
            if (Math.abs(t.clientX - startX) > 8 || Math.abs(t.clientY - startY) > 8) moved = true;
        }, { passive: true });
        card.addEventListener('click', function(e){
            if (moved) { moved = false; return; }
            var href = card.getAttribute('data-href');
            if (href) window.location.href = href;
        });
        card.addEventListener('contextmenu', function(e){ e.preventDefault(); });
    });
})();
</script>
@endsection