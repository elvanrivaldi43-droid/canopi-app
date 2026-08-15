<?php
// FILE: app/Http/Controllers/AbsensiController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Absensi;
use App\Models\User;
use App\Models\LuarKota;
use App\Models\IzinAbsen;
use App\Models\KerjaHariLibur;
use App\Models\KodeAbsen;
use App\Services\TelegramService;
use App\Services\LiburService;
use App\Services\KerjaHariLiburService;
use App\Services\R2Service;

class AbsensiController extends Controller
{
    const LOKASI = [
        'workshop' => ['lat' => -6.326891466682671, 'lng' => 106.68817860852242],
        'kantor'   => ['lat' => -6.377479957005854, 'lng' => 106.68087679503195],
    ];

    const RADIUS_MASUK_PULANG = 100;
    const RADIUS_SIANG        = 200;
    const LEVEL_WORKSHOP      = [3, 5, 6];
    const LEVEL_KANTOR        = [2, 4, 7];
    const JAM_BUKA_ABSEN      = '06:30';
    const JAM_SETENGAH        = '10:00';
    const JAM_MASUK_SIANG     = '13:00';
    const JAM_SKIP_SIANG      = '14:00';
    const TOLERANSI_SIANG     = 3;
    const POTONGAN_TELAT      = 20000;
    const LEMBUR_MAX_JAM      = 5;

    const STATUS_PEKERJAAN = [
        'normal'   => '🟢 Berjalan normal sesuai rencana',
        'lambat'   => '🟡 Sedikit terlambat dari target',
        'terhenti' => '🔴 Terhenti — ada kendala',
    ];

    const JENIS_KENDALA = [
        'cuaca'     => '⛈️ Cuaca (hujan/angin)',
        'material'  => '🔩 Material kurang/tidak sesuai',
        'peralatan' => '🔧 Peralatan bermasalah',
        'tenaga'    => '👤 Kekurangan tenaga',
        'teknis'    => '📐 Masalah teknis pemasangan',
        'customer'  => '🏠 Kendala dari pihak customer',
        'lainnya'   => '📝 Lainnya',
    ];

    const BANK_PERTANYAAN_PROGRESS = [
        'Progress kerja hari ini sudah sampai mana?',
        'Bagian apa yang sudah selesai dikerjakan hari ini?',
        'Target hari ini kira-kira tercapai berapa persen?',
        'Ada bagian yang lebih cepat/lambat dari rencana?',
        'Apa yang lagi dikerjakan sekarang?',
        'Kalau dibandingkan kemarin, progress hari ini gimana?',
        'Ada bagian yang perlu diperhatikan Owner/Mandor hari ini?',
    ];

    const BALASAN_TANPA_KENDALA = [
        '✅ Laporan diterima, semangat lanjut kerja!',
        '✅ Mantap, tetap semangat ya! 💪',
        '✅ Oke, terima kasih laporannya. Lanjut kerja!',
    ];

    const BALASAN_ADA_KENDALA = [
        '✅ Laporan diterima. Kendala kamu udah diteruskan ke Owner, ditunggu ya.',
        '✅ Diterima, Owner udah dikabari soal kendalanya. Semangat!',
    ];

    const JAM_LAPOR_PROGRESS        = '11:00';
    const JAM_BATAS_LAPOR_PROGRESS  = '12:30';

    public static function pilihPertanyaanProgress(int $userId, \Carbon\Carbon $tanggal): string
    {
        $index = ($tanggal->dayOfYear + $userId) % count(self::BANK_PERTANYAAN_PROGRESS);
        return self::BANK_PERTANYAAN_PROGRESS[$index];
    }

    public function index()
    {
        $user         = Auth::user();
        $absenHariIni = Absensi::where('user_id', $user->id)->whereDate('tanggal', today())->first();

        // Checkpoint 1 "Lapor Progress": kalau kelewat jam 12:30 belum lapor sama sekali -> denda flat
        if ($absenHariIni && $absenHariIni->jam_masuk
            && !$absenHariIni->jam_lapor_progress
            && !$absenHariIni->potongan_progress_dicatat
            && now()->format('H:i') >= self::JAM_BATAS_LAPOR_PROGRESS) {
            $potongan = self::POTONGAN_TELAT;
            $absenHariIni->update([
                'potongan_telat'            => ($absenHariIni->potongan_telat ?? 0) + $potongan,
                'gaji_hari_ini'             => ($absenHariIni->gaji_hari_ini ?? 0) - $potongan,
                'potongan_progress_dicatat' => true,
            ]);
            $absenHariIni->refresh();
        }

        // Checkpoint 2 "Kembali Kerja": kalau kelewat jam 14:00 belum lapor sama sekali -> denda flat
        // (LOGIC TIDAK BERUBAH dari sebelumnya, cuma nama kolom flag disamakan konteksnya)
        if ($absenHariIni && $absenHariIni->jam_masuk
            && !$absenHariIni->jam_absen_siang
            && !$absenHariIni->potongan_siang_dicatat
            && now()->format('H:i') >= self::JAM_SKIP_SIANG) {
            $potongan = self::POTONGAN_TELAT;
            $absenHariIni->update([
                'potongan_telat'         => ($absenHariIni->potongan_telat ?? 0) + $potongan,
                'gaji_hari_ini'          => ($absenHariIni->gaji_hari_ini ?? 0) - $potongan,
                'potongan_siang_dicatat' => true,
            ]);
            $absenHariIni->refresh();
        }

        $riwayat  = Absensi::where('user_id', $user->id)->orderBy('tanggal','desc')->limit(30)->get();
        $bulanIni = Absensi::where('user_id', $user->id)
                           ->whereMonth('tanggal', now()->month)
                           ->whereYear('tanggal', now()->year)->get();

        // Hari yang diaktifkan masuk kerja IKUT dihitung sebagai hari kerja biasa
        // (aktivasi membatalkan libur). `kerja_libur` dipisah hanya untuk menandai
        // upah ekstranya, bukan untuk mengeluarkannya dari statistik.
        $stats = array_merge(
            app(KerjaHariLiburService::class)->statistikKehadiran($bulanIni),
            [
                'total_um'       => $bulanIni->sum('uang_makan_hari_ini'),
                'total_potongan' => $bulanIni->sum('potongan_telat'),
                'total_gaji'     => $bulanIni->sum('gaji_hari_ini'),
            ]
        );

        $fase          = $this->getFaseAbsen($absenHariIni);
        $luarKotaAktif = LuarKota::getAktif($user->id);

        return view('absensi.index', compact('user','absenHariIni','riwayat','stats','fase','luarKotaAktif'));
    }

    public function formMasuk()
    {
        $user  = Auth::user();
        $absen = Absensi::where('user_id',$user->id)->whereDate('tanggal',today())->first();

        if ($absen?->jam_masuk) return redirect()->route('absensi.index')->with('info','Kamu sudah absen masuk hari ini.');

        // Izin/sakit/cuti/dinas luar yang tercatat SETELAH kode absen dibuat pagi.
        // Kodenya sudah terlanjur di tangan karyawan dan tidak ikut dibatalkan, jadi
        // tanpa pagar ini layar isi kode tetap terbuka dan absennya benar-benar masuk.
        // Definisinya satu: adaIzinHariIni() — alpha sengaja TIDAK termasuk, yang
        // terlanjur ditandai alpha tetap boleh absen.
        if ($this->adaIzinHariIni($user->id, today())) {
            return redirect()->route('absensi.index')->with('error','Hari ini kamu tercatat izin/sakit/cuti/dinas luar, jadi tidak perlu absen masuk. Kalau ini keliru, hubungi Owner atau Mandor.');
        }

        if (now()->format('H:i') < self::JAM_BUKA_ABSEN && !LuarKota::sedangLuarKota($user->id)) return redirect()->route('absensi.index')->with('error','Absen masuk baru bisa mulai jam 06:30');

        $lokasi        = $this->getLokasiUser($user->level);
        $setengahHari  = now()->format('H:i') >= self::JAM_SETENGAH;
        $luarKotaAktif = LuarKota::getAktif($user->id);

        return view('absensi.form-masuk', compact('user','lokasi','setengahHari','luarKotaAktif'));
    }

