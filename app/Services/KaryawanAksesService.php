<?php
// FILE: app/Services/KaryawanAksesService.php
// Kebijakan akses modul Karyawan — SATU tempat, murni, tanpa database/Auth,
// supaya bisa diuji langsung (tests/keamanan/test_akses_karyawan.php) dan dipakai
// sama persis oleh controller MAUPUN view.
//
// Kenapa ada: route modul karyawan dibuka `level:1,2`, jadi Admin Operasional bisa
// masuk. Sebelum ini, form Edit menampilkan pilihan level 1-7 untuk siapa pun yang
// bisa membuka halamannya — artinya Admin bisa mengangkat dirinya sendiri jadi Owner
// lewat satu dropdown, lalu membuka seluruh modul keuangan. Menyembunyikan field di
// layar TIDAK cukup: POST manual tetap tembus kalau controllernya tidak menyaring.

namespace App\Services;

class KaryawanAksesService
{
    const LEVEL_OWNER = 1;
    const LEVEL_ADMIN = 2;

    /** Level target yang boleh dikelola Admin Operasional — data operasional saja. */
    const LEVEL_TARGET_ADMIN = [3, 4, 5, 6, 7];

    /** Level target yang boleh dikelola Owner — semuanya. */
    const LEVEL_TARGET_OWNER = [1, 2, 3, 4, 5, 6, 7];

    /**
     * Level yang boleh DIBUAT Owner lewat form Tambah Karyawan — tanpa level 1.
     *
     * Mengubah level baris yang sudah ada (Edit) tetap 1-7: itu tindakan sadar atas
     * orang yang sudah dikenal, dan Owner memang berhak mengalihkan kepemilikan
     * sistem. Tapi form Tambah tidak punya alasan menawarkan level 1: sistem ini
     * milik satu orang, undangannya dikirim ke email yang baru saja diketik, dan
     * "Owner kedua" yang lahir dari salah pilih dropdown langsung memegang seluruh
     * modal/margin/keuangan tanpa jejak persetujuan siapa pun.
     */
    const LEVEL_TARGET_CREATE_OWNER = [2, 3, 4, 5, 6, 7];

    /**
     * Field yang HANYA boleh disentuh Owner (keputusan Bos, 15 Agustus 2026).
     *
     * Finansial + rekening + tunjangan: ini uang, bukan data operasional.
     * `tanggal_bergabung`: menentukan kelayakan Kasbon (lihat MasaKerjaService) —
     * kalau Admin bisa memundurkannya, dia bisa meloloskan pengajuan kasbon siapa pun.
     */
    const FIELD_OWNER_SAJA = [
        'tipe_gaji', 'gaji_harian', 'gaji_bulanan', 'uang_makan', 'uang_bonus',
        'nama_bank', 'no_rekening', 'atas_nama', 'tunjangan',
        'tanggal_bergabung',
    ];

    // Perbandingan level pakai == (loose): kolom `level` tidak punya cast di model
    // User, jadi dari DB bisa datang sebagai string "2". Mengikuti preseden perbaikan
    // bug level di sesi-sesi sebelumnya — JANGAN diganti === tanpa menambah cast.
    private static function samaLevel($a, $b): bool
    {
        if ($a === null || $a === '' || !is_numeric($a)) return false;
        return $a == $b;
    }

    public static function isOwner($levelAktor): bool
    {
        return self::samaLevel($levelAktor, self::LEVEL_OWNER);
    }

    public static function isAdmin($levelAktor): bool
    {
        return self::samaLevel($levelAktor, self::LEVEL_ADMIN);
    }

    /**
     * Daftar level target yang boleh dikelola aktor ini.
     * Level lain -> array kosong: gagal TERTUTUP, bukan terbuka.
     */
    public static function levelTargetDiizinkan($levelAktor): array
    {
        if (self::isOwner($levelAktor)) return self::LEVEL_TARGET_OWNER;
        if (self::isAdmin($levelAktor)) return self::LEVEL_TARGET_ADMIN;
        return [];
    }

    /**
     * Boleh membuka/mengubah data karyawan dengan level target ini?
     *
     * Admin ditolak untuk target level 1-2 — TERMASUK dirinya sendiri (dia level 2).
     * Itu disengaja: kalau Admin boleh mengedit barisnya sendiri, dia bisa menaikkan
     * levelnya sendiri, dan seluruh pagar ini jadi percuma.
     */
    public static function bolehKelola($levelAktor, $levelTarget): bool
    {
        foreach (self::levelTargetDiizinkan($levelAktor) as $lv) {
            if (self::samaLevel($levelTarget, $lv)) return true;
        }
        return false;
    }

    /** Boleh MENETAPKAN level baru ke nilai ini? (batas yang sama dengan bolehKelola) */
    public static function bolehSetLevel($levelAktor, $levelBaru): bool
    {
        return self::bolehKelola($levelAktor, $levelBaru);
    }

    /**
     * Daftar level yang boleh dipilih di form TAMBAH karyawan.
     * Owner: 2-7 (tanpa Owner kedua). Aktor lain: sama dengan batas kelolanya.
     */
    public static function levelTargetCreate($levelAktor): array
    {
        if (self::isOwner($levelAktor)) return self::LEVEL_TARGET_CREATE_OWNER;
        return self::levelTargetDiizinkan($levelAktor);
    }

