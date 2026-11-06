<?php
// FILE: app/Services/PenugasanAturan.php
//
// Aturan murni Penugasan Harian (rencana kerja malam oleh Mandor, pekerja konfirmasi
// Hadir/Tidak Hadir via Telegram sampai 06:00 WIB). Tanpa DB/facade supaya gampang dites.
// Jam = string 'HH:MM' WIB (berpadding nol, jadi aman dibandingkan sebagai string).

namespace App\Services;

class PenugasanAturan
{
    // Teknisi(5), Driver(6), Mandor(3) ikut penugasan.
    const LEVEL_IKUT = [3, 5, 6];

    // Urutan kode pengingat; kode tak pernah dikirim bila urutannya <= yang terakhir terkirim.
    const URUT_MANDOR  = ['1900', '2030', '2100', 'owner'];
    const URUT_PEKERJA = ['2130', '0500', '0545'];

    public static function ikut(int $level): bool
    {
        return in_array($level, self::LEVEL_IKUT, true);
    }

    // True bila himpunan tugas (tahap+porsi) berbeda; urutan tak berpengaruh.
    public static function perluTanyaUlang(array $tugasLama, array $tugasBaru): bool
    {
        $kunci = function (array $list) {
            $k = array_map(fn($t) => $t['project_tahap_id'] . ':' . $t['porsi'], $list);
            sort($k);
            return $k;
        };
        return $kunci($tugasLama) !== $kunci($tugasBaru);
    }

    // Keputusan konfirmasi_hadir milik Mandor Bertugas saat rencana disimpan.
    // 'tidak_hadir' hanya boleh diubah oleh Mandor sendiri; editor lain (Owner) membiarkannya.
    // Hasil: 'hadir' | 'tidak_hadir' | 'biarkan' | 'hapus'
    public static function statusMandor(bool $editorAdalahMandor, bool $centangTidakMasuk, ?string $jawabanLama, bool $adaTugas): string
    {
        if ($editorAdalahMandor && $centangTidakMasuk) return 'tidak_hadir';
        if (!$editorAdalahMandor && $jawabanLama === 'tidak_hadir') return 'biarkan';
        return $adaTugas ? 'hadir' : 'hapus';
    }

    public static function orangHari(string $porsi): float
    {
        return match ($porsi) {
            'penuh'    => 1.0,
            'setengah' => 0.5,
            default    => throw new \InvalidArgumentException("Porsi tak dikenal: $porsi"),
        };
    }

    // Satu orang maksimal 1 hari kerja per tanggal ('penuh' tak boleh bareng tugas lain).
    public static function alasanTolakTugasOrang(array $porsiList): ?string
    {
        $total = array_sum(array_map([self::class, 'orangHari'], $porsiList));
        return $total > 1.0 ? 'Lebih dari 1 hari kerja' : null;
    }

    // Pengingat Mandor yang jatuh tempo & belum terkirim; lompat ke yang terbaru, tak mengulang.
    public static function pengingatMandor(string $jam, bool $sudahDisimpan, ?string $terakhir): ?string
    {
        if ($sudahDisimpan) return null;

        $due = null;
        foreach (['1900' => '19:00', '2030' => '20:30', '2100' => '21:00', 'owner' => '22:00'] as $kode => $batas) {
            if ($jam >= $batas) $due = $kode;
        }

        return self::lebihMaju(self::URUT_MANDOR, $due, $terakhir);
    }

    // Pengingat pekerja yang jatuh tempo; tak ada pesan 22:00-04:59 maupun >= 06:00.
    public static function pengingatPekerja(string $jam, ?string $jawaban, ?string $terakhir): ?string
    {
        if ($jawaban !== null) return null;

        $due = match (true) {
            $jam >= '21:30' && $jam < '22:00' => '2130',
            $jam >= '05:00' && $jam < '05:45' => '0500',
            $jam >= '05:45' && $jam < '06:00' => '0545',
            default                          => null,
        };

        return self::lebihMaju(self::URUT_PEKERJA, $due, $terakhir);
    }

    // Pertanyaan konfirmasi hadir (dipakai simpan() dan cron pengingat).
    // $ulang = rencana berubah setelah pekerja sempat menjawab (jawabannya direset).
    public static function pesanTanya(string $kataHari, string $tglLabel, array $labelTugas, bool $ulang = false): string
    {
        return ($ulang ? "Rencana berubah — jawaban sebelumnya dibatalkan, mohon jawab lagi.\n" : '')
            . "$kataHari $tglLabel kamu di " . implode(' + ', $labelTugas) . '. Bersedia hadir?';
    }

    // Tanggal kerja yang sedang direncanakan/dijawab ('Y-m-d'). Sebelum 06:00 = hari ini
    // (jawaban hari ini masih terbuka sampai 06:00, lihat bolehJawab); sejak 06:00 = besok.
    public static function tanggalKerja(\DateTimeInterface $sekarang): string
    {
        $d = \DateTimeImmutable::createFromInterface($sekarang);
        return ($d->format('H:i') < '06:00' ? $d : $d->modify('+1 day'))->format('Y-m-d');
    }

