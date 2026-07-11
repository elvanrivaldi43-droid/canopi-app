@extends('layouts.app')

@section('content')
<div style="padding: 16px; max-width: 700px; margin: 0 auto;" x-data="ujianApp({{ $sisaDetik }})" x-init="init()">

    {{-- HEADER --}}
    <div style="background: #1e293b; border-radius: 12px; padding: 14px 16px; margin-bottom: 16px; border: 1px solid #334155; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <div style="color: #fbbf24; font-weight: 700; font-size: 15px;">📝 Ujian Online</div>
            <div style="color: #64748b; font-size: 12px;">{{ ucfirst($sesi->periode) }} {{ $sesi->tahun }} · {{ $sesi->jumlah_soal }} soal</div>
        </div>
        {{-- TIMER --}}
        <div style="text-align: center;">
            <div x-text="formatWaktu()" :style="'font-size: 22px; font-weight: 800; color: ' + (sisaDetik < 300 ? '#ef4444' : '#10b981')"></div>
            <div style="color: #64748b; font-size: 11px;">sisa waktu</div>
        </div>
    </div>

    {{-- PROGRESS SOAL --}}
    <div style="background: #1e293b; border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; border: 1px solid #334155;">
        <div style="display: flex; justify-content: space-between; color: #64748b; font-size: 12px; margin-bottom: 8px;">
            <span>Progress jawaban</span>
            <span x-text="terjawab + '/{{ $sesi->jumlah_soal }}'"></span>
        </div>
        <div style="background: #0f172a; border-radius: 4px; height: 6px; overflow: hidden;">
            <div :style="'background: #fbbf24; width: ' + (terjawab / {{ $sesi->jumlah_soal }} * 100) + '%; height: 100%; transition: width 0.3s;'"></div>
        </div>

        {{-- NAVIGASI SOAL --}}
        <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px;">
            @foreach($jawaban as $j)
            <button onclick="scrollToSoal({{ $j->urutan }})"
                :style="'width: 30px; height: 30px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; border: none; ' + (jawaban[{{ $j->urutan }}] ? 'background: #fbbf24; color: #0f172a;' : 'background: #0f172a; color: #64748b;')">
                {{ $j->urutan }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- DAFTAR SOAL --}}
    <form id="formUjian">
        @csrf
        @foreach($jawaban as $j)
        <div id="soal-{{ $j->urutan }}" style="background: #1e293b; border-radius: 12px; padding: 18px; margin-bottom: 14px; border: 1px solid #334155; scroll-margin-top: 20px;">

            {{-- NOMOR + PERTANYAAN --}}
            <div style="display: flex; gap: 12px; margin-bottom: 16px;">
                <div style="background: #fbbf24; color: #0f172a; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; flex-shrink: 0;">{{ $j->urutan }}</div>
                <div style="color: #e2e8f0; font-size: 15px; line-height: 1.5; padding-top: 2px;">{{ $j->soal->pertanyaan }}</div>
            </div>

            {{-- PILIHAN --}}
            @foreach(['a' => $j->soal->pilihan_a, 'b' => $j->soal->pilihan_b, 'c' => $j->soal->pilihan_c, 'd' => $j->soal->pilihan_d] as $huruf => $teks)
            <label style="display: flex; align-items: flex-start; gap: 12px; padding: 12px; border-radius: 8px; cursor: pointer; margin-bottom: 8px; transition: background 0.2s;"
                :style="jawaban[{{ $j->urutan }}] === '{{ $huruf }}' ? 'background: rgba(251,191,36,0.15); border: 1px solid #fbbf24;' : 'background: #0f172a; border: 1px solid #334155;'">
                <input type="radio" name="soal_{{ $j->soal_id }}" value="{{ $huruf }}"
                    {{ $j->jawaban_karyawan === $huruf ? 'checked' : '' }}
                    style="display: none;"
                    @change="simpanJawaban({{ $j->soal_id }}, '{{ $huruf }}', {{ $j->urutan }})">
                <div :style="'width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; flex-shrink: 0; ' + (jawaban[{{ $j->urutan }}] === '{{ $huruf }}' ? 'background: #fbbf24; color: #0f172a;' : 'background: #1e293b; border: 1px solid #334155; color: #94a3b8;')">
                    {{ strtoupper($huruf) }}
                </div>
                <span style="color: #e2e8f0; font-size: 14px; line-height: 1.4; padding-top: 2px;">{{ $teks }}</span>
            </label>
            @endforeach

        </div>
        @endforeach
    </form>

    {{-- TOMBOL SUBMIT --}}
    <div style="background: #1e293b; border-radius: 12px; padding: 16px; border: 1px solid #334155; text-align: center; margin-bottom: 30px;">
        <div style="color: #94a3b8; font-size: 13px; margin-bottom: 12px;">
            Pastikan semua soal sudah dijawab sebelum submit.
        </div>
        <button onclick="konfirmasiSubmit()" style="background: #fbbf24; color: #0f172a; border: none; padding: 14px 32px; border-radius: 10px; font-size: 16px; font-weight: 800; cursor: pointer; width: 100%; max-width: 300px;">
            ✅ Submit Ujian
        </button>
    </div>

