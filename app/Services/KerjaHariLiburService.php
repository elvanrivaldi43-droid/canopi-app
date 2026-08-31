<?php
// FILE: app/Services/KerjaHariLiburService.php
// Logic murni kerja hari libur — testable tanpa database.

namespace App\Services;

use Illuminate\Support\Collection;

class KerjaHariLiburService
{
    const LEVEL_BOLEH_AKTIVASI = [1, 3]; // Owner & Supervisor/Mandor

    // Status yang berarti karyawan BENAR-BENAR bekerja hari itu.
    // Alpha/izin/sakit/cuti/dinas luar bukan hari kerja libur yang dibayar.
    const STATUS_BEKERJA = ['hadir', 'telat', 'setengah_hari'];

    // Status absensi yang berarti "hari ini karyawan memang tidak masuk, dan itu sah".
    // Dipakai SEMUA pintu yang bisa membuat/mengirim kode absen — kalau daftarnya
    // ditulis ulang per pintu, satu pintu bisa diperbaiki dan yang lain ketinggalan
    // (persis yang terjadi: aktivasi memeriksanya, tombol kirim kode manual tidak).
    const STATUS_IZIN = ['sakit', 'izin', 'cuti', 'dinas_luar'];

    // Status yang boleh dipilih saat Owner mengoreksi/mencatat absen manual.
    // Ini SATU-SATUNYA daftar yang sah: dipakai aturan validasi kedua endpoint
    // koreksi dan dropdown di layar rekap. Semuanya dikenal nominalKoreksi() —
    // status di luar daftar ini bikin nominal ngambang (rupiah lama tertinggal
    // di baris berstatus baru) dan barisnya hilang dari statistik/KPI.
    const STATUS_KOREKSI = ['hadir', 'telat', 'setengah_hari', 'sakit', 'izin', 'cuti', 'dinas_luar', 'alpha'];

    // Level target yang boleh diaktifkan masuk hari libur, per aktor.
    // Owner: semua kecuali Owner (Owner tidak pernah absen masuk).
    // Mandor: level 3-7 saja. Admin Operasional (level 2) secara struktur di ATAS
    // Mandor dan jadwal/upahnya bukan urusan lapangan — aktivasi melahirkan baris
    // berupah, jadi untuk Admin harus Owner yang menekan tombolnya.
    const LEVEL_TARGET_AKTIVASI_OWNER  = [2, 3, 4, 5, 6, 7];
    const LEVEL_TARGET_AKTIVASI_MANDOR = [3, 4, 5, 6, 7];

    // Perbandingan level pakai == (loose) — kolom `level` tidak punya cast di model User,
    // mengikuti preseden perbaikan bug level/hari_libur_default di sesi sebelumnya.
    public function aktorBolehAktivasi($levelAktor): bool
    {
        foreach (self::LEVEL_BOLEH_AKTIVASI as $lv) {
            if ($levelAktor == $lv) return true;
        }
        return false;
    }

    // Level target yang boleh diaktifkan aktor ini. Level aktor lain -> kosong
    // (gagal TERTUTUP), walau route sudah memagari level:1,3.
    public function levelTargetAktivasi($levelAktor): array
    {
        if ($levelAktor == 1) return self::LEVEL_TARGET_AKTIVASI_OWNER;
        if ($levelAktor == 3) return self::LEVEL_TARGET_AKTIVASI_MANDOR;
        return [];
    }

    // Level target dari DB bisa datang sebagai string ("5") — kolom `level` tidak
    // punya cast di model User, jadi dibandingkan longgar seperti preseden lain.
    // Level kosong/non-numerik tidak pernah dianggap cocok.
    public function bolehTargetAktivasi($levelAktor, $levelTarget): bool
    {
        if ($levelTarget === null || $levelTarget === '' || !is_numeric($levelTarget)) return false;
        foreach ($this->levelTargetAktivasi($levelAktor) as $lv) {
            if ($levelTarget == $lv) return true;
        }
        return false;
    }

