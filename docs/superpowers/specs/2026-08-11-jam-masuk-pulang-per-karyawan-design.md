# Desain: Jam Masuk/Pulang Per-Karyawan (jadi patokan telat/lembur beneran)

**Tanggal:** 11 Agustus 2026
**Status:** Disetujui (brainstorming) — siap ke tahap rencana implementasi
**Pemilik:** Elvan (owner, non-teknis)
**Konteks:** Ketemu pas ngobrolin "apakah kondisi telat berlaku sama buat semua karyawan?" Investigasi nemuin `users.jam_masuk`/`jam_pulang` sudah ada di form Karyawan dan tersimpan di DB, tapi FUNGSINYA DEKORATIF — perhitungan telat/lembur yang beneran jalan pakai konstanta hardcode `AbsensiController::JAM_MASUK`('07:00')/`JAM_LEMBUR`('17:00') yang sama buat semua orang.

---

## 1. Ringkasan

Ganti patokan telat dari konstanta hardcode `JAM_MASUK` ke kolom `users.jam_masuk` per-karyawan, dan patokan mulai lembur dari `JAM_LEMBUR` ke `users.jam_pulang` per-karyawan. Field yang sudah ada di form Karyawan (sekarang percuma) jadi beneran punya efek ke gaji.

---

## 2. Kenapa dibutuhkan

Dikonfirmasi Elvan (11 Agustus): sebagian karyawan punya/akan punya jam mulai kerja yang beda dari yang lain — ini kebutuhan nyata, bukan kosmetik.

Dibuktikan lewat baca kode (`app/Http/Controllers/AbsensiController.php`, verifikasi ulang di sesi ini — masih akurat):
- Baris 27, 32: `const JAM_MASUK = '07:00'`, `const JAM_LEMBUR = '17:00'` — dipakai SEMUA karyawan, terlepas dari isi `users.jam_masuk`/`jam_pulang`.
- Baris 139: `hitungMenitTelat($jamSekarang, self::JAM_MASUK)` — telat dihitung dari konstanta, bukan `$user->jam_masuk`.
- Baris 350-351: lembur mulai dihitung dari `self::JAM_LEMBUR`, bukan `$user->jam_pulang`.
- `users.jam_pulang` memang DIBACA, tapi cuma buat label kosmetik "pulang lebih awal" di JS (`resources/views/absensi/form-pulang.blade.php:130`), nol konsekuensi ke gaji — dan fallback-nya sendiri salah (`'16:30'` padahal `JAM_LEMBUR` yang mau digantikan adalah `'17:00'`).

---

## 3. Keputusan yang dikunci (brainstorm 11 Agustus, dikonfirmasi Elvan satu-satu)

| # | Keputusan | Detail |
|---|---|---|
| 1 | Telat mengikuti jam individu | `hitungMenitTelat` pakai `$user->jam_masuk` (bukan `self::JAM_MASUK` lagi). |
| 2 | "Setengah Hari" TETAP seragam | `JAM_SETENGAH` (10:00 absolut) TIDAK ikut geser sama `jam_masuk` individu. |
| 3 | Lembur mengikuti jam individu | Mulai dihitung lembur pakai `$user->jam_pulang` (bukan `self::JAM_LEMBUR` lagi). |
| 4 | `JAM_BUKA_ABSEN` (06:30) TETAP seragam, KECUALI luar kota | Karyawan yang `LuarKota::sedangLuarKota()` aktif dikecualikan total dari gate ini (bisa absen masuk jam berapa pun, mis. jam 3-4 pagi buat berangkat jauh). Kompensasi/gaji buat jam dini hari itu SENGAJA di luar cakupan — fitur terpisah nanti, jangan dicampur di sini. |
| 5 | Absen siang (13:00-14:00) TETAP seragam | Ini check-in progress tim yang disinkronkan, bukan jam istirahat pribadi — tidak ikut geser jadwal individu. Dikonfirmasi: memang tidak ada variasi shift di antara karyawan saat ini buat titik ini. |
| 6 | Backfill wajib sebelum deploy | `users.jam_masuk` DB default = `07:30:00` (BUKAN `07:00:00`, konstanta yang mau digantikan) — karena field ini selama ini gak berefek, isinya sekarang gak bisa dipercaya. `jam_pulang` default `17:00:00` sudah cocok `JAM_LEMBUR`, risiko rendah. **Sebelum wiring aktif: jalankan backfill SQL reset semua karyawan aktif ke `jam_masuk='07:00:00'`, `jam_pulang='17:00:00'`** (persis perilaku live sekarang) supaya hari deploy nol-regresi. Owner kustomisasi per-karyawan sesudahnya lewat form Edit (yang sekarang beneran berefek). |