</div>

<script>
// State jawaban dari server
const jawabanServer = {
    @foreach($jawaban as $j)
    {{ $j->urutan }}: '{{ $j->jawaban_karyawan ?? '' }}',
    @endforeach
};

function ujianApp(sisaDetikAwal) {
    return {
        sisaDetik: sisaDetikAwal,
        jawaban: { ...jawabanServer },
        terjawab: Object.values(jawabanServer).filter(v => v !== '').length,
        timer: null,

        init() {
            this.timer = setInterval(() => {
                this.sisaDetik--;
                if (this.sisaDetik <= 0) {
                    clearInterval(this.timer);
                    alert('Waktu habis! Ujian otomatis dikumpulkan.');
                    document.getElementById('formSubmit').submit();
                }
            }, 1000);
        },

        formatWaktu() {
            const m = Math.floor(this.sisaDetik / 60).toString().padStart(2, '0');
            const s = (this.sisaDetik % 60).toString().padStart(2, '0');
            return m + ':' + s;
        },

        simpanJawaban(soalId, jawaban, urutan) {
            // Update state lokal
            this.jawaban[urutan] = jawaban;
            this.terjawab = Object.values(this.jawaban).filter(v => v !== '').length;

            // Tandai radio button aktif
            document.querySelector(`input[name="soal_${soalId}"][value="${jawaban}"]`).checked = true;

            // Simpan ke server via AJAX
            fetch('{{ route("kpi.ujian.jawab") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ soal_id: soalId, jawaban: jawaban })
            })
            .then(r => r.json())
            .then(data => {
                if (data.expired) {
                    alert('Waktu habis! Ujian dikumpulkan.');
                    window.location.href = '{{ route("kpi.ujian.hasil") }}';
                }
            });
        }
    }
}

function scrollToSoal(nomor) {
    document.getElementById('soal-' + nomor)?.scrollIntoView({ behavior: 'smooth' });
}

function konfirmasiSubmit() {
    const terjawab = Object.values(jawabanServer).filter(v => v !== '').length;
    const total = {{ $sesi->jumlah_soal }};

    if (terjawab < total) {
        if (!confirm(`Masih ada ${total - terjawab} soal yang belum dijawab. Tetap submit?`)) return;
    } else {
        if (!confirm('Submit ujian? Jawaban tidak bisa diubah setelah submit.')) return;
    }

    fetch('{{ route("kpi.ujian.submit") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        }
    }).then(() => {
        window.location.href = '{{ route("kpi.ujian.hasil") }}';
    });
}
</script>

{{-- Form hidden untuk auto-submit saat waktu habis --}}
<form id="formSubmit" method="POST" action="{{ route('kpi.ujian.submit') }}" style="display:none;">
    @csrf
</form>

@endsection