    // Label izin untuk layar "Kode Absen Hari Ini" — null berarti tidak ada izin.
    // Baris absensi yang sudah tercatat menang atas ajuan yang masih berjalan:
    // yang sudah tercatat lebih pasti daripada yang baru diajukan.
    // Alpha SENGAJA bukan izin — karyawan alpha tetap boleh dibuatkan kode.
    public static function labelStatusIzin(?string $statusAbsensi, bool $adaAjuanIzin): ?string
    {
        $peta = ['sakit' => 'Sakit', 'izin' => 'Izin', 'cuti' => 'Cuti', 'dinas_luar' => 'Dinas Luar'];
        if ($statusAbsensi !== null && isset($peta[$statusAbsensi])) return $peta[$statusAbsensi];
        return $adaAjuanIzin ? 'Ajuan Izin' : null;
    }

    // Aturan validasi Laravel untuk field `status` di kedua endpoint koreksi.
    public static function aturanStatusKoreksi(): string
    {
        return 'required|in:' . implode(',', self::STATUS_KOREKSI);
    }

    // Apakah aktor sedang mengaktifkan DIRINYA SENDIRI?
    // ID dari route/DB sering berupa string ("7"), jadi dibandingkan numerik.
    // ID kosong/non-numerik tidak pernah dianggap cocok — controller wajib
    // mengirim ID asli, bukan mengandalkan nilai kosong untuk lolos.
    public function aktivasiDiriSendiri($idAktor, $idTarget): bool
    {
        if (!is_numeric($idAktor) || !is_numeric($idTarget)) return false;
        return (int) $idAktor === (int) $idTarget;
    }

    // Aturan boleh/tidaknya mengaktifkan "masuk hari libur" untuk 1 karyawan di 1 tanggal.
    // Balikan null = boleh; string = alasan tolak (dipakai langsung sebagai pesan ke layar).
    public function alasanTolakAktivasi($levelAktor, ?string $statusUser, $levelUser, bool $isLibur, bool $adaIzin, $gajiHarian, $idAktor, $idTarget): ?string
    {
        if (!$this->aktorBolehAktivasi($levelAktor)) {
            return 'Kamu tidak punya akses untuk mengaktifkan kerja hari libur.';
        }
        // Keputusan Bos: Mandor boleh mengaktifkan karyawan lain, TIDAK dirinya sendiri.
        // Aktivasi melahirkan baris berupah (1x gaji harian + uang makan) atas nama
        // orang yang menekan tombolnya — tanpa pagar ini, Mandor bisa memberi dirinya
        // hari kerja berbayar di hari liburnya kapan saja, tanpa persetujuan siapa pun.
        // Yang boleh mengaktifkan Mandor adalah Owner.
        // (Owner menargetkan diri sendiri sudah tertutup lebih dulu lewat aturan
        // "Owner tidak ikut absen masuk" di bawah.)
        if ($this->aktivasiDiriSendiri($idAktor, $idTarget)) {
            return 'Kamu tidak bisa mengaktifkan kerja hari libur untuk diri sendiri. '
                 . 'Minta Owner yang mengaktifkannya.';
        }
        if ($statusUser !== 'aktif') {
            return 'Karyawan ini statusnya tidak aktif.';
        }
        if ($levelUser == 1) {
            return 'Owner tidak ikut absen masuk.';
        }
        // Batas LEVEL target (beda dari batas identitas di atas): Mandor hanya boleh
        // ke level 3-7. Tanpa ini Mandor bisa mengaktifkan Admin Operasional — orang
        // di atasnya secara struktur — dan melahirkan baris berupah atas nama Admin
        // tanpa persetujuan Owner.
        if (!$this->bolehTargetAktivasi($levelAktor, $levelUser)) {
            $izin = $this->levelTargetAktivasi($levelAktor);
            return 'Kamu tidak bisa mengaktifkan karyawan di level ini. '
                 . ($izin ? 'Yang bisa kamu aktifkan: level ' . implode(', ', $izin) . '. ' : '')
                 . 'Minta Owner yang mengaktifkannya.';
        }
        if (!$isLibur) {
            return 'Karyawan ini bukan libur hari ini — kode absennya sudah dikirim otomatis pagi tadi.';
        }
        if ($adaIzin) {
            return 'Karyawan ini sedang izin/sakit/cuti/dinas luar hari ini.';
        }
        return $this->alasanTolakTarif($gajiHarian);
    }