    /** Boleh menetapkan level ini pada karyawan BARU? */
    public static function bolehSetLevelCreate($levelAktor, $levelBaru): bool
    {
        foreach (self::levelTargetCreate($levelAktor) as $lv) {
            if (self::samaLevel($levelBaru, $lv)) return true;
        }
        return false;
    }

    /**
     * Aturan validasi field `level` untuk pembuatan karyawan baru.
     * Selalu `in:` (bukan `between:`) — termasuk untuk Owner, supaya level 1
     * benar-benar tidak bisa disisipkan lewat POST manual walau dropdown-nya bersih.
     */
    public static function aturanLevelCreate($levelAktor): string
    {
        // Tanpa level yang diizinkan, `in:` kosong = selalu gagal (gagal tertutup).
        return 'required|integer|in:' . implode(',', self::levelTargetCreate($levelAktor));
    }

    /** Aturan validasi Laravel untuk field `level`, sesuai aktor. */
    public static function aturanLevel($levelAktor): string
    {
        if (self::isOwner($levelAktor)) {
            return 'required|integer|between:1,7';
        }
        $izin = self::levelTargetDiizinkan($levelAktor);
        // Tanpa level yang diizinkan, `in:` kosong = selalu gagal (gagal tertutup).
        return 'required|integer|in:' . implode(',', $izin);
    }

    public static function bolehFinansial($levelAktor): bool
    {
        return self::isOwner($levelAktor);
    }

    /**
     * Aturan validasi untuk field finansial — IKUT hak aktor.
     *
     * Untuk Owner: seperti semula (tipe_gaji wajib, nominal opsional & numerik).
     * Untuk selain Owner: KOSONG.
     *
     * Kenapa harus kosong, bukan sekadar dilonggarkan: field-field ini tidak lagi
     * dirender untuk Admin, jadi form-nya memang tidak mengirimnya. Kalau aturan
     * `required` tetap dipasang, Admin tidak akan pernah bisa menyimpan form sama
     * sekali — dan pesan errornya menunjuk field yang tidak pernah dia lihat.
     * Itu justru memutus kemampuan yang seharusnya dibuka (Admin mengelola level 3-7).
     *
     * Aman karena nilainya toh sudah dibuang saringPayload()/payloadCreate() sebelum
     * menyentuh database — tidak ada yang perlu divalidasi.
     */
    public static function aturanFinansial($levelAktor): array
    {
        if (!self::bolehFinansial($levelAktor)) return [];

        return [
            'tipe_gaji'    => 'required|in:harian,bulanan,project',
            'gaji_harian'  => 'nullable|numeric|min:0',
            'gaji_bulanan' => 'nullable|numeric|min:0',
            'uang_makan'   => 'nullable|numeric|min:0',
            'uang_bonus'   => 'nullable|numeric|min:0',
        ];
    }

    public static function bolehTunjangan($levelAktor): bool
    {
        return self::isOwner($levelAktor);
    }

    public static function bolehUbahTanggalBergabung($levelAktor): bool
    {
        return self::isOwner($levelAktor);
    }

    /**
     * Buang field Owner-saja dari payload kalau aktornya bukan Owner.
     *
     * Ini pagar yang sebenarnya — bukan `@if` di Blade. Admin yang menyusun POST
     * sendiri (curl/DevTools) tetap tidak bisa menitipkan nominal gaji atau rekening.
     */
    public static function saringPayload($levelAktor, array $payload): array
    {
        if (self::bolehFinansial($levelAktor)) return $payload;

        foreach (self::FIELD_OWNER_SAJA as $field) {
            unset($payload[$field]);
        }
        return $payload;
    }

    /**
     * Nilai finansial default yang AMAN untuk karyawan baru buatan Admin:
     * tipe harian, semua nominal 0, tanpa tunjangan/rekening.
     *
     * Konsekuensinya disengaja: karyawan baru belum tergaji sampai Owner
     * melengkapi angkanya. Lebih baik Rp 0 yang kelihatan dan harus dilengkapi,
     * daripada nominal karangan Admin yang diam-diam terbayar.
     */
    public static function defaultFinansialAman(): array
    {
        return [
            'tipe_gaji'    => 'harian',
            'gaji_harian'  => 0,
            'gaji_bulanan' => 0,
            'uang_makan'   => 0,
            'uang_bonus'   => 0,
        ];
    }

    /**
     * Payload pembuatan karyawan baru, sudah disesuaikan hak aktor.
     * Owner: apa adanya. Admin: field Owner-saja dibuang, lalu ditimpa default aman.
     */
    public static function payloadCreate($levelAktor, array $payload): array
    {
        if (self::bolehFinansial($levelAktor)) return $payload;

        return array_merge(self::saringPayload($levelAktor, $payload), self::defaultFinansialAman());
    }

    /**
     * Batas level untuk daftar karyawan di halaman index.
     * null = tanpa batas (Owner). Array = whereIn level.
     * Admin tidak boleh melihat baris Owner/Admin sama sekali — kalau barisnya
     * tampil, tombol Edit/Reset Password-nya ikut tampil dan mengundang percobaan.
     */
    public static function levelUntukIndex($levelAktor): ?array
    {
        if (self::isOwner($levelAktor)) return null;
        return self::levelTargetDiizinkan($levelAktor);
    }
}