---

## 4. Titik integrasi (kode)

| # | File | Perubahan |
|---|---|---|
| 1 | `AbsensiController::absenMasuk()` (baris ~139) | `hitungMenitTelat($jamSekarang, self::JAM_MASUK)` → `hitungMenitTelat($jamSekarang, $user->jam_masuk)`. |
| 2 | `AbsensiController::absenPulang()` (baris ~350-351) | `self::JAM_LEMBUR` → `$user->jam_pulang` (dua pemakaian: kondisi `>=` dan hitung menit lembur). |
| 3 | `AbsensiController::formMasuk()` (baris ~97) | Gate `JAM_BUKA_ABSEN` dikecualikan kalau `LuarKota::sedangLuarKota($user->id)` true. |
| 4 | `AbsensiController::formPulang()` (baris ~318) | `$jamLemburMax = self::JAM_LEMBUR` → `$user->jam_pulang` (dikirim ke view, dipakai label). |
| 5 | `KaryawanController` (baris ~50-51, 131-132) | Tambah `date_format:H:i` ke rule validasi `jam_masuk`/`jam_pulang` (sekarang cuma `required`, gak ada cek format). |
| 6 | `resources/views/absensi/form-pulang.blade.php:130` | Fallback JS `'16:30'` yang salah → `'17:00'` (konsisten sama `JAM_LEMBUR` lama). |
| 7 | `AbsensiController` (baris 27, 32) | Hapus konstanta mati `JAM_MASUK`, `JAM_LEMBUR`, dan `JAM_PULANG='16:30'` (baris 31 — sudah tidak dipakai di manapun, dicek grep). |

**Yang TIDAK berubah:** `JAM_SETENGAH`, `JAM_BUKA_ABSEN` (kecuali exemption luar kota), `JAM_MASUK_SIANG`, gate GPS/`RADIUS_MASUK_PULANG`, alur absen siang.

---

## 5. Backfill SQL (jalan SEBELUM kode di-deploy, mirror pelajaran jadwal-libur)

```sql
UPDATE users SET jam_masuk = '07:00:00', jam_pulang = '17:00:00'
WHERE status = 'aktif';
```

Idempotent, aman dijalankan ulang. Karyawan non-aktif tidak disentuh (tidak mempengaruhi absensi berjalan).

---

## 6. Testing

**Logic murni (standalone tanpa DB, pola sama seperti `tests/jadwal-libur/*.php`):**
File baru `tests/absensi/test_jam_individu.php` — reproduksi `hitungMenitTelat`/lembur dengan jam custom per-user vs default, pastikan hasil identik ke perilaku lama kalau `jam_masuk='07:00'`/`jam_pulang='17:00'` (nol-regresi), dan beda kalau di-custom.

**Butuh verifikasi manual di production:**
- Karyawan dengan `jam_masuk` di-custom (mis. 08:00) — absen jam 07:30 harusnya TIDAK telat (dulu telat).
- Karyawan luar kota aktif — absen masuk jam 04:00 harusnya lolos gate `JAM_BUKA_ABSEN`.
- Lembur: karyawan dengan `jam_pulang` custom (mis. 16:00) yang lembur_approved, absen pulang jam 16:30 → mulai dihitung lembur dari 16:00, bukan 17:00.
- Slip gaji tetap konsisten dengan sebelumnya buat karyawan yang jamnya TIDAK di-custom (backfill bekerja).

---

## 7. Di luar cakupan / dicatat, bukan diperbaiki di sini

- **Kompensasi jam dini hari buat luar kota** — exemption gate cuma soal BISA absen, bukan soal DIBAYAR lebih. Fitur terpisah, belum dirancang.
- **Kemungkinan bug dobel-potong**: karyawan yang telat submit absen siang SETELAH jam 14:00 mungkin kena DUA penalti — flat Rp20.000 "skip siang" (otomatis lewat `index()` pas lewat 14:00) DAN potongan telat prorata dari submit aslinya. Belum dikonfirmasi sebagai bug nyata, baru temuan baca-kode. Perlu verifikasi terpisah, tidak diperbaiki lewat fitur ini.
- **Redesain "absen siang"**: Elvan menandai deadline 13:00 gak ngasih ruang buat menindaklanjuti apa yang dilaporkan di situ (yang dilaporkan harusnya udah kelar SEBELUM istirahat, bukan pas istirahat). Ini redesain tujuan fitur, bukan tweak — butuh sesi brainstorm sendiri.

---

## 8. Yang TIDAK berubah

Alur GPS/lokasi, absen siang, kode absen, jadwal libur (`LiburService`), perhitungan gaji selain titik lembur/telat di atas, halaman rekap absensi.