    // Satu-satunya tempat aturan "tarif hari libur belum terisi" ditulis.
    // Upah kerja hari libur = 1x `users.gaji_harian` (dibekukan jadi snapshot saat
    // diaktifkan) — berlaku untuk SEMUA tipe gaji: harian, bulanan, maupun project.
    // Kalau kolomnya masih 0/kosong, karyawan dipanggil masuk di hari liburnya dan
    // dibayar Rp 0. Ditolak di depan, bukan baru ketahuan waktu slip gaji keluar.
    // Dipakai DUA pintu yang sama-sama bisa melahirkan baris kerja hari libur berupah:
    // tombol Aktivasi (alasanTolakAktivasi) dan koreksi manual di tanggal libur
    // (AbsensiController::koreksiManual) — biar aturannya tidak kembar dan tidak bisa
    // diperbaiki di satu pintu saja.
    // Balikan null = tarif sah; string = alasan tolak (langsung dipakai sebagai pesan).
    public function alasanTolakTarif($gajiHarian): ?string
    {
        if ((float) ($gajiHarian ?? 0) > 0) {
            return null;
        }
        return 'Gaji harian karyawan ini belum diisi, jadi kerja hari liburnya akan terbayar Rp 0. '
             . 'Lengkapi dulu di data karyawan, baru aktifkan.';
    }

    // Snapshot nominal saat otorisasi dibuat, supaya perubahan tarif karyawan
    // di kemudian hari tidak mengubah histori upah hari libur.
    public function snapshot($gajiHarian, $uangMakan): array
    {
        return [
            'gaji_harian_snapshot' => max(0.0, (float) ($gajiHarian ?? 0)),
            'uang_makan_snapshot'  => max(0.0, (float) ($uangMakan ?? 0)),
        ];
    }

    // Upah kerja hari libur untuk 1 hari, dihitung dari snapshot.
    // KOTOR (belum dikurangi potongan) — potongan telat tetap lewat kolom
    // potongan_telat yang sudah ada, biar tidak kepotong dua kali.
    public function upahHariLibur($gajiHarianSnapshot, ?string $status): float
    {
        $dasar = max(0.0, (float) ($gajiHarianSnapshot ?? 0));
        return match ($status) {
            'hadir', 'telat' => $dasar,
            'setengah_hari'  => $dasar * 0.5,
            default          => 0.0,
        };
    }

    // ── CRON ALPHA JAM 13:00 ───────────────────────────────────────
    // Hari libur biasa tetap dilewati (tidak pernah alpha). Tapi karyawan yang
    // sudah DIAKTIFKAN masuk hari libur sudah dijanjikan kerja dan dikirimi kode,
    // jadi kalau dia tidak masuk sama sekali dia ikut alur alpha normal.
    public function lewatiAlphaHariLibur(bool $isLibur, bool $adaOtorisasi): bool
    {
        return $isLibur && !$adaOtorisasi;
    }

    // Kolom penanda untuk baris alpha yang dibuat cron.
    // Ditandai audit sebagai jejak "hari ini memang hari aktivasi", tapi upahnya 0
    // karena orangnya tidak masuk. Alpha-nya TETAP dihitung sebagai alpha biasa:
    // tanggal aktivasi adalah hari kerja, jadi mangkir di situ memang harus terlihat
    // di rekap dan menurunkan KPI (lihat statistikKehadiran).
    public function atributAlphaHariLibur(bool $adaOtorisasi): array
    {
        return [
            'kerja_hari_libur' => $adaOtorisasi,
            'upah_hari_libur'  => 0.0,
        ];
    }

    // ── KOREKSI ABSEN ──────────────────────────────────────────────
    // Sumber tarif saat Owner/Mandor mengoreksi 1 baris absensi.
    // Baris kerja hari libur WAJIB pakai snapshot otorisasi: kalau pakai tarif
    // karyawan saat dikoreksi, kenaikan/penurunan gaji belakangan akan mengubah
    // histori bayaran hari libur yang sudah lewat.
    // Snapshot null (baris otorisasi hilang/terhapus) -> fallback tarif sekarang,
    // supaya koreksi tidak malah membayar 0.
    public function tarifKoreksi(bool $kerjaHariLibur, $gajiSnapshot, $umSnapshot, $gajiSekarang, $umSekarang): array
    {
        $pakaiSnapshot = $kerjaHariLibur && $gajiSnapshot !== null;
        return [
            'gaji_harian' => (float) ($pakaiSnapshot ? $gajiSnapshot : ($gajiSekarang ?? 0)),
            'uang_makan'  => (float) ($pakaiSnapshot ? ($umSnapshot ?? 0) : ($umSekarang ?? 0)),
        ];
    }

