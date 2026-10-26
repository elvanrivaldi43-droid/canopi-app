# Izin Pulang Cepat — Design

Tanggal: 26 Okt 2026 · Status: menunggu review spec tertulis oleh Elvan

## Latar

Sekarang tombol absen pulang bisa ditekan kapan saja. Status jadi `setengah_hari`
HANYA bila total kerja < 225 menit (3j45m). Akibatnya masuk 07:00 pulang 11:00
(4 jam) tetap `hadir` = gaji penuh. Tidak ada izin ke atasan.

## Keputusan Elvan (jangan didebat ulang)

| Hal | Keputusan |
|---|---|
| Pulang sebelum jam pulang jadwal | Wajib izin atasan |
| Toleransi menit | **Tidak ada** — sebelum `users.jam_pulang` persis = pulang cepat |
| Tanpa izin | Default setengah hari; Owner bisa Koreksi bila alasan & bukti kuat |
| Diizinkan, pulang < 14:00 | Tetap setengah hari |
| Diizinkan, pulang >= 14:00 | Gaji penuh |
| Penyetuju Teknisi (5) & Driver (6) | Mandor (3) atau Owner (1) |
| Penyetuju Admin Ops (2), Mandor (3), Marketing (4), Toko Besi (7) | Owner saja |

Konsekuensi yang sudah dijelaskan ke Elvan: batas "jam 12" tak lagi berpengaruh ke
uang — sebelum 14:00 hasilnya selalu setengah hari. Izin sebelum 14:00 tetap
berguna sebagai catatan (atasan tahu siapa pulang & alasannya).

## Aturan gaji saat absen pulang (satu fungsi murni)

`AbsensiController::statusSaatPulang(string $statusSekarang, int $menitKerja, string $jamPulang, string $jamPulangJadwal, bool $diizinkan): string`

1. Status sudah `setengah_hari` (masuk >= 10:00) → `setengah_hari`.
2. `$menitKerja < 225` → `setengah_hari` (aturan lama, tetap).
3. `$jamPulang >= $jamPulangJadwal` → status tak berubah (pulang normal).
4. `$diizinkan && $jamPulang >= '14:00'` → status tak berubah (gaji penuh).
5. Selain itu → `setengah_hari`.

Nominal setengah hari memakai rumus yang SUDAH ada di `absenPulang` (gaji 0,5x
dikurangi potongan, UM 0,5x, upah hari libur ikut). Tidak ada rumus baru.

## Penyimpanan — 3 kolom di baris absensi hari itu (tanpa tabel baru)

Ajuan selalu untuk hari ini dan baris absensi sudah ada sejak absen masuk.

```sql
ALTER TABLE absensi
  ADD COLUMN IF NOT EXISTS pulang_cepat_status VARCHAR(10) NULL,  -- menunggu / disetujui / ditolak
  ADD COLUMN IF NOT EXISTS pulang_cepat_alasan VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS pulang_cepat_oleh   BIGINT UNSIGNED NULL; -- penyetuju/penolak
```

VARCHAR (bukan ENUM) supaya status baru tak butuh ALTER lagi. Dijalankan Elvan
di phpMyAdmin SEBELUM push.

## Alur

### 1. Mengajukan (semua level 2–7)
- Halaman Absensi: kotak **"Ajukan Pulang Cepat"** (kolom alasan wajib) tampil bila
  sudah absen masuk, belum pulang, jam sekarang < jam pulang jadwal, dan status ajuan
  kosong/`ditolak`. Status `menunggu` → teks "menunggu persetujuan"; `disetujui` →
  "Diizinkan pulang cepat"; `ditolak` → info ditolak + boleh ajukan lagi.
- `POST /absensi/ajukan-pulang-cepat` (grup absensi, cek di server).
- Notif Telegram ke penyetuju sah (lihat tabel), kecuali diri sendiri.

### 2. Menyetujui / menolak
- Halaman **Persetujuan Izin** (`izin.approval`, sudah `level:1,3`): bagian baru
  "Pulang Cepat Menunggu" (hanya hari ini). Tombol Setujui/Tolak tampil hanya bila
  aturan server membolehkan (fungsi yang sama).
- `POST /izin/pulang-cepat/{absensiId}/setujui|tolak` di dalam grup `level:1,3`.
- Aturan murni `Absensi::alasanTolakProsesPulangCepat(status, tanggal, hariIni, sudahPulang, pemilikId, aktorId, pemilikLevel, aktorLevel): ?string`:
  status ≠ `menunggu` → tolak; bukan hari ini → kedaluwarsa; **karyawan sudah absen
  pulang → tolak ("sudah pulang, pakai Koreksi")**; diri sendiri → tolak; Owner → boleh;
  Mandor → boleh hanya utk level 5/6; lainnya → tolak.
- Hasil: status `disetujui`/`ditolak`, `pulang_cepat_oleh` = penyetuju, notif ke karyawan.

### 3. Absen pulang
- `formPulang`: bila jam < jadwal dan hasil `statusSaatPulang` = setengah hari,
  tampil peringatan "Pulang sekarang dihitung SETENGAH HARI" (+ alasannya: belum
  diizinkan / sebelum 14:00). Tombol TIDAK dikunci.
- `absenPulang`: ganti baris `$setengahHari = ... < 225` dengan `statusSaatPulang(...)`.

### 4. Denda checkpoint setelah pulang
Sekarang `index()` mendenda lupa Lapor Progress (lewat 12:30) & tak Kembali Kerja
(lewat 14:00) walau karyawan SUDAH pulang sebelum batas itu → orang sakit pulang 11:00
kena setengah hari + 2 denda. Perubahan: denda checkpoint tidak dicatat bila
`jam_pulang` terisi dan lebih awal dari batas checkpoint tsb.

## Tes (tanpa DB)
- `tests/absensi/test_pulang_cepat.php`: tabel `statusSaatPulang` (pulang normal,
  1 menit sebelum jadwal tanpa izin, izin 13:59 vs 14:00, <225 menit walau diizinkan,
  status awal setengah_hari) + `alasanTolakProsesPulangCepat` (mandor utk teknisi lolos,
  mandor utk admin/marketing/toko/mandor lain ditolak, owner lolos, diri sendiri,
  sudah pulang, kedaluwarsa, bukan menunggu) + aturan denda checkpoint setelah pulang.
- Tes route: ajukan tanpa level middleware; setujui/tolak di dalam `level:1,3`.
- `php scripts/canopi-check --full` hijau sebelum push.

## Di luar cakupan (sengaja, ponytail)
Riwayat ajuan khusus (cukup terlihat di baris absensi) · alasan wajib saat menolak ·
batas jam 12 terpisah (tak berpengaruh) · pengubahan cron lupa absen pulang (tetap
setengah hari = sama dengan "tanpa izin").

## Risiko
- Karyawan yang sering pulang 15-30 menit lebih awal tanpa sadar akan langsung kena
  setengah hari (tanpa toleransi, keputusan Elvan) — umumkan sebelum dipakai.
- Jadwal pulang per karyawan (`users.jam_pulang`) harus benar; kalau kosong/salah,
  pulang dinilai salah. Kolom ber-default `17:00:00` (migrasi users), jadi kosong tak terjadi
  untuk akun normal; kode tetap pakai `?? '17:00'` sebagai jaring.
