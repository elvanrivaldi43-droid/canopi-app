# Rincian Potongan Absensi — Design

Tanggal: 30 September 2026 · Disetujui Elvan (brainstorming, opsi C + penyimpanan opsi 1)

## Masalah

Kolom `absensi.potongan_telat` menampung 4 jenis potongan sekaligus: telat pagi,
telat absen siang, denda lupa Lapor Progress (20rb), denda tidak absen Kembali
Kerja (20rb), plus timpaan manual dari menu Koreksi. Angka seperti 41.333 (Rizco,
24/9) tidak bisa diurai dengan pasti — bahkan oleh Owner. Karyawan menduga-duga,
Owner menduga-duga.

## Tujuan & kriteria sukses

- Tiap angka potongan harian bisa dibaca asal-usulnya tanpa menebak.
- Terlihat di: riwayat absen karyawan (per hari), rekap harian Owner + modal
  Koreksi (per hari), slip gaji (ringkasan per jenis).
- **Nol perubahan angka uang.** `potongan_telat` tetap satu-satunya patokan;
  rumus `GajiService`/`nominalKoreksi`/`gajiLupaPulang` tidak disentuh.

## Data

- `absensi.rincian_potongan` TEXT NULL (cast `array`). Isi: daftar entri
  `{"j": jenis, "n": nominal, "m": menit?, "a": alasan?}`.
- `slip_gaji.rincian_potongan` TEXT NULL (cast `array`). Isi: ringkasan bulan
  `{jenis: {"x": jumlah_kejadian, "n": total}}` + kunci `belum_terinci` bila ada
  selisih. Dibekukan saat slip dibuat/dihitung ulang (sama seperti angka slip).
- Jenis: `telat_pagi`, `telat_siang`, `lupa_progress`, `skip_siang`, `koreksi`.
- TEXT, bukan JSON: aman di MariaDB/MySQL shared hosting mana pun.

## Unit baru

`app/Services/RincianPotongan.php` — fungsi statis MURNI (tanpa DB):

- `tambah(?array $rincian, string $jenis, float $nominal, ?int $menit = null, ?string $alasan = null): array`
  — kembalikan rincian + 1 entri. Nominal 0 → tidak menambah entri.
- `baris(?array $rincian, float $total): array` — daftar baris tampilan
  `[label, nominal, keterangan]`; kalau `total − Σrincian` ≥ Rp1 (absolut),
  tambah baris **"Belum terinci (data lama)"** senilai selisih. Satu aturan ini
  menangani data sebelum fitur, perbaikan SQL manual, dan sisa pembulatan.
- `ringkasBulan(iterable $absensi): array` — agregat per jenis untuk slip,
  termasuk `belum_terinci` (Σ selisih per baris).
- `LABEL` — peta jenis → label Indonesia.

## Titik tulis (semua di `AbsensiController`, kecuali disebut lain)

| Titik | Semantik total | Rincian |
|---|---|---|
| Absen masuk (`updateOrCreate`, ~l.271) | TIMPA | TIMPA: `tambah(null,'telat_pagi',…)` |
| Denda Lapor Progress (`index`, ~l.103) | tambah | `tambah(lama,'lupa_progress',20000)` |
| Denda skip siang (`index`, ~l.119) | tambah | `tambah(lama,'skip_siang',20000)` |
| Telat absen siang (`kembaliKerja`, ~l.676) | tambah | `tambah(lama,'telat_siang',…,menit)` |
| Koreksi Owner (`koreksi`, ~l.924) | TIMPA | `tambah(lama,'koreksi', baru−lama, null, alasan)` bila selisih ≠ 0 |

Tak disentuh (total tak berubah / 0): cron alpha, `koreksiManual` (baris baru,
total 0), approval izin, cron lupa pulang (hanya baca).

## Tampilan

- `absensi/index.blade.php` riwayat per hari: di bawah angka potongan, daftar
  `baris()` kecil.
- `absensi/rekap.blade.php` rekap harian + modal Koreksi: daftar `baris()`.
- `penggajian/slip.blade.php`: di bawah baris "Potongan Telat", ringkasan per
  jenis dari `slip_gaji.rincian_potongan` (slip lama tanpa data → tak tampil apa-apa).
- `rekap-bulanan.blade.php` TIDAK diubah (grid, tak ada ruang di HP).
- Tanpa emoji (aturan deploy blade).

## Pemasangan (urutan WAJIB)

1. `docs/sql/2026-09-30-rincian-potongan.sql`: `ALTER TABLE … ADD COLUMN IF NOT EXISTS`
   untuk kedua tabel + `SHOW COLUMNS` verifikasi. Plus migration Laravel padanan.
2. Elvan jalankan SQL di phpMyAdmin, konfirmasi kolom ada.
3. Baru push kode. Kode naik duluan = absen masuk semua karyawan gagal (kolom tak ada).

## Pengujian

`tests/penggajian/test_rincian_potongan.php` (daftar di `tests/guardrail/manifest.json`
di commit yang sama): tambah entri, nominal 0 diabaikan, Σ rincian = total pada
alur masuk→progress→siang, koreksi naik & turun, baris "belum terinci" untuk
rincian null, `ringkasBulan` per jenis + belum_terinci.

## Di luar lingkup (sengaja)

- Rincian ulang data lama (tebakan; baris "belum terinci" cukup).
- Rekap bulanan.
- Temuan terpisah: denda Lapor Progress/skip siang hanya tercatat saat karyawan
  membuka halaman Absensi setelah jam batas (tak ada cron) → yang tak membuka
  aplikasi lolos denda. Keputusan kebijakan terpisah untuk Elvan.