    // ═══════════════════════════════════════════════════════
    // LUPA ABSEN PULANG (keputusan Elvan 31 Ags 2026)
    // ═══════════════════════════════════════════════════════
    // Kasus nyata dari data production: Sahrul absen masuk 07:35, absen pulang
    // 20:00:25, kerja 12+ jam — tapi cron jam 20:00 keburu menandainya ALPHA dan
    // menolkan gajinya (telat 25 detik dari cron). Bryan & Sahrul kehilangan gaji
    // harian PLUS bonus KPI sebulan (alpha > 0 -> kelas KPI 'none'), padahal
    // dua-duanya benar-benar bekerja.
    //
    // Aturan baru: lupa menekan tombol pulang = DIBAYAR SEPARUH (`setengah_hari`,
    // status yang memang sudah ada & sudah dihitung separuh di sistem), BUKAN alpha.
    // Alpha tetap hanya untuk yang tidak absen masuk sama sekali.
    //
    // Fungsi-fungsi di bawah sengaja MURNI supaya bisa dites tanpa database.

    /** Status untuk cron jam 20:00. null = jangan diapa-apakan. */
    public function statusLupaPulang(bool $sudahMasuk, bool $sudahPulang): ?string
    {
        if (!$sudahMasuk) return null;   // belum masuk = urusan cabang alpha yang lain
        if ($sudahPulang) return null;   // sudah pulang = normal, jangan diutak-atik
        return 'setengah_hari';
    }

    /** Gaji hari itu kalau lupa absen pulang: separuh, dikurangi denda, mentok 0. */
    public function gajiLupaPulang(float $gajiHarian, float $potonganTelat): float
    {
        return max(0.0, ($gajiHarian * 0.5) - $potonganTelat);
    }

    /**
     * Boleh tidaknya denda checkpoint (lupa lapor progress / kembali kerja) dikenakan.
     * Hari yang BUKAN hari kerja tidak boleh didenda — inilah yang dulu membuat gaji
     * MINUS: orang sudah di-alpha (gaji 0), lalu membuka aplikasi, dendanya tetap
     * dipotong dari nol. Minus itu lalu menggerus gaji hari-hari lain, karena gaji
     * pokok harian = jumlah `gaji_hari_ini` sebulan.
     * Status null/kosong = baris baru yang belum ditandai -> boleh (perilaku lama).
     */
    public function bolehDendaCheckpoint(?string $status): bool
    {
        if ($status === null || $status === '') return true;
        return in_array($status, self::STATUS_BEKERJA, true);
    }

    /** Penjaga terakhir: gaji sehari tak pernah boleh di bawah nol. */
    public function kurangiDenda(float $gajiSekarang, float $potongan): float
    {
        return max(0.0, $gajiSekarang - $potongan);
    }

    // Nominal 1 hari hasil koreksi. Formula gaji/UM/potongan SAMA PERSIS dengan
    // aturan koreksi lama — yang berubah cuma dari mana tarifnya diambil (lihat tarifKoreksi).
    // Balikan null = status tidak dikenal, controller mempertahankan nilai lama.
    public function nominalKoreksi(?string $status, float $gajiHarian, float $uangMakan, float $potonganTelat): ?array
    {
        $gaji = match ($status) {
            'hadir', 'telat' => max(0, $gajiHarian - $potonganTelat),
            'setengah_hari'  => max(0, ($gajiHarian * 0.5) - $potonganTelat),
            'sakit', 'izin', 'cuti', 'dinas_luar', 'alpha' => 0,
            default          => null,
        };
        if ($gaji === null) return null;

        $um = match ($status) {
            'hadir', 'telat', 'sakit', 'izin', 'cuti', 'dinas_luar' => $uangMakan,
            'setengah_hari' => $uangMakan * 0.5,
            default         => 0, // alpha
        };

        return [
            'gaji_hari_ini'       => (float) $gaji,
            'uang_makan_hari_ini' => (float) $um,
            'upah_hari_libur'     => $this->upahHariLibur($gajiHarian, $status),
        ];
    }

