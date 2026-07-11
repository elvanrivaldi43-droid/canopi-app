@extends('layouts.app')

@section('content')
<div style="padding: 16px; max-width: 700px; margin: 0 auto;">

    {{-- BACK --}}
    <a href="{{ auth()->user()->level == 1 ? route('kpi.index') : url('/owner/dashboard') }}"
       style="color: #94a3b8; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 16px;">
        ← Kembali
    </a>

    {{-- BANNER BINTANG BULAN LALU --}}
    @if($isBintangBulanLalu)
    <div style="background: linear-gradient(135deg, #2d1f00, #1c1400); border: 2px solid #fbbf24; border-radius: 14px; padding: 20px; margin-bottom: 20px; text-align: center; box-shadow: 0 0 20px rgba(251,191,36,0.2);">
        <div style="font-size: 40px; margin-bottom: 8px;">🏆</div>
        <div style="color: #fbbf24; font-size: 18px; font-weight: 800;">BINTANG {{ strtoupper($namaBulanLalu) }}</div>
        <div style="color: #fde68a; font-size: 14px; margin-top: 6px;">Kamu adalah yang terbaik di jabatanmu!</div>
        <div style="color: #92400e; font-size: 12px; margin-top: 8px;">Penghargaan ini berlaku hingga akhir bulan ini</div>
    </div>
    @endif

    {{-- PROFIL --}}
    <div style="background: #1e293b; border-radius: 12px; padding: 16px; margin-bottom: 16px; border: 1px solid #334155; display: flex; gap: 14px; align-items: center;">
        @if($user->foto)
        <img src="{{ asset('storage/' . $user->foto) }}" style="width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 2px solid #fbbf24;">
        @else
        <div style="width: 56px; height: 56px; border-radius: 50%; background: #334155; display: flex; align-items: center; justify-content: center; font-size: 22px; color: #94a3b8;">👤</div>
        @endif
        <div>
            <div style="color: #e2e8f0; font-weight: 700; font-size: 16px;">{{ $user->name }}</div>
            <div style="color: #94a3b8; font-size: 13px;">{{ \App\Models\UjianSoal::namaJabatan($user->level) }}</div>
            @if($spAktif)
            <span style="background: {{ $spAktif->warnaSp() }}20; border: 1px solid {{ $spAktif->warnaSp() }}; color: {{ $spAktif->warnaSp() }}; font-size: 11px; padding: 2px 8px; border-radius: 4px; font-weight: 700;">
                ⚠️ {{ $spAktif->labelSp() }} AKTIF
            </span>
            @endif
        </div>
    </div>

    {{-- KPI BULAN INI --}}
    @if($kpiBulanIni)
    @php
        $poin = $kpiBulanIni->total_poin;
        $bintang = str_repeat('⭐', $kpiBulanIni->bintang);
        $barColor = $poin >= 75 ? '#fbbf24' : ($poin >= 45 ? '#60a5fa' : '#ef4444');
        $detailHadir = $kpiBulanIni->detail_kehadiran ?? [];
        $detailTugas = $kpiBulanIni->detail_tugas ?? [];
    @endphp
    <div style="background: #1e293b; border-radius: 12px; padding: 18px; margin-bottom: 16px; border: 1px solid #334155;">
        <div style="color: #94a3b8; font-size: 12px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;">Poin Kinerja Bulan Ini</div>

        {{-- POIN BESAR --}}
        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px;">
            <div style="font-size: 48px; font-weight: 800; color: {{ $barColor }}; line-height: 1;">{{ $poin }}</div>
            <div>
                <div style="font-size: 20px;">{{ $bintang }}</div>
                @if($kpiBulanIni->bonus_nominal > 0)
                <div style="color: #fbbf24; font-size: 13px; font-weight: 600; margin-top: 4px;">
                    Bonus: Rp {{ number_format($kpiBulanIni->bonus_nominal, 0, ',', '.') }}
                </div>
                @else
                <div style="color: #ef4444; font-size: 13px; margin-top: 4px;">
                    {{ $kpiBulanIni->is_alpha ? 'Ada alpha — bonus gugur' : 'Tidak ada bonus' }}
                </div>
                @endif
            </div>
        </div>

        {{-- PROGRESS BAR --}}
        <div style="background: #0f172a; border-radius: 6px; height: 8px; overflow: hidden; margin-bottom: 16px;">
            <div style="background: {{ $barColor }}; width: {{ $poin }}%; height: 100%; transition: width 0.5s;"></div>
        </div>

        {{-- BREAKDOWN POIN --}}
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div style="background: #0f172a; border-radius: 8px; padding: 10px;">
                <div style="color: #64748b; font-size: 11px;">Kehadiran</div>
                <div style="color: #e2e8f0; font-size: 16px; font-weight: 700;">{{ $kpiBulanIni->poin_kehadiran }}</div>
                @if($detailHadir)
                <div style="color: #64748b; font-size: 11px; margin-top: 4px;">
                    Hadir: {{ $detailHadir['hadir'] ?? 0 }} · Alpha: {{ $detailHadir['alpha'] ?? 0 }} · Telat: {{ $detailHadir['telat'] ?? 0 }}
                </div>
                @endif
            </div>
            <div style="background: #0f172a; border-radius: 8px; padding: 10px;">
                <div style="color: #64748b; font-size: 11px;">Tugas Harian</div>
                <div style="color: #e2e8f0; font-size: 16px; font-weight: 700;">{{ $kpiBulanIni->poin_tugas }}</div>
                @if($detailTugas)
                <div style="color: #64748b; font-size: 11px; margin-top: 4px;">
                    {{ $detailTugas['selesai'] ?? 0 }}/{{ $detailTugas['total'] ?? 0 }} selesai ({{ $detailTugas['persen'] ?? 0 }}%)
                </div>
                @endif
            </div>
            @if($kpiBulanIni->poin_leads > 0)
            <div style="background: #0f172a; border-radius: 8px; padding: 10px;">
                <div style="color: #64748b; font-size: 11px;">Leads Pipeline</div>
                <div style="color: #e2e8f0; font-size: 16px; font-weight: 700;">{{ $kpiBulanIni->poin_leads }}</div>
            </div>
            @endif
            @if($kpiBulanIni->poin_bbm > 0)
            <div style="background: #0f172a; border-radius: 8px; padding: 10px;">
                <div style="color: #64748b; font-size: 11px;">Efisiensi BBM</div>
                <div style="color: #e2e8f0; font-size: 16px; font-weight: 700;">{{ $kpiBulanIni->poin_bbm }}</div>
            </div>
            @endif
            <div style="background: #0f172a; border-radius: 8px; padding: 10px;">
                <div style="color: #64748b; font-size: 11px;">Komplain</div>
                <div style="color: {{ $kpiBulanIni->poin_komplain >= 20 ? '#10b981' : '#ef4444' }}; font-size: 16px; font-weight: 700;">{{ $kpiBulanIni->poin_komplain }}</div>
                <div style="color: #64748b; font-size: 11px; margin-top: 4px;">{{ $kpiBulanIni->poin_komplain >= 20 ? 'Bersih ✅' : 'Ada komplain' }}</div>
            </div>
        </div>
    </div>
    @else
    <div style="background: #1e293b; border-radius: 12px; padding: 20px; margin-bottom: 16px; border: 1px solid #334155; text-align: center; color: #64748b;">
        Belum ada data poin kinerja bulan ini
    </div>
    @endif

    {{-- HISTORI 6 BULAN --}}
    @if($histori->count() > 0)
    <div style="background: #1e293b; border-radius: 12px; padding: 16px; margin-bottom: 16px; border: 1px solid #334155;">
        <div style="color: #94a3b8; font-size: 12px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;">Histori 6 Bulan</div>
        @foreach($histori as $h)
        @php
            $namaBulanH = \Carbon\Carbon::create($h->tahun, $h->bulan, 1)->locale('id')->isoFormat('MMM YY');
            $barW = $h->total_poin;
            $bc = $h->total_poin >= 75 ? '#fbbf24' : ($h->total_poin >= 45 ? '#60a5fa' : '#ef4444');
        @endphp
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
            <div style="color: #64748b; font-size: 12px; width: 54px; flex-shrink: 0;">{{ $namaBulanH }}</div>
            <div style="flex: 1; background: #0f172a; border-radius: 4px; height: 6px; overflow: hidden;">
                <div style="background: {{ $bc }}; width: {{ $barW }}%; height: 100%;"></div>
            </div>
            <div style="color: {{ $bc }}; font-size: 13px; font-weight: 700; width: 36px; text-align: right;">{{ $h->total_poin }}</div>
            <div style="font-size: 11px; width: 60px;">{{ str_repeat('⭐', $h->bintang) }}</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- RAPOR TERAKHIR --}}
    @if($raporTerakhir)
    <div style="background: #1e293b; border-radius: 12px; padding: 16px; margin-bottom: 16px; border: 1px solid #334155;">
        <div style="color: #94a3b8; font-size: 12px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;">Rapor Terakhir</div>
        @php $warna = \App\Models\RaporKaryawan::warnaKelas($raporTerakhir->kelas_baru); @endphp
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-size: 24px; font-weight: 800; color: {{ $warna }};">{{ \App\Models\RaporKaryawan::labelKelas($raporTerakhir->kelas_baru) }}</div>
                <div style="color: #64748b; font-size: 12px; margin-top: 4px;">
                    {{ ucfirst($raporTerakhir->periode) }} {{ $raporTerakhir->tahun }} · Nilai: {{ $raporTerakhir->nilai_total }}
                </div>
                @if($raporTerakhir->kenaikan_gaji > 0)
                <div style="color: #10b981; font-size: 13px; margin-top: 4px;">
                    +Rp {{ number_format($raporTerakhir->kenaikan_gaji, 0, ',', '.') }} kenaikan gaji permanen
                </div>
                @endif
            </div>
            <a href="{{ route('kpi.ujian.index') }}" style="background: #fbbf24; color: #0f172a; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 700;">📝 Ujian</a>
        </div>
    </div>
    @endif

    {{-- RIWAYAT SP --}}
    @if($riwayatSp->count() > 0)
    <div style="background: #1e293b; border-radius: 12px; padding: 16px; margin-bottom: 16px; border: 1px solid #334155;">
        <div style="color: #94a3b8; font-size: 12px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;">Riwayat SP</div>
        @foreach($riwayatSp as $sp)
        <div style="background: #0f172a; border-radius: 8px; padding: 10px; margin-bottom: 8px; border-left: 3px solid {{ $sp->warnaSp() }};">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <span style="background: {{ $sp->warnaSp() }}20; border: 1px solid {{ $sp->warnaSp() }}; color: {{ $sp->warnaSp() }}; font-size: 11px; padding: 2px 8px; border-radius: 4px; font-weight: 700;">{{ $sp->labelSp() }}</span>
                    <div style="color: #94a3b8; font-size: 12px; margin-top: 6px;">{{ $sp->alasan }}</div>
                </div>
                <div style="text-align: right; flex-shrink: 0; margin-left: 12px;">
                    <div style="color: #64748b; font-size: 11px;">{{ $sp->tanggal_sp?->format('d/m/Y') }}</div>
                    @php
                        $statusWarna = ['usulan' => '#fbbf24', 'aktif' => '#ef4444', 'dicabut' => '#6b7280', 'pulih' => '#10b981'];
                    @endphp
                    <span style="color: {{ $statusWarna[$sp->status] ?? '#6b7280' }}; font-size: 11px;">{{ ucfirst($sp->status) }}</span>
                </div>
            </div>
            @if($sp->status === 'aktif' && $sp->bulan_bersih_berturut > 0)
            <div style="margin-top: 8px; background: #0f172a; border-radius: 4px; padding: 6px 10px;">
                <div style="color: #64748b; font-size: 11px;">Progress pemulihan:</div>
                <div style="display: flex; gap: 6px; margin-top: 4px;">
                    @for($i = 1; $i <= 3; $i++)
                    <div style="width: 20px; height: 20px; border-radius: 50%; background: {{ $i <= $sp->bulan_bersih_berturut ? '#10b981' : '#1e293b' }}; border: 1px solid {{ $i <= $sp->bulan_bersih_berturut ? '#10b981' : '#334155' }};"></div>
                    @endfor
                    <span style="color: #64748b; font-size: 11px; line-height: 20px;">{{ $sp->bulan_bersih_berturut }}/3 bulan bersih</span>
                </div>
            </div>
            @endif
        </div>
        @endforeach
    </div>
    @endif

</div>
@endsection