    public static function kataHari(string $tanggalKerja, \DateTimeInterface $sekarang): string
    {
        return $tanggalKerja === $sekarang->format('Y-m-d') ? 'Hari ini' : 'Besok';
    }

    // Kabar ke Mandor+Owner saat pekerja berbalik Tidak Hadir -> Hadir; null utk transisi lain.
    public static function pesanBerubahHadir(?string $lama, string $baru, string $nama, string $kataHari, string $tglLabel): ?string
    {
        if ($lama !== 'tidak_hadir' || $baru !== 'hadir') return null;
        return "$nama berubah: HADIR " . strtolower($kataHari) . " ($tglLabel) — batalkan pengganti bila sudah dicari";
    }

    // Owner dikabari 'Mandor tidak masuk' hanya sekali (bukan tiap simpan ulang).
    public static function perluKabariMandorTidakMasuk(bool $mandorTidakMasuk, ?string $jawabanLama): bool
    {
        return $mandorTidakMasuk && $jawabanLama !== 'tidak_hadir';
    }

    public static function tombolHadir(int $konfirmasiId): array
    {
        return [[
            ['text' => 'Hadir', 'callback_data' => "hadir:$konfirmasiId"],
            ['text' => 'Tidak Hadir', 'callback_data' => "tidak:$konfirmasiId"],
        ]];
    }

    // callback_data tombol: "hadir:{id}" / "tidak:{id}".
    public static function parseTombol(string $data): ?array
    {
        if (!preg_match('/^(hadir|tidak):(\d+)$/', $data, $m)) return null;
        return ['jawaban' => $m[1] === 'hadir' ? 'hadir' : 'tidak_hadir', 'id' => (int) $m[2]];
    }

    // Pesan penolakan, atau null bila jawaban boleh dicatat.
    public static function alasanTolakJawaban(?string $chatPenekan, ?string $chatPemilik, bool $boleh): ?string
    {
        if ($chatPenekan === null || $chatPenekan === '' || $chatPemilik === null || $chatPemilik === '' || $chatPenekan !== $chatPemilik) {
            return 'Tombol ini bukan untuk akun Telegram kamu.';
        }
        return $boleh ? null : 'Batas jawab jam 06:00 sudah lewat. Hubungi mandor.';
    }

    // Jawaban terbuka sampai tanggal kerja 06:00:00 (tepat 06:00 sudah tutup).
    public static function bolehJawab(string $tanggalKerja, \DateTimeInterface $sekarang): bool
    {
        return $sekarang < new \DateTimeImmutable("$tanggalKerja 06:00:00", $sekarang->getTimezone());
    }

    // 'kirim' | 'catat_tidak_hadir' | 'tanpa_jawaban'
    public static function keputusanKode(bool $adaRencana, bool $adaDiRencana, ?string $jawaban): string
    {
        if (!$adaRencana || !$adaDiRencana) return 'kirim';

        return match ($jawaban) {
            'hadir'       => 'kirim',
            'tidak_hadir' => 'catat_tidak_hadir',
            default       => 'tanpa_jawaban',
        };
    }

    // Orang dianggap "ada di rencana" bila punya tugas atau baris konfirmasi.
    // Mandor Bertugas SENGAJA tidak otomatis masuk: rencana tanpa tugas utk dirinya
    // menghapus baris konfirmasinya, dan diam-tanpa-baris tak boleh menghanguskan kodenya.
    public static function adaDiRencana(bool $adaPenugasan, bool $adaKonfirmasi): bool
    {
        return $adaPenugasan || $adaKonfirmasi;
    }

    // Ringkasan pagi ke Mandor Bertugas + Owner; null bila tak ada yang terlewat.
    public static function pesanTanpaJawaban(array $nama): ?string
    {
        if (!$nama) return null;
        return 'Tidak dapat kode absen hari ini karena tak menjawab konfirmasi: ' . implode(', ', $nama) . ".\n"
             . 'Mereka bisa dibuatkan kode manual oleh Owner (menu Kode Absen Hari Ini).';
    }

    // Status baru tahap akibat laporan sore, atau null bila tak berubah.
    public static function statusTahapDariLaporan(string $statusTahap, string $laporan): ?string
    {
        if ($laporan === 'selesai' && $statusTahap !== 'selesai') return 'selesai';
        if ($laporan === 'lanjut' && $statusTahap === 'belum') return 'sedang';
        return null;
    }

    private static function lebihMaju(array $urut, ?string $due, ?string $terakhir): ?string
    {
        if ($due === null) return null;
        $posTerakhir = $terakhir === null ? -1 : array_search($terakhir, $urut, true);
        return array_search($due, $urut, true) > $posTerakhir ? $due : null;
    }
}