    // Koreksi MANUAL (bikin/menimpa baris absensi) di tanggal yang memang libur.
    // Ditandai kerja hari libur hanya kalau ada audit otorisasi yang sah:
    //   - audit baru dibuat aktor level 1/3 untuk status yang benar-benar bekerja, ATAU
    //   - audit memang sudah ada sebelumnya (mis. diaktifkan pagi tadi, atau ditandai
    //     cron 13:00 karena diaktifkan tapi tidak masuk) -> fakta audit tidak dihapus.
    // Alpha/izin tanpa audit TIDAK pernah ditandai, dan upahnya 0 (lihat upahHariLibur).
    // Otorisasi yang sudah ada = fakta audit; tidak ikut hilang kalau jadwal libur
    // karyawan diubah Owner setelah hari itu lewat.
    public function tandaiKerjaLiburManual($levelAktor, bool $isLibur, ?string $status, bool $adaOtorisasi): bool
    {
        if ($adaOtorisasi) return true;
        if (!$isLibur) return false;
        return $this->buatAuditManual($levelAktor, $isLibur, $status, $adaOtorisasi);
    }

    // Kapan koreksi manual boleh MEMBUAT baris otorisasi baru (dengan snapshot tarif).
    public function buatAuditManual($levelAktor, bool $isLibur, ?string $status, bool $adaOtorisasi): bool
    {
        return $isLibur
            && !$adaOtorisasi
            && $this->aktorBolehAktivasi($levelAktor)
            && in_array($status, self::STATUS_BEKERJA, true);
    }

    // Pegawai HARIAN: upah hari libur sudah masuk absensi.gaji_hari_ini, jadi
    // jangan ditambah lagi (kalau ditambah, dia dibayar 2x untuk hari yang sama).
    // Pegawai BULANAN: gaji pokoknya tetap, jadi upah hari libur ditambah 1x.
    public function tambahanPendapatan(?string $tipeGaji, float $totalUpahHariLibur): float
    {
        return $tipeGaji === 'bulanan' ? $totalUpahHariLibur : 0.0;
    }

    // Gaji pokok pegawai HARIAN untuk slip bulanan.
    // absensi.gaji_hari_ini disimpan SUDAH dikurangi potongan (AbsensiController: telat masuk,
    // denda Lapor Progress, denda Kembali Kerja — semuanya juga menambah potongan_telat).
    // Di slip, potongan_telat dikurangi lagi lewat totalPotongan, jadi kalau gaji_hari_ini
    // dipakai apa adanya, potongan kepotong DUA KALI.
    // Dikembalikan ke KOTOR di sini supaya slip tetap transparan: gaji pokok tampil kotor,
    // potongan tampil sekali di barisnya sendiri, dan hasil akhirnya kepotong tepat sekali.
    public function gajiPokokKotor(float $sumGajiHariIni, float $sumPotonganTelat): float
    {
        return $sumGajiHariIni + $sumPotonganTelat;
    }

    // Satu tempat penjumlahan pendapatan sebulan — dipakai GajiService.
    // Uang makan masuk SEKALI di sini (dari sum absensi), snapshot uang makan
    // di tabel otorisasi cuma buat audit, bukan buat ditambahkan lagi.
    public function totalPendapatan(
        ?string $tipeGaji,
        float $gajiPokok,
        float $uangMakan,
        float $tunjangan,
        float $bonusKpi,
        float $bonusLembur,
        float $totalUpahHariLibur
    ): float {
        return $gajiPokok + $uangMakan + $tunjangan + $bonusKpi + $bonusLembur
             + $this->tambahanPendapatan($tipeGaji, $totalUpahHariLibur);
    }

    // Penanda 1 baris absensi. Baris lama (kolom belum ada) dianggap reguler.
    public static function rowKerjaLibur($row): bool
    {
        if (is_array($row))  return (bool) ($row['kerja_hari_libur'] ?? false);
        if (is_object($row)) return (bool) ($row->kerja_hari_libur ?? false);
        return false;
    }

    public function hanyaKerjaLibur(iterable $rows): Collection
    {
        return collect($rows)->filter(fn($r) => self::rowKerjaLibur($r))->values();
    }