    public function absenMasuk(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'foto' => 'required|string',
            'lat'  => 'required|numeric',
            'lng'  => 'required|numeric',
            'kode' => 'required|string',
        ]);

        // ── IZIN HARI INI: pintu ditutup, kode lama tidak berlaku lagi ──
        //
        // Kode absen dibuat cron jam 06:30. Kalau siangnya karyawan ini dicatat
        // sakit/izin/cuti/dinas luar (atau ajuannya masih berjalan), kode paginya tetap
        // ada dan tetap cocok — lalu updateOrCreate di bawah MENIMPA baris izin itu jadi
        // 'hadir' berikut gaji + uang makan sehari penuh. Bukti izin yang sudah dicatat
        // Owner hilang tanpa jejak, tanpa error apa pun.
        //
        // Diperiksa paling awal: sebelum GPS dihitung dan sebelum foto diunggah ke R2,
        // biar penolakan ini tidak memakai kuota unggah sama sekali. Alpha TIDAK ikut
        // terjaring (bukan bagian daftar status izin) — yang terlanjur alpha tetap boleh absen.
        if ($this->adaIzinHariIni($user->id, today())) {
            return response()->json(['success'=>false,'message'=>'🚫 Hari ini kamu tercatat izin/sakit/cuti/dinas luar, jadi absen masuk ditutup. Kalau ini keliru, hubungi Owner atau Mandor.']);
        }

        // ── CEK MODE LUAR KOTA ──────────────────────────────────────────
        $sedangLuarKota = LuarKota::sedangLuarKota($user->id);

        // Validasi GPS — skip kalau luar kota
        $lokasi = $this->getLokasiUser($user->level);
        $jarak  = $this->hitungJarak($request->lat, $request->lng, $lokasi['lat'], $lokasi['lng']);
        if ($jarak > self::RADIUS_MASUK_PULANG && !$sedangLuarKota) {
            return response()->json(['success'=>false,'message'=>"📍 Lokasi terlalu jauh ({$this->formatJarak($jarak)}). Pastikan kamu sudah berada di lokasi kerja."]);
        }

        // Validasi kode absen (tetap wajib walau luar kota) — harus kode milik user ini sendiri
        $kode      = strtoupper(trim($request->kode));
        $kodeValid = \App\Models\KodeAbsen::whereDate('tanggal', today())
                                          ->where('user_id', $user->id)
                                          ->where('kode', $kode)
                                          ->exists();
        if (!$kodeValid) {
            return response()->json(['success'=>false,'message'=>'❌ Kode absen salah! Cek kode di Telegram kamu.']);
        }

        // ── HARI LIBUR: cuma boleh absen kalau sudah diaktifkan Owner/Mandor ──
        //
        // Otorisasi dicari DULUAN, tanpa syarat isLibur(). Sejak aktivasi membatalkan
        // libur (LiburService::expandAktivasi), isLibur() justru bernilai FALSE untuk
        // orang yang sudah diaktifkan — kalau urutannya dibalik, penanda kerja hari
        // libur dan upah ekstranya tidak pernah tersimpan.
        $otorisasiLibur = KerjaHariLibur::where(KerjaHariLibur::kunciUnik($user->id, today()))->first();

        if (!$otorisasiLibur && app(LiburService::class)->isLibur($user, today())) {
            return response()->json(['success'=>false,'message'=>'🗓️ Hari ini jadwal libur kamu. Kalau memang diminta masuk, Owner/Mandor perlu mengaktifkannya dulu.']);
        }

        $fotoPath = $this->simpanFotoBase64($request->foto,'absensi/'.$user->id.'/'.today()->format('Ymd'));
        if (!$fotoPath) {
            return response()->json(['success'=>false,'message'=>'Gagal menyimpan foto, coba lagi.']);
        }
        $jamSekarang  = now()->format('H:i');
        $setengahHari = $jamSekarang >= self::JAM_SETENGAH;
        $menitTelat   = $this->hitungMenitTelat($jamSekarang, $user->jam_masuk);

        // Kalau kerja hari libur, tarif diambil dari snapshot saat otorisasi dibuat
        // (perubahan tarif belakangan tidak mengubah histori hari itu).
        $tarifHarian = $otorisasiLibur ? (float)$otorisasiLibur->gaji_harian_snapshot : ($user->gaji_harian??0);
        $tarifUM     = $otorisasiLibur ? (float)$otorisasiLibur->uang_makan_snapshot  : ($user->uang_makan??0);

        if ($setengahHari) {
            $potongan    = 0;
            $status      = 'setengah_hari';
            $gajiHariIni = $tarifHarian*0.5;
            $uangMakan   = $tarifUM*0.5;
        } else {
            $potongan    = $this->hitungPotongan($menitTelat);
            $status      = $menitTelat>0?'telat':'hadir';
            $gajiHariIni = $tarifHarian-$potongan;
            $uangMakan   = $tarifUM;
        }

        // Upah hari libur disimpan KOTOR — potongan tetap lewat kolom potongan_telat,
        // biar tidak kepotong dua kali (lihat tests/kerja-hari-libur/test_payroll_kerja_hari_libur.php).
        $upahHariLibur = $otorisasiLibur
            ? app(KerjaHariLiburService::class)->upahHariLibur($otorisasiLibur->gaji_harian_snapshot, $status)
            : 0;

        Absensi::updateOrCreate(
            ['user_id'=>$user->id,'tanggal'=>today()],
            [
                'jam_masuk'       => now()->format('H:i:s'),
                'foto_masuk'      => $fotoPath,
                'lat_masuk'       => $request->lat,
                'lng_masuk'       => $request->lng,
                'gps_valid_masuk' => true, // selalu true, GPS tetap dicatat
                'status'          => $status,
                'potongan_telat'  => $potongan,
                'gaji_hari_ini'   => $gajiHariIni,
                'uang_makan_hari_ini' => $uangMakan,
                'kerja_hari_libur'    => (bool) $otorisasiLibur,
                'upah_hari_libur'     => $upahHariLibur,
            ]
        );

        $pesan = match($status) {
            'setengah_hari' => "⚠️ Absen masuk berhasil. Tercatat SETENGAH HARI (masuk jam ".now()->format('H:i').")",
            'telat'         => "✅ Absen masuk berhasil. Telat {$menitTelat} menit — potongan Rp ".number_format($potongan,0,',','.'),
            default         => "✅ Absen masuk berhasil jam ".now()->format('H:i'),
        };

        if ($otorisasiLibur) {
            $pesan .= "\n🗓️ Tercatat sebagai KERJA HARI LIBUR. Hari ini dihitung hari kerja biasa dan dibayar 1x gaji harian + uang makan.";
        }

        if ($sedangLuarKota) {
            $pesan .= "\n✈️ Mode luar kota aktif — GPS tidak divalidasi.";
        }

        return response()->json(['success'=>true,'message'=>$pesan,'redirect'=>route('absensi.index')]);
    }

    public function validasiKode(Request $request)
    {
        $user  = Auth::user();

        // Kode yang sudah dikirim pagi tidak ikut terhapus saat izin dicatat siangnya.
        // Endpoint ini yang dipakai layar isi kode untuk bilang "kode benar", jadi tanpa
        // pagar yang SAMA dengan absenMasuk() dia akan menghijaukan kode yang sebenarnya
        // sudah tidak berlaku — karyawan merasa aman, lalu ditolak di langkah terakhir.
        if ($this->adaIzinHariIni($user->id, today())) {
            return response()->json(['valid' => false]);
        }

        $kode  = strtoupper(trim($request->kode ?? ''));
        $valid = \App\Models\KodeAbsen::whereDate('tanggal', today())
                                      ->where('user_id', $user->id)
                                      ->where('kode', $kode)
                                      ->exists();
        return response()->json(['valid' => $valid]);
    }

    public function kodeHariIni()
    {
        $tanggal  = today();
        $karyawan = User::where('level', '!=', 1)
                        ->where('status', 'aktif')
                        ->orderBy('name')
                        ->get();

        $liburService = app(LiburService::class);

        $otorisasi = KerjaHariLibur::whereDate('tanggal', $tanggal)
                                   ->with('pengaktif')
                                   ->get()
                                   ->keyBy('user_id');

        // Izin hari ini diambil SEKALI untuk semua karyawan (bukan query per baris):
        // baris absensi yang sudah tercatat + ajuan izin yang masih berjalan.
        // Sumber yang sama dipakai adaIzinHariIni() saat tombolnya benar-benar ditekan.
        $statusIzin = Absensi::whereDate('tanggal', $tanggal)
                             ->whereIn('status', KerjaHariLiburService::STATUS_IZIN)
                             ->pluck('status', 'user_id');
        $ajuanIzin  = IzinAbsen::whereDate('tanggal', $tanggal)
                               ->whereIn('status', ['pending', 'approved'])
                               ->pluck('status', 'user_id');

        // Tombol "Aktifkan" hanya untuk target yang memang boleh diaktifkan aktor ini
        // (Owner: 2-7, Mandor: 3-7, dan tidak boleh dirinya sendiri). Pagar aslinya
        // tetap di alasanTolakAktivasi() — ini cuma supaya tombol yang PASTI ditolak
        // tidak dirender jadi jebakan klik.
        $aktor    = Auth::user();
        $svcKerja = app(KerjaHariLiburService::class);

        $data = $karyawan->map(function ($k) use ($tanggal, $liburService, $otorisasi, $statusIzin, $ajuanIzin, $aktor, $svcKerja) {
            $libur       = $liburService->isLibur($k, $tanggal);
            $kerjaLibur  = $otorisasi->get($k->id);

            return [
                'id'        => $k->id,
                'nama'      => $k->name,
                'jabatan'   => $k->jabatan,
                // HALAMAN INI BACA SAJA — tidak pernah membuat kode, untuk siapa pun.
                //
                // Dulu di sini dipanggil KodeAbsen::kodeHariIniUntuk(), yang MEMBUAT
                // baris kode kalau belum ada. Kalau halaman ini dibuka sebelum cron
                // 06:30, seluruh baris kode terlanjur dibuat lewat GET, dan cron
                // kemudian melewati SEMUA karyawan tanpa mengirim Telegram sama sekali
                // (dia menilai "sudah pernah dikirim" dari wasRecentlyCreated).
                //
                // Yang belum punya kode ditampilkan kosong + tombol "Buat & Kirim Kode".
                'kode'      => KodeAbsen::kodeHariIniUntukJikaAda($k),
                'connected' => (bool) $k->telegram_chat_id,
                'libur'     => $libur,
                // Sakit/izin/cuti/dinas luar: tidak ada tombol apa pun yang masuk akal
                // hari ini. Ditampilkan supaya jelas ini keadaan yang benar, bukan
                // kode yang gagal terkirim — dan supaya tidak ada tombol jebakan.
                'izin'      => KerjaHariLiburService::labelStatusIzin(
                                    $statusIzin->get($k->id),
                                    $ajuanIzin->has($k->id)
                               ),
                'boleh_aktivasi'  => $svcKerja->bolehTargetAktivasi($aktor->level, $k->level)
                                     && !$svcKerja->aktivasiDiriSendiri($aktor->id, $k->id),
                'kerja_libur'     => (bool) $kerjaLibur,
                'diaktifkan_oleh' => $kerjaLibur?->pengaktif?->name,
            ];
        });

        return view('absensi.kode-hari-ini', ['tanggal' => $tanggal, 'data' => $data]);
    }

    // Satu-satunya definisi "karyawan ini sudah tidak masuk hari ini, dan itu sah":
    // baris absensi berstatus sakit/izin/cuti/dinas luar, ATAU ajuan izin yang masih
    // pending/approved. Dipakai KEDUA pintu yang bisa membuat & mengirim kode absen
    // (aktivasi kerja hari libur dan tombol "Buat & Kirim Kode") supaya aturannya
    // tidak bisa diperbaiki sebelah — sebelumnya hanya jalur aktivasi yang menjaganya,
    // jadi karyawan yang sakit tetap bisa dibuatkan kode lewat tombol satunya.
    private function adaIzinHariIni($userId, $tanggal): bool
    {
        return Absensi::where('user_id', $userId)
                      ->whereDate('tanggal', $tanggal)
                      ->whereIn('status', KerjaHariLiburService::STATUS_IZIN)
                      ->exists()
            || IzinAbsen::where('user_id', $userId)
                        ->whereDate('tanggal', $tanggal)
                        ->whereIn('status', ['pending', 'approved'])
                        ->exists();
    }

    // ═══════════════════════════════════════════════════════════
    // AKTIFKAN "MASUK HARI LIBUR" (Owner/Mandor)
    // Aktivasi MEMBATALKAN libur di tanggal itu: hari itu jadi hari kerja biasa
    // (jatah libur terpakai, tanpa pengganti) + kode absen pribadi dikirim.
    // ═══════════════════════════════════════════════════════════
    public function aktifkanKerjaHariLibur($userId)
    {
        $aktor    = Auth::user();
        $karyawan = User::findOrFail($userId);
        $tanggal  = today();
        $svc      = app(KerjaHariLiburService::class);

        // Bentrok izin: yang sudah tercatat di absensi hari ini ATAU ajuan izin yang
        // masih berjalan. Definisinya di adaIzinHariIni() — dipakai bersama tombol
        // "Buat & Kirim Kode" biar dua pintu ini tidak pernah beda aturan.
        $adaIzin = $this->adaIzinHariIni($karyawan->id, $tanggal);

        $alasanTolak = $svc->alasanTolakAktivasi(
            $aktor->level,
            $karyawan->status,
            $karyawan->level,
            app(LiburService::class)->isLibur($karyawan, $tanggal),
            $adaIzin,
            $karyawan->gaji_harian,
            // ID diambil dari sesi & route, BUKAN dari body request — kalau tidak,
            // Mandor bisa memalsukan "aktor" lain dan menerobos larangan
            // mengaktifkan diri sendiri.
            $aktor->id,
            $karyawan->id
        );

        if ($alasanTolak) {
            return back()->with('error', $alasanTolak);
        }

        $otorisasi = KerjaHariLibur::firstOrCreate(
            KerjaHariLibur::kunciUnik($karyawan->id, $tanggal),
            array_merge(
                $svc->snapshot($karyawan->gaji_harian, $karyawan->uang_makan),
                ['diaktifkan_oleh' => $aktor->id]
            )
        );

        $kode = KodeAbsen::kodeHariIniUntuk($karyawan);

        // Klik ulang: tidak bikin baris kedua DAN tidak kirim notifikasi kedua.
        // (Pakai flash 'success' karena layout cuma merender success & error.)
        if (!$otorisasi->wasRecentlyCreated) {
            return back()->with('success', "{$karyawan->name} sudah diaktifkan sebelumnya (tidak dikirim ulang). Kode absennya: {$kode}");
        }

        $terkirim = false;
        if ($karyawan->telegram_chat_id) {
            $terkirim = app(TelegramService::class)->kirim($karyawan->telegram_chat_id,
                  "🏠 *PUSAT KANOPI BSD*\n"
                . "━━━━━━━━━━━━━━━━━━\n"
                . "📅 " . $tanggal->translatedFormat('l, d F Y') . "\n\n"
                . "Hari ini sebenarnya jadwal libur kamu, tapi diminta masuk.\n"
                . "Hari ini dihitung sebagai HARI KERJA BIASA — jatah libur hari ini terpakai (tidak ada hari pengganti), "
                . "dan kamu dibayar 1x gaji harian + uang makan.\n\n"
                . "🔑 *KODE ABSEN KAMU HARI INI:*\n"
                . "┌─────────────┐\n"
                . "│   *{$kode}*   │\n"
                . "└─────────────┘\n\n"
                . "Aturan absen tetap normal: lapor progress, lanjut kerja, dan absen pulang.\n"
                . "_CanopiBSD System_");
        }

        $pesan = $terkirim
            ? "{$karyawan->name} diaktifkan masuk hari ini. Kode absen sudah dikirim ke Telegram-nya."
            : "{$karyawan->name} diaktifkan masuk hari ini. Belum terhubung Telegram — kasih tahu kodenya manual: {$kode}";

        return back()->with('success', $pesan);
    }

    // ═══════════════════════════════════════════════════════════
    // BUAT & KIRIM KODE ABSEN MANUAL (Owner/Mandor)
    //
    // Jaring pengaman untuk karyawan aktif yang belum punya kode setelah cron pagi:
    // karyawan baru/diaktifkan kembali setelah 06:30, atau cron yang gagal jalan
    // (pernah kejadian: endpoint cron dibalas 403 di jam bulat, 6 Agustus).
    //
    // Halaman kode absen sendiri sekarang BACA SAJA, jadi pembuatan kode harus
    // lewat tindakan yang disengaja seperti ini — bukan efek samping membuka halaman.
    // ═══════════════════════════════════════════════════════════
    public function kirimKodeAbsen($userId)
    {
        $karyawan = User::findOrFail($userId);

        // Owner tidak pernah absen masuk (dikonfirmasi 11 Agustus), dan karyawan
        // nonaktif tidak boleh dapat kode yang bisa dipakai absen.
        if ($karyawan->status !== 'aktif') {
            return back()->with('error', "{$karyawan->name} statusnya tidak aktif, jadi tidak dibuatkan kode.");
        }
        if ($karyawan->level == 1) {
            return back()->with('error', 'Owner tidak ikut absen masuk.');
        }

        // Sakit/izin/cuti/dinas luar (atau ajuan izin yang masih berjalan): tidak
        // dibuatkan kode sama sekali. Aturan yang SAMA sudah dijaga di jalur aktivasi
        // dan di cron pagi sejak 6 Agustus; pintu manual ini dulu satu-satunya yang
        // melewatinya, jadi karyawan yang sudah tercatat sakit tetap bisa dikirimi
        // pesan "kode absen kamu hari ini" — mengundang absen di hari yang sudah izin.
        // Diperiksa SEBELUM kode dibuat maupun dikirim.
        if ($this->adaIzinHariIni($karyawan->id, today())) {
            return back()->with('error', "{$karyawan->name} hari ini tercatat izin/sakit/cuti/dinas luar, jadi tidak dibuatkan kode absen.");
        }

        // Hari libur yang BELUM diaktifkan tidak boleh dapat kode lewat pintu ini —
        // kalau boleh, tombol ini jadi jalan memutar yang melewati seluruh
        // pemeriksaan aktivasi (izin bentrok, tarif kosong, larangan self-activation)
        // dan menghasilkan kode berupah tanpa jejak siapa yang mengaktifkan.
        if (app(LiburService::class)->isLibur($karyawan, today())) {
            return back()->with('error', "{$karyawan->name} hari ini libur. Pakai tombol \"Aktifkan Masuk Hari Ini\" kalau memang diminta masuk.");
        }

        // Jalur atomik yang SAMA dengan cron pagi — dua permintaan barengan tidak
        // bisa menghasilkan dua kode valid untuk satu karyawan di satu hari.
        $baris = KodeAbsen::barisHariIniUntuk($karyawan);
        $kode  = $baris->kode;

        // Kodenya sudah ada sebelum permintaan ini -> sudah pernah dibuat/dikirim
        // hari ini. Jangan kirim ulang (insiden 6 Agustus: kode terkirim 4x sepagi).
        if (!$baris->wasRecentlyCreated) {
            return back()->with('success', "{$karyawan->name} sudah punya kode hari ini (tidak dikirim ulang). Kodenya: {$kode}");
        }

        $terkirim = false;
        if ($karyawan->telegram_chat_id) {
            $terkirim = app(TelegramService::class)->kirim($karyawan->telegram_chat_id,
                  "🏠 *PUSAT KANOPI BSD*\n"
                . "━━━━━━━━━━━━━━━━━━\n"
                . "📅 " . today()->translatedFormat('l, d F Y') . "\n\n"
                . "🔑 *KODE ABSEN KAMU HARI INI:*\n"
                . "┌─────────────┐\n"
                . "│   *{$kode}*   │\n"
                . "└─────────────┘\n\n"
                . "Pakai kode ini untuk absen masuk.\n"
                . "_CanopiBSD System_");
        }

        return back()->with('success', $terkirim
            ? "Kode absen {$karyawan->name} dibuat dan sudah dikirim ke Telegram-nya."
            : "Kode absen {$karyawan->name} dibuat. Belum terhubung Telegram — kasih tahu kodenya manual: {$kode}");
    }

    public function cekGps(Request $request)
    {
        $user   = Auth::user();
        $tipe   = $request->tipe ?? 'masuk';

        // Kalau luar kota — langsung valid
        if (LuarKota::sedangLuarKota($user->id)) {
            return response()->json(['valid'=>true,'jarak'=>'0m','meter'=>0,'luar_kota'=>true]);
        }

        $lokasi = $this->getLokasiCek($user->level, $tipe);
        $radius = $tipe==='siang' ? self::RADIUS_SIANG : self::RADIUS_MASUK_PULANG;
        $jarak  = $this->hitungJarak($request->lat,$request->lng,$lokasi['lat'],$lokasi['lng']);
        return response()->json(['valid'=>$jarak<=$radius,'jarak'=>$this->formatJarak($jarak),'meter'=>round($jarak),'luar_kota'=>false]);
    }

    public function formLaporProgress()
    {
        $user  = Auth::user();
        $absen = Absensi::where('user_id',$user->id)->whereDate('tanggal',today())->first();
        if (!$absen?->jam_masuk) return redirect()->route('absensi.index')->with('error','Kamu belum absen masuk pagi.');
        if ($absen?->jam_lapor_progress) return redirect()->route('absensi.index')->with('info','Kamu sudah lapor progress hari ini.');

        $lokasi        = $this->getLokasiCek($user->level,'siang');
        $gpsWajib      = in_array($user->level, self::LEVEL_KANTOR);
        $luarKotaAktif = LuarKota::getAktif($user->id);
        $pertanyaan    = self::pilihPertanyaanProgress($user->id, today());

        return view('absensi.form-lapor-progress', compact('user','lokasi','gpsWajib','luarKotaAktif','pertanyaan'));
    }

    public function laporProgress(Request $request)
    {
        $user  = Auth::user();
        $absen = Absensi::where('user_id',$user->id)->whereDate('tanggal',today())->first();
        if (!$absen) return response()->json(['success'=>false,'message'=>'Belum absen masuk pagi.']);
        if ($absen->jam_lapor_progress) return response()->json(['success'=>false,'message'=>'Kamu sudah lapor progress hari ini.']);

        $request->validate([
            'foto'             => 'required|string',
            'lat'              => 'required|numeric',
            'lng'              => 'required|numeric',
            'jawaban_progress' => 'required|string',
            'ada_kendala'      => 'required',
            'kendala_apa'      => 'required_if:ada_kendala,1',
            'kendala_kenapa'   => 'required_if:ada_kendala,1',
        ]);

        // ── CEK MODE LUAR KOTA ──────────────────────────────────────────
        $sedangLuarKota = LuarKota::sedangLuarKota($user->id);

        $gpsValid = true;
        if (in_array($user->level, self::LEVEL_KANTOR) && !$sedangLuarKota) {
            $lokasi   = $this->getLokasiCek($user->level,'siang');
            $jarak    = $this->hitungJarak($request->lat,$request->lng,$lokasi['lat'],$lokasi['lng']);
            $gpsValid = $jarak <= self::RADIUS_SIANG;
            if (!$gpsValid) {
                return response()->json(['success'=>false,'message'=>"📍 Lokasi terlalu jauh ({$this->formatJarak($jarak)})."]);
            }
        }

        $adaKendala = $request->ada_kendala == 1;
        $pertanyaan = self::pilihPertanyaanProgress($user->id, today());
        $folder     = 'absensi/'.$user->id.'/'.today()->format('Ymd');

        $fotoPath = $this->simpanFotoBase64($request->foto,$folder);
        if (!$fotoPath) {
            return response()->json(['success'=>false,'message'=>'Gagal menyimpan foto, coba lagi.']);
        }

        $absen->update([
            'foto_siang_1'        => $fotoPath,
            'lat_siang'           => $request->lat,
            'lng_siang'           => $request->lng,
            'gps_valid_siang'     => true, // selalu true, GPS tetap dicatat
            'jam_lapor_progress'  => now()->format('H:i:s'),
            'pertanyaan_progress' => $pertanyaan,
            'jawaban_progress'    => $request->jawaban_progress,
            'ada_kendala'         => $adaKendala,
            'deskripsi_kendala'   => $adaKendala ? $request->kendala_apa : null,
            'kendala_kenapa'      => $adaKendala ? $request->kendala_kenapa : null,
        ]);

        if ($adaKendala) $this->kirimNotifKendala($user,$absen);

        $balasan = $adaKendala
            ? self::BALASAN_ADA_KENDALA[array_rand(self::BALASAN_ADA_KENDALA)]
            : self::BALASAN_TANPA_KENDALA[array_rand(self::BALASAN_TANPA_KENDALA)];

        if ($sedangLuarKota) $balasan .= "\n✈️ Mode luar kota aktif.";

        return response()->json(['success'=>true,'message'=>$balasan,'redirect'=>route('absensi.index')]);
    }

    public function kembaliKerja(Request $request)
    {
        $user  = Auth::user();
        $absen = Absensi::where('user_id',$user->id)->whereDate('tanggal',today())->first();
        if (!$absen?->jam_masuk) return response()->json(['success'=>false,'message'=>'Kamu belum absen masuk pagi.']);
        if ($absen->jam_absen_siang) return response()->json(['success'=>false,'message'=>'Kamu sudah lapor kembali kerja hari ini.']);

        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $sedangLuarKota = LuarKota::sedangLuarKota($user->id);

        $gpsValid = true;
        if (in_array($user->level, self::LEVEL_KANTOR) && !$sedangLuarKota) {
            $lokasi   = $this->getLokasiCek($user->level,'siang');
            $jarak    = $this->hitungJarak($request->lat,$request->lng,$lokasi['lat'],$lokasi['lng']);
            $gpsValid = $jarak <= self::RADIUS_SIANG;
            if (!$gpsValid) {
                return response()->json(['success'=>false,'message'=>"📍 Lokasi terlalu jauh ({$this->formatJarak($jarak)})."]);
            }
        }

        $jamSekarang = now()->format('H:i');
        $menitTelat  = $this->hitungMenitTelat($jamSekarang, self::JAM_MASUK_SIANG, self::TOLERANSI_SIANG);
        $potongan    = $this->hitungPotongan($menitTelat);

        $absen->update([
            'jam_absen_siang'         => now()->format('H:i:s'),
            'lat_kembali_kerja'       => $request->lat,
            'lng_kembali_kerja'       => $request->lng,
            'gps_valid_kembali_kerja' => true,
            'potongan_telat'          => ($absen->potongan_telat??0) + $potongan,
            'potongan_siang_dicatat'  => true,
            'gaji_hari_ini'           => ($absen->gaji_hari_ini??0) - $potongan,
        ]);

        $pesan = $menitTelat>0
            ? "✅ Tercatat, lanjut kerja ya! Telat {$menitTelat} menit — potongan Rp".number_format($potongan,0,',','.')
            : "✅ Tercatat, lanjut kerja ya!";

        if ($sedangLuarKota) $pesan .= "\n✈️ Mode luar kota aktif.";

        return response()->json(['success'=>true,'message'=>$pesan,'redirect'=>route('absensi.index')]);
    }

    public function formPulang()
    {
        $user  = Auth::user();
        $absen = Absensi::where('user_id',$user->id)->whereDate('tanggal',today())->first();
        if (!$absen?->jam_masuk) return redirect()->route('absensi.index')->with('error','Kamu belum absen masuk.');
        if ($absen?->jam_pulang) return redirect()->route('absensi.index')->with('info','Kamu sudah absen pulang.');

        $lokasi        = $this->getLokasiUser($user->level);
        $adaLembur     = $absen->lembur_approved ?? false;
        $absenHariIni  = $absen;
        $luarKotaAktif = LuarKota::getAktif($user->id);

        return view('absensi.form-pulang', compact('user','lokasi','absen','absenHariIni','adaLembur','luarKotaAktif'));
    }

    public function absenPulang(Request $request)
    {
        $user  = Auth::user();
        $absen = Absensi::where('user_id',$user->id)->whereDate('tanggal',today())->first();
        if (!$absen) return response()->json(['success'=>false,'message'=>'Belum absen masuk.']);

        $request->validate(['foto'=>'required|string','lat'=>'required|numeric','lng'=>'required|numeric']);

        // ── CEK MODE LUAR KOTA ──────────────────────────────────────────
        $sedangLuarKota = LuarKota::sedangLuarKota($user->id);

        // Validasi GPS pulang — skip kalau luar kota
        $lokasi = $this->getLokasiUser($user->level);
        $jarak  = $this->hitungJarak($request->lat,$request->lng,$lokasi['lat'],$lokasi['lng']);
        if ($jarak > self::RADIUS_MASUK_PULANG && !$sedangLuarKota) {
            return response()->json(['success'=>false,'message'=>"📍 Lokasi terlalu jauh ({$this->formatJarak($jarak)})."]);
        }

        $fotoPath = $this->simpanFotoBase64($request->foto,'absensi/'.$user->id.'/'.today()->format('Ymd'));
        if (!$fotoPath) {
            return response()->json(['success'=>false,'message'=>'Gagal menyimpan foto, coba lagi.']);
        }
        $jamPulang = now()->format('H:i:s');

        $menitKerja   = $this->hitungMenitKerja($absen->jam_masuk,$jamPulang);
        $setengahHari = $absen->status!=='setengah_hari' && $menitKerja<225;

        // Jam lembur DICATAT di sini, tapi NOMINALNYA tidak dibayar di sini.
        // Dulu nominalnya ikut dijumlahkan ke `gaji_hari_ini` DAN `lembur_jam` juga
        // disimpan, lalu GajiService membayar lagi dari `lembur_jam` -> pegawai harian
        // (gaji pokoknya diakumulasi dari `gaji_hari_ini`) dibayar DUA KALI.
        // Pembaginya pun beda: di sini dulu /7,5 sementara slip memakai /9.
        // Sekarang satu rumus, satu pembayar: GajiService::bonusLembur() di slip.
        $lemburJam = 0;
        if ($absen->lembur_approved && now()->format('H:i')>=substr($user->jam_pulang,0,5)) {
            $lemburJam = min(round($this->hitungMenitTelat(now()->format('H:i'),substr($user->jam_pulang,0,5))/60,2),self::LEMBUR_MAX_JAM);
        }
        // Nominalnya sengaja TIDAK dihitung di sini sama sekali — supaya tidak ada
        // angka lembur kedua yang bisa menyimpang dari slip. Pesan ke karyawan cukup
        // menyebut JAM-nya; rupiahnya muncul di slip lewat GajiService::bonusLembur().

        $statusBaru = $setengahHari?'setengah_hari':$absen->status;

        // Kerja hari libur: kalau status berubah jadi setengah hari, upah hari liburnya
        // ikut menyesuaikan (0.5x) — kalau tidak, pegawai bulanan kebayar 1x penuh.
        $tarifHarian   = $user->gaji_harian??0;
        $tarifUM       = $user->uang_makan??0;
        $upahHariLibur = $absen->upah_hari_libur ?? 0;
        if ($absen->kerja_hari_libur) {
            $otorisasiLibur = KerjaHariLibur::where(KerjaHariLibur::kunciUnik($absen->user_id, $absen->tanggal))->first();
            if ($otorisasiLibur) {
                $tarifHarian = (float) $otorisasiLibur->gaji_harian_snapshot;
                $tarifUM     = (float) $otorisasiLibur->uang_makan_snapshot;
            }
            $upahHariLibur = app(KerjaHariLiburService::class)->upahHariLibur($tarifHarian, $statusBaru);
        }

        $umHariIni  = $statusBaru==='setengah_hari'?$tarifUM*0.5:$tarifUM;
        // TANPA nominal lembur — lembur dibayar sekali saja, di slip.
        $gajiBersih = $statusBaru==='setengah_hari'
            ?$tarifHarian*0.5-($absen->potongan_telat??0)
            :($absen->gaji_hari_ini??0);

        $absen->update([
            'jam_pulang'          => $jamPulang,
            'foto_pulang'         => $fotoPath,
            'lat_pulang'          => $request->lat,
            'lng_pulang'          => $request->lng,
            'gps_valid_pulang'    => true, // selalu true, GPS tetap dicatat
            'status'              => $statusBaru,
            'uang_makan_hari_ini' => $umHariIni,
            'gaji_hari_ini'       => $gajiBersih,
            'upah_hari_libur'     => $upahHariLibur,
            'lembur_jam'          => $lemburJam,
        ]);

        $pesan = "✅ Absen pulang berhasil jam ".now()->format('H:i');
        if ($setengahHari) $pesan .= " (setengah hari)";
        if ($lemburJam>0) $pesan .= " + lembur {$lemburJam} jam";
        if ($sedangLuarKota) $pesan .= "\n✈️ Mode luar kota aktif.";

        return response()->json(['success'=>true,'message'=>$pesan,'redirect'=>route('absensi.index')]);
    }

    public function rekap(Request $request)
    {
        $tanggal     = $request->tanggal ?? today()->format('Y-m-d');
        $levelFilter = (int)($request->level ?? 0);

        $query = User::where('level', '!=', 1)
                     ->where('status', 'aktif')
                     ->with(['absensi' => fn($q) => $q->whereDate('tanggal', $tanggal)]);

        if ($levelFilter > 0) $query->where('level', $levelFilter);

        $karyawan = $query->orderBy('level')->orderBy('name')->get();

        // Siapa yang sedang luar kota hari ini
        $sedangLuarKota = LuarKota::aktifPadaTanggal()->pluck('user_id')->toArray();

        return view('absensi.rekap', compact('karyawan', 'tanggal', 'levelFilter', 'sedangLuarKota'));
    }

    public function rekapBulanan(Request $request)
    {
        $bulan  = (int)($request->bulan ?? now()->month);
        $tahun  = (int)($request->tahun ?? now()->year);
        $userId = $request->user_id;
        $user   = Auth::user();

        // Halaman ini menampilkan absensi + NOMINAL GAJI. Hanya Owner yang boleh
        // lintas-karyawan; SEMUA level lain dipaksa melihat dirinya sendiri saja.
        //
        // Ambang lama `level > 2` membiarkan Admin Operasional (level 2) melihat
        // seluruh karyawan. Tapi halaman ini juga dipakai karyawan biasa untuk rekap
        // absensinya sendiri, jadi menguncinya lewat middleware `level:1` justru
        // memutus akses 13 orang — pagarnya harus di sini, bukan di route.
        $bolehSemua = self::bolehRekapSemua($user->level);
        if (!$bolehSemua) $userId = $user->id;

        $hariDalamBulan = \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->daysInMonth;

        $daftarKaryawan = collect();
        if ($bolehSemua) {
            $daftarKaryawan = User::where('level','!=',1)->where('status','aktif')->orderBy('name')->get();
        }

        if ($userId) {
            $karyawanList = User::where('id', $userId)->get();
        } elseif ($bolehSemua) {
            $karyawanList = User::where('level','!=',1)->where('status','aktif')->orderBy('level')->orderBy('name')->get();
        } else {
            $karyawanList = User::where('id', $user->id)->get();
        }

        $rekapData = [];
        foreach ($karyawanList as $k) {
            $absensiRaw = Absensi::where('user_id', $k->id)
                                 ->whereMonth('tanggal', $bulan)
                                 ->whereYear('tanggal', $tahun)
                                 ->get()
                                 ->keyBy(fn($a) => \Carbon\Carbon::parse($a->tanggal)->format('Y-m-d'));

            // Hari yang diaktifkan masuk kerja IKUT dihitung (aktivasi membatalkan
            // libur). `kerja_libur` dipisah hanya untuk menandai upah ekstranya.
            $stats = array_merge(
                app(KerjaHariLiburService::class)->statistikKehadiran($absensiRaw),
                [
                    'total_potongan' => $absensiRaw->sum('potongan_telat'),
                    'total_gaji'     => $absensiRaw->sum('gaji_hari_ini'),
                    'total_um'       => $absensiRaw->sum('uang_makan_hari_ini'),
                ]
            );

            $rekapData[] = [
                'karyawan'         => $k,
                'absensi'          => $absensiRaw,
                'stats'            => $stats,
                'hari_dalam_bulan' => $hariDalamBulan,
                // Peta libur per karyawan — view tidak boleh nebak "Minggu = libur" lagi.
                'peta_libur'       => app(LiburService::class)->petaLiburBulan($k, $bulan, $tahun),
            ];
        }

        return view('absensi.rekap-bulanan', compact('rekapData','bulan','tahun','userId','daftarKaryawan'));
    }

    public function koreksi(Request $request, $id)
    {
        $request->validate([
            'jam_masuk'      => 'nullable|date_format:H:i',
            'jam_pulang'     => 'nullable|date_format:H:i',
            // Dibatasi daftar status yang dikenal mesin gaji. Dengan `required|string`
            // polos, status salah ketik tersimpan apa adanya sementara nominalKoreksi()
            // mengembalikan null — nominal lama tertinggal di baris berstatus baru, dan
            // barisnya hilang dari semua statistik/KPI tanpa error apa pun.
            'status'         => KerjaHariLiburService::aturanStatusKoreksi(),
            'potongan_telat' => 'nullable|integer|min:0',
            'alasan'         => 'required|string',
        ]);

        $absen         = Absensi::findOrFail($id);
        $user          = $absen->user;
        $potonganTelat = $request->filled('potongan_telat') ? (int) $request->potongan_telat : ($absen->potongan_telat ?? 0);
        $svcLibur      = app(KerjaHariLiburService::class);

        // Baris kerja hari libur dikoreksi memakai SNAPSHOT tarif saat diaktifkan —
        // gaji harian, uang makan, dan upah hari liburnya. Kalau pakai tarif karyawan
        // sekarang, kenaikan gaji belakangan akan mengubah histori bulan yang sudah lewat.
        $snapshot = $absen->kerja_hari_libur
            ? KerjaHariLibur::where(KerjaHariLibur::kunciUnik($absen->user_id, $absen->tanggal))->first()
            : null;

        $tarif = $svcLibur->tarifKoreksi(
            (bool) $absen->kerja_hari_libur,
            $snapshot?->gaji_harian_snapshot,
            $snapshot?->uang_makan_snapshot,
            $user->gaji_harian ?? 0,
            $user->uang_makan ?? 0
        );

        // Formula gaji/UM/potongan tidak berubah — cuma sumber tarifnya (lihat KerjaHariLiburService).
        $nominal     = $svcLibur->nominalKoreksi($request->status, $tarif['gaji_harian'], $tarif['uang_makan'], (float) $potonganTelat);
        $gajiHariIni = $nominal['gaji_hari_ini']       ?? $absen->gaji_hari_ini;
        $umHariIni   = $nominal['uang_makan_hari_ini'] ?? $absen->uang_makan_hari_ini;

        // Penanda kerja hari libur TIDAK dihapus koreksi — cuma nominal upahnya
        // yang ikut menyesuaikan status baru (tetap kotor, potongan lewat potongan_telat).
        $upahHariLibur = $absen->upah_hari_libur ?? 0;
        if ($absen->kerja_hari_libur) {
            $upahHariLibur = $nominal['upah_hari_libur'] ?? $upahHariLibur;
        }

        $absen->update([
            'jam_masuk'           => $request->jam_masuk ? $request->jam_masuk.':00' : $absen->jam_masuk,
            'jam_pulang'          => $request->jam_pulang ? $request->jam_pulang.':00' : $absen->jam_pulang,
            'status'              => $request->status,
            'potongan_telat'      => $potonganTelat,
            'gaji_hari_ini'       => $gajiHariIni,
            'uang_makan_hari_ini' => $umHariIni,
            'upah_hari_libur'     => $upahHariLibur,
            'dikoreksi'           => true,
            'alasan_koreksi'      => $request->alasan,
            'dikoreksi_oleh'      => Auth::id(),
        ]);

        return back()->with('success', 'Koreksi absen berhasil disimpan.');
    }

    public function koreksiManual(Request $request, $userId)
    {
        $request->validate([
            'jam_masuk'  => 'nullable|date_format:H:i',
            'jam_pulang' => 'nullable|date_format:H:i',
            // Daftar status yang sama dengan koreksi() — lihat alasannya di sana.
            // Di jalur ini taruhannya lebih besar: status juga menentukan apakah baris
            // otorisasi kerja hari libur berupah ikut dibuat (STATUS_BEKERJA).
            'status'     => KerjaHariLiburService::aturanStatusKoreksi(),
            'alasan'     => 'required|string',
        ]);

        $aktor = Auth::user();
        $user  = User::findOrFail($userId);

        // Tanggal WAJIB datang dari filter rekap yang sedang dibuka — tidak boleh
        // diam-diam jatuh ke hari ini. Dicek PALING AWAL, sebelum baris absensi
        // maupun baris otorisasi kerja hari libur ditulis.
        if ($alasanTanggal = self::alasanTolakTanggalKoreksi($request->tanggal)) {
            return back()->with('error', $alasanTanggal);
        }
        $tanggal = $request->tanggal;

        // Catat manual di tanggal yang memang libur karyawan ini -> ditandai kerja hari
        // libur HANYA kalau ada audit otorisasi yang sah, supaya upah hari libur tidak
        // bisa diberikan tanpa jejak siapa yang mengaktifkan.
        $tanggalCarbon = $tanggal instanceof \Carbon\Carbon ? $tanggal : \Carbon\Carbon::parse($tanggal);

        // Jalur manual HANYA untuk tanggal yang belum punya baris absensi. Kalau
        // barisnya sudah ada, dia harus lewat tombol Koreksi — kalau ditimpa di sini,
        // jam masuk/pulang, GPS, foto, dan penanda kerja hari libur yang sudah tercatat
        // hilang tanpa jejak. Dicek SEBELUM audit otorisasi dibuat, biar permintaan yang
        // ditolak tidak meninggalkan baris kerja_hari_libur nyasar.
        $sudahAdaBaris = Absensi::where('user_id', $user->id)
                                ->whereDate('tanggal', $tanggalCarbon)
                                ->exists();

        if ($alasanTolak = self::alasanTolakKoreksiManual($sudahAdaBaris)) {
            return back()->with('error', $alasanTolak);
        }

        $svcLibur      = app(KerjaHariLiburService::class);
        $isLibur       = app(LiburService::class)->isLibur($user, $tanggalCarbon);

        // Dicari tanpa syarat isLibur: kalau jadwal libur karyawan diubah setelah
        // hari itu lewat, baris otorisasinya tetap jadi bukti hari itu kerja hari libur.
        $otorisasi = KerjaHariLibur::where(KerjaHariLibur::kunciUnik($user->id, $tanggalCarbon))->first();

        // Pintu KEDUA yang bisa melahirkan baris kerja hari libur berupah (yang pertama
        // tombol Aktivasi). Jalur ini tidak lewat alasanTolakAktivasi(), jadi aturan
        // tarifnya dipanggil terpisah dari helper yang SAMA — kalau tidak, karyawan
        // bergaji harian kosong bisa tercatat masuk di hari liburnya dan dibayar Rp 0
        // lewat jalur manual. Dicek sebelum otorisasi/absensi ditulis, biar permintaan
        // yang ditolak tidak meninggalkan baris nyasar.
        // Hanya untuk tanggal libur + status yang benar-benar bekerja: hari kerja biasa
        // dan status alpha/izin tidak membayar upah hari libur, jadi tidak ikut diblokir.
        if ($isLibur && in_array($request->status, KerjaHariLiburService::STATUS_BEKERJA, true)) {
            // Tarif efektifnya: snapshot otorisasi kalau barisnya sudah ada, kalau belum
            // ya tarif karyawan sekarang (itu yang akan dibekukan jadi snapshot di bawah).
            $tarifDasar = $otorisasi?->gaji_harian_snapshot ?? ($user->gaji_harian ?? 0);
            if ($alasanTarif = $svcLibur->alasanTolakTarif($tarifDasar)) {
                return back()->with('error', $alasanTarif);
            }
        }

        // Audit baru cuma dibuat kalau aktornya Owner/Mandor dan statusnya benar-benar
        // bekerja (hadir/telat/setengah hari) — alpha/izin tidak pernah bikin otorisasi.
        if ($svcLibur->buatAuditManual($aktor->level, $isLibur, $request->status, (bool) $otorisasi)) {
            $otorisasi = KerjaHariLibur::firstOrCreate(
                KerjaHariLibur::kunciUnik($user->id, $tanggalCarbon),
                array_merge(
                    $svcLibur->snapshot($user->gaji_harian, $user->uang_makan),
                    ['diaktifkan_oleh' => $aktor->id]
                )
            );
        }

        $kerjaHariLibur = $svcLibur->tandaiKerjaLiburManual($aktor->level, $isLibur, $request->status, (bool) $otorisasi);

        // Tarif dari snapshot otorisasi (kalau ada), bukan tarif karyawan saat ini.
        $tarif = $svcLibur->tarifKoreksi(
            $kerjaHariLibur,
            $otorisasi?->gaji_harian_snapshot,
            $otorisasi?->uang_makan_snapshot,
            $user->gaji_harian ?? 0,
            $user->uang_makan ?? 0
        );

        $upahHariLibur = $kerjaHariLibur
            ? $svcLibur->upahHariLibur($tarif['gaji_harian'], $request->status)
            : 0;

        $gajiHariIni = match($request->status) {
            'hadir', 'telat' => $tarif['gaji_harian'],
            'setengah_hari'  => $tarif['gaji_harian'] * 0.5,
            default          => 0,
        };
        $umHariIni = match($request->status) {
            'hadir', 'telat', 'sakit', 'izin', 'cuti', 'dinas_luar' => $tarif['uang_makan'],
            'setengah_hari' => $tarif['uang_makan'] * 0.5,
            default         => 0,
        };

        Absensi::create(
            [
                'user_id'             => $user->id,
                'tanggal'             => $tanggal,
                'jam_masuk'           => $request->jam_masuk ? $request->jam_masuk.':00' : null,
                'jam_pulang'          => $request->jam_pulang ? $request->jam_pulang.':00' : null,
                'status'              => $request->status,
                'gaji_hari_ini'       => $gajiHariIni,
                'uang_makan_hari_ini' => $umHariIni,
                'kerja_hari_libur'    => $kerjaHariLibur,
                'upah_hari_libur'     => $upahHariLibur,
                'dikoreksi'           => true,
                'alasan_koreksi'      => $request->alasan,
                'dikoreksi_oleh'      => Auth::id(),
            ]
        );

        return back()->with('success', 'Absen manual berhasil dicatat untuk '.$user->name);
    }

    /**
     * Siapa yang boleh melihat rekap LINTAS-KARYAWAN (dan nominal gajinya): Owner saja.
     *
     * Semua level lain dipaksa self-only. Dipakai controller DAN view rekap bulanan
     * supaya keduanya tidak pernah menyimpang (tombol yang tampil harus persis sama
     * dengan yang diizinkan server).
     *
     * Perbandingan pakai == (loose): kolom `level` tidak punya cast di model User,
     * jadi dari DB bisa datang sebagai string "1". Nilai kosong/non-numerik gagal
     * TERTUTUP (dianggap bukan Owner).
     *
     * Murni — diuji di tests/keamanan/test_regresi_minor.php.
     */
    public static function bolehRekapSemua($levelAktor): bool
    {
        if ($levelAktor === null || $levelAktor === '' || !is_numeric($levelAktor)) return false;
        return $levelAktor == 1;
    }

    /**
     * Penjaga tanggal untuk pencatatan absen MANUAL.
     * Balikan null = boleh; string = alasan tolak (langsung dipakai sebagai pesan).
     *
     * Kenapa ketat: halaman rekap punya filter tanggal, tapi form Koreksi di dalamnya
     * dulu TIDAK mengirim tanggal sama sekali, jadi controller jatuh ke `today()`.
     * Owner yang memfilter ke 10 Agustus lalu mencatat absen manual akan menulis
     * barisnya ke HARI INI — tanggal yang mau diperbaiki tetap kosong, hari ini malah
     * dapat baris palsu, dan pemeriksaan "kerja hari libur" (termasuk pembuatan baris
     * otorisasi berupah) dilakukan atas tanggal yang keliru.
     *
     * Format WAJIB `Y-m-d` persis: kata relatif seperti "today"/"yesterday" ditolak
     * supaya tanggal tidak pernah ditentukan oleh teks bebas dari browser.
     *
     * Murni, tanpa database — diuji di tests/kerja-hari-libur/test_koreksi_tanggal.php.
     */
    public static function alasanTolakTanggalKoreksi($tanggal, ?\Carbon\Carbon $hariIni = null): ?string
    {
        $teks = is_string($tanggal) ? trim($tanggal) : '';
        if ($teks === '') {
            return 'Tanggal koreksi tidak terbaca. Muat ulang halaman rekap lalu coba lagi.';
        }

        // createFromFormat + cek balik: menolak tanggal yang formatnya benar tapi
        // isinya tidak ada di kalender (mis. 2026-02-31, yang kalau tidak dicek
        // akan digeser diam-diam jadi 3 Maret). Input yang benar-benar ngawur
        // membuat Carbon melempar exception — ditangkap di sini, bukan dibiarkan
        // jadi 500 di layar Owner.
        try {
            $tgl = \Carbon\Carbon::createFromFormat('Y-m-d', $teks);
        } catch (\Throwable $e) {
            return 'Format tanggal koreksi tidak sah. Harus YYYY-MM-DD.';
        }
        if (!$tgl || $tgl->format('Y-m-d') !== $teks) {
            return 'Format tanggal koreksi tidak sah. Harus YYYY-MM-DD.';
        }

        $acuan = ($hariIni ?? \Carbon\Carbon::today())->copy()->startOfDay();
        if ($tgl->startOfDay()->greaterThan($acuan)) {
            return 'Tanggal koreksi tidak boleh di masa depan.';
        }

        return null;
    }

    // Kapan pencatatan absen MANUAL boleh dilakukan. Balikan null = boleh;
    // string = alasan tolak (langsung dipakai sebagai pesan ke layar).
    // Murni, tanpa database — diuji di tests/kerja-hari-libur/test_koreksi_kerja_hari_libur.php.
    public static function alasanTolakKoreksiManual(bool $sudahAdaBaris): ?string
    {
        if ($sudahAdaBaris) {
            return 'Karyawan ini sudah punya data absen di tanggal tersebut. '
                 . 'Pakai tombol Koreksi pada barisnya supaya jam, GPS, dan catatan lama tidak hilang.';
        }
        return null;
    }

    private function getLokasiUser(int $level): array
    {
        return in_array($level,self::LEVEL_WORKSHOP)?self::LOKASI['workshop']:self::LOKASI['kantor'];
    }

    private function getLokasiCek(int $level, string $tipe): array
    {
        if ($tipe==='siang' && in_array($level,self::LEVEL_WORKSHOP)) return self::LOKASI['workshop'];
        return $this->getLokasiUser($level);
    }

    private function hitungJarak(float $lat1,float $lng1,float $lat2,float $lng2): float
    {
        $R=6371000; $dLat=deg2rad($lat2-$lat1); $dLng=deg2rad($lng2-$lng1);
        $a=sin($dLat/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)**2;
        return $R*2*atan2(sqrt($a),sqrt(1-$a));
    }

    private function formatJarak(float $meter): string
    { return $meter>=1000?round($meter/1000,1).'km':round($meter).'m'; }

    private function hitungMenitTelat(string $jamSekarang,string $jamTarget,int $toleransi=0): int
    { return max(0,(int)((strtotime($jamSekarang)-strtotime($jamTarget)-($toleransi*60))/60)); }

    private function hitungPotongan(int $menitTelat): float
    { return ($menitTelat/60)*self::POTONGAN_TELAT; }

    private function hitungMenitKerja(string $jamMasuk,string $jamPulang): int
    {
        $total=(strtotime($jamPulang)-strtotime($jamMasuk))/60;
        return (int)(strtotime($jamPulang)>strtotime(self::JAM_MASUK_SIANG.':00')?$total-60:$total);
    }

    private function getFaseAbsen(?Absensi $absen): string
    {
        if (!$absen||!$absen->jam_masuk) return 'belum_masuk';
        $jam = now()->format('H:i');
        if (!$absen->jam_lapor_progress) {
            if ($jam >= self::JAM_LAPOR_PROGRESS && $jam < self::JAM_BATAS_LAPOR_PROGRESS) return 'perlu_lapor_progress';
        }
        if (!$absen->jam_absen_siang) {
            if ($jam >= self::JAM_MASUK_SIANG && $jam < self::JAM_SKIP_SIANG) return 'perlu_kembali_kerja';
        }
        if (!$absen->jam_pulang) return 'perlu_pulang';
        return 'lengkap';
    }

    private function simpanFotoBase64(string $base64,string $folder): ?string
    {
        $imageData=preg_replace('/^data:image\/\w+;base64,/','',$base64);
        $filename=$folder.'/'.date('His').'_'.uniqid().'.jpg';
        return app(R2Service::class)->put($filename, base64_decode($imageData), 'image/jpeg');
    }

    private function kirimNotifKendala(User $user,Absensi $absen): void
    {
        $penerima=User::whereIn('level',[1,3])->whereNotNull('telegram_chat_id')->get();
        foreach ($penerima as $p) {
            app(TelegramService::class)->kirim($p->telegram_chat_id,"⚠️ *LAPORAN KENDALA*\nKaryawan: {$user->name}\nJabatan: {$user->jabatan}\nTanggal: ".today()->format('d/m/Y')."\nProgress: {$absen->jawaban_progress}\nKendala: {$absen->deskripsi_kendala}\nPenyebab: {$absen->kendala_kenapa}\n---\nCek detail di app.kanopibsd.co.id");
        }
    }
}