    // Hari libur yang benar-benar DIKERJAKAN — ini yang dihitung & dibayar di slip/rekap.
    // Baris berpenanda tapi berstatus alpha/izin (mis. diaktifkan lalu tidak masuk,
    // di-alpha cron 13:00) TIDAK boleh muncul sebagai "kerja hari libur 1 hari".
    public function hanyaKerjaLiburBekerja(iterable $rows): Collection
    {
        return $this->hanyaKerjaLibur($rows)
                    ->filter(fn($r) => in_array(self::rowStatus($r), self::STATUS_BEKERJA, true))
                    ->values();
    }

    public static function rowStatus($row): ?string
    {
        if (is_array($row))  return $row['status'] ?? null;
        if (is_object($row)) return $row->status ?? null;
        return null;
    }

    // Statistik ringkas 1 periode — SATU tempat, dipakai halaman absensi,
    // rekap bulanan, profil, dan slip, biar angkanya tidak beda-beda per layar.
    //
    // SEMUA baris dihitung, termasuk hari yang diaktifkan. Keputusan Bos: aktivasi
    // membatalkan libur, jadi tanggal itu hari kerja biasa — masuk penyebut
    // (LiburService::hitungHariKerja) DAN pembilang di sini. Konsisten secara
    // matematis, tanpa perlu membuang record.
    //
    // Rancangan awal MENGELUARKAN baris aktivasi dari statistik supaya persentase
    // tidak tembus 100%. Akibatnya karyawan yang sudah diaktifkan lalu MANGKIR
    // hilang dari laporan: alpha-nya tidak terhitung dan KPI-nya tidak turun.
    //
    // `kerja_libur`/`upah_libur` tetap dipisah — itu untuk upah EKSTRA (kompensasi
    // jatah libur yang hangus), bukan untuk statistik kehadiran.
    public function statistikKehadiran(iterable $rows): array
    {
        $semua = collect($rows);
        $libur = $this->hanyaKerjaLiburBekerja($rows);

        return [
            'hadir'       => $semua->whereIn('status', self::STATUS_BEKERJA)->count(),
            'alpha'       => $semua->where('status', 'alpha')->count(),
            'telat'       => $semua->where('status', 'telat')->count(),
            'izin'        => $semua->whereIn('status', ['sakit','izin','cuti','dinas_luar'])->count(),
            'kerja_libur' => $libur->count(),
            'upah_libur'  => (float) $libur->sum('upah_hari_libur'),
        ];
    }

    // Satu baris tanggal di rincian slip gaji. Libur ditentukan dari JADWAL karyawan
    // (default + tukar/skip + libur nasional) yang dikirim controller, bukan tebakan
    // "Minggu = libur". Hari libur yang dimasuki menampilkan status kerjanya, bukan
    // label "Libur", dan barisnya tidak diredupkan.
    // Murni tampilan — tidak menyentuh penyebut hari kerja maupun kebijakan KPI.
    public function barisHariSlip(bool $isLiburJadwal, ?string $status, bool $kerjaHariLibur): array
    {
        if ($kerjaHariLibur) {
            return ['status' => $status ?? '-', 'redup' => false, 'kerja_libur' => true];
        }
        return [
            'status'      => $status ?? ($isLiburJadwal ? 'libur' : '-'),
            'redup'       => $isLiburJadwal,
            'kerja_libur' => false,
        ];
    }

    // Persentase kehadiran — numerator HARUS sudah tanpa hari libur yang dimasuki,
    // biar hasilnya tetap bermakna. Hasil akhir tetap dikunci maksimal 100% sebagai
    // pengaman kedua: sumber >100% bukan cuma kerja hari libur (baris absensi dobel,
    // atau jadwal libur yang diubah di tengah bulan sehingga penyebutnya mengecil
    // setelah kehadiran terlanjur tercatat). Angka "107% hadir" di slip/profil bikin
    // laporan tidak dipercaya, dan hitungKelasKpi() menilai ambang persen ini.
    public function persenHadir(int $hariHadirReguler, int $hariKerja): float
    {
        if ($hariKerja <= 0) return 0.0;
        return min(100.0, ($hariHadirReguler / $hariKerja) * 100);
    }
}
