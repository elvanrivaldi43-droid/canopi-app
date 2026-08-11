# Desain: Gabungkan Ajuan Jadwal Libur Jadi 1 Form (Tukar/Skip/Tambah)

**Tanggal:** 11 Agustus 2026
**Status:** Disetujui (brainstorming) — siap ke tahap rencana implementasi
**Pemilik:** Elvan (owner, non-teknis)
**Konteks:** Fitur Jadwal Libur Per-Karyawan (`docs/superpowers/specs/2026-08-11-jadwal-libur-karyawan-design.md`) sudah live sejak siang 11 Agustus, dengan 2 jenis ajuan terpisah (Tambah/Batal). Buat kasus "tukar hari libur" (misal minggu ini Rabu gak libur, gantinya Jumat), karyawan harus kirim 2 ajuan terpisah, dan Elvan harus mastiin manual sendiri apakah tanggal yang diajukan itu beneran hari libur default karyawan itu atau bukan. Redesain ini menyatukan jadi 1 form dengan 3 jenis eksplisit: **Tukar**, **Skip**, **Tambah** — sekaligus mindahin pengecekan "apakah tanggal ini beneran hari liburnya" dari manual (Elvan) ke otomatis (sistem).

---

## 1. Ringkasan

Ganti 2 jenis ajuan (`tambah`/`batal`) jadi 3 jenis eksplisit di 1 form: **Tukar** (geser 1 hari libur dari tanggal lama ke tanggal baru, 1 ajuan = 1 baris data = 1 klik approve/tolak), **Skip** (batalkan 1 hari libur tanpa ganti — nama baru buat jenis `batal` yang sudah ada), **Tambah** (libur ekstra di luar jadwal — jenis `tambah` yang sudah ada, tidak berubah). Sistem otomatis membatasi tanggal mana yang boleh dipilih sesuai jenisnya, jadi Owner/Mandor gak perlu cek manual lagi pas approval.

---

## 2. Kenapa dibutuhkan

Dikonfirmasi Elvan lewat brainstorm 11 Agustus (sesi kedua):
- Kasus tukar hari libur (paling umum terjadi) butuh 2 ajuan terpisah dengan sistem sekarang — dobel kerjaan buat karyawan, dan approve-nya juga 2x klik.
- Gak ada jaminan tanggal yang diajukan buat "Batalkan Libur" itu beneran hari libur default karyawan itu — Elvan harus ngecek manual sendiri tiap kali approval, padahal ini bisa dihitung otomatis oleh sistem dari `users.hari_libur_default`.
- Kalau 2 ajuan terpisah (batal + tambah) buat 1 tukar, ada resiko yang satu di-approve dan yang satu ketinggalan/ditolak — karyawan bisa rugi kehilangan hari libur tanpa dapat ganti.

---

## 3. Keputusan yang dikunci (brainstorm 11 Agustus, dikonfirmasi Elvan satu-satu)

### 3.1 Definisi 3 jenis

| Jenis | Tanggal dibutuhkan | Syarat tanggal | Jendela waktu |
|---|---|---|---|
| **Tukar** | tanggal lama (dibatalkan) + tanggal baru (pengganti) | tanggal lama = hari libur default karyawan; tanggal baru = hari yang normalnya dia kerja (bukan hari libur default) | sisa minggu ini + minggu depan (Senin–Minggu), minimal besok |
| **Skip** (dulu "Batal") | 1 tanggal (dibatalkan, tanpa ganti) | harus hari libur default karyawan | sisa minggu ini + minggu depan, minimal besok |
| **Tambah** | 1 tanggal (libur ekstra) | bebas — kalau kebetulan sudah hari libur, tetap diizinkan kirim (tanpa efek tambahan, tidak diblok) | bebas, minimal besok saja (TIDAK dibatasi 2 minggu — karyawan sering ajukan jauh-jauh hari buat acara) |

**Contoh Tukar (dikonfirmasi lewat 2 skenario nyata dari Elvan):**
- Rabu minggu ini (default libur) → Jumat minggu ini (pengganti). Hasil: tetap 1 hari libur minggu ini, geser dari Rabu ke Jumat.
- Kamis minggu ini (pengganti, "dipakai duluan") → Rabu minggu depan (default libur, dibatalkan). Hasil: minggu ini dapat 2 hari libur (Rabu asli + Kamis), minggu depan 0 hari libur (Rabu depan tetap masuk) — **net tetap sama** di rentang 2 minggu itu.

Kesimpulan penting dari contoh kedua: **tanggal baru boleh lebih dulu dari tanggal lama** (gak harus urut kronologis) — validasi TIDAK BOLEH memaksa "tanggal baru harus setelah tanggal lama".

### 3.2 Jendela waktu Tukar & Skip

Minggu dihitung Senin–Minggu (bukan Minggu–Sabtu). Jendela = dari besok (H+1) sampai Minggu (akhir) di minggu depan — mencakup sisa hari minggu ini + seluruh minggu depan, walau beda bulan.

### 3.3 Cara nyimpen di database

Tabel `jadwal_libur` dapat 1 kolom baru: `tanggal_baru` (nullable, DATE), dipakai KHUSUS jenis Tukar. `jenis` ENUM dapat nilai baru `'tukar'` — nilai lama `'tambah'`/`'batal'` **TIDAK diubah namanya di database** (data yang mungkin sudah ke-submit sejak fitur asli live tetap valid), cuma label tampilannya yang berubah (`'batal'` → tampil "Skip").

**Kenapa 1 baris 2 kolom, bukan 2 baris terhubung:** dengan 1 baris, approve/tolak otomatis berlaku ke kedua tanggal sekaligus — gak mungkin kejadian "setengah disetujui" (satu approve, satu ketinggalan/ditolak) yang bisa bikin karyawan rugi kehilangan hari libur tanpa ganti.

### 3.4 Validasi

- Duplikat/bentrok: tanggal baru yang diajukan (baik tanggal lama maupun tanggal baru untuk Tukar) TIDAK BOLEH bentrok dengan tanggal manapun (lama atau baru) dari ajuan lain milik karyawan yang sama yang masih `pending`/`approved`. Perluasan dari pengecekan yang sudah ada di `JadwalLiburController::store()`.
- Validasi "tanggal harus hari libur default" dan "dalam jendela 2 minggu" dilakukan di SERVER (otoritatif), bukan cuma di tampilan — form tetap dibantu JS/dropdown buat UX, tapi keputusan akhir selalu dari server.

---

## 4. Titik integrasi (kode)

| # | File | Perubahan |
|---|---|---|
| 1 | Migrasi baru | Tambah kolom `tanggal_baru` (DATE, nullable) ke `jadwal_libur`; perluas ENUM `jenis` jadi `('tambah','batal','tukar')`. |
| 2 | `app/Models/JadwalLibur.php` | `$fillable` tambah `'tanggal_baru'`; `$casts` tambah `'tanggal_baru' => 'date'`; `JENIS` const: label `'batal'` diganti jadi "🚫 Skip Libur" (teks doang, value gak berubah), tambah `'tukar' => '🔄 Tukar Libur'`. Method baru `labelTanggal(): string` — buat `jenis==='tukar'` return `"{tanggal lama} → {tanggal baru}"` diformat `d/m/Y`, buat jenis lain return tanggal biasa (dipakai di blade riwayat/approval & notif Telegram, gantiin akses langsung `$jadwal->tanggal->format(...)` di tempat-tempat itu). |
| 3 | `app/Services/LiburService.php` | HANYA `ambilOverride()` yang berubah — baris `'jenis' => 'tukar'` di-expand jadi 2 entry sintetis: `{tanggal: tanggal_lama, jenis: 'batal'}` + `{tanggal: tanggal_baru, jenis: 'tambah'}` sebelum dikembalikan. Query range-nya juga perlu `orWhere` supaya nangkep baris tukar yang salah satu tanggalnya (lama ATAU baru) jatuh di rentang yang dicari, walau yang satunya di luar rentang (kasus lintas bulan). **`cocokLiburPada()`/`hitungHariKerjaPada()` (logic murni yang sudah teruji 13/13) TIDAK disentuh sama sekali** — sudah generik menangani pasangan tanggal+jenis apa pun. Tambah 2 method baru: `jendelaTukarSkip(Carbon $sekarang): array` (hitung [awal, akhir] jendela 2 minggu, Senin–Minggu) dan `tanggalKandidatLibur(int $hariLiburDefault, Carbon $awal, Carbon $akhir): array` (list tanggal dalam rentang yang cocok hari default — dipakai buat isi dropdown "tanggal lama"/Skip). |
| 4 | `app/Http/Controllers/JadwalLiburController.php` | `create()`: kirim ke view daftar tanggal kandidat (dari `LiburService::tanggalKandidatLibur`), batas jendela (dari `jendelaTukarSkip`), dan flag `$punyaLiburDefault = $user->hari_libur_default !== null`. `store()`: validasi jenis `in:tambah,batal,tukar` (value `'tambah'`/`'batal'` dipertahankan biar konsisten sama data lama — form sisi user pakai label Tukar/Skip/Tambah, tapi `'tukar'` adalah value baru, `'batal'`≈Skip dan `'tambah'`≈Tambah tetap value lama); `tanggal_baru` wajib+beda tanggal kalau `jenis==='tukar'`; validasi server "tanggal cocok hari default" & "dalam jendela" via `LiburService`; perluas cek bentrok ke `tanggal` DAN `tanggal_baru`. `kirimNotifPengajuan()`/`kirimNotifHasil()`: pakai `$jadwal->labelTanggal()` gantiin akses tanggal langsung. |
| 5 | `resources/views/jadwal-libur/create.blade.php` | Redesain: grid jenis jadi 3 kartu (Tukar/Skip/Tambah) — kartu Tukar & Skip disembunyikan total kalau `!$punyaLiburDefault`. Field tanggal berubah sesuai jenis dipilih (JS toggle): Tukar tampilkan 2 field (`<select>` tanggal lama dari daftar kandidat + `<input type=date>` tanggal baru dengan `min`/`max` = jendela); Skip tampilkan 1 `<select>` dari daftar kandidat; Tambah tampilkan 1 `<input type=date min=besok>` tanpa `max`. |
| 6 | `resources/views/jadwal-libur/index.blade.php`, `approval.blade.php` | Ganti `{{ $jadwal->tanggal->translatedFormat(...) }}` jadi pakai `$jadwal->labelTanggal()` biar Tukar nampilin 2 tanggal. |

**Yang TIDAK berubah:** `cocokLiburPada()`/`hitungHariKerjaPada()` (core logic, sudah teruji), alur approve/reject (tetap 1 tombol, 1 baris data), 3 titik integrasi yang sudah pakai `LiburService::isLibur()` (`cron-alpha.php`, `cron-kode-absen.php`, `GajiService`, `ProfilController`) — semuanya otomatis dapat efek Tukar lewat `ambilOverride()` tanpa perlu disentuh.

---

## 5. SQL (jalan manual di phpMyAdmin production, idempotent, SEBELUM kode di-deploy)

```sql
ALTER TABLE jadwal_libur MODIFY COLUMN jenis ENUM('tambah','batal','tukar') NOT NULL;
ALTER TABLE jadwal_libur ADD COLUMN IF NOT EXISTS tanggal_baru DATE NULL AFTER tanggal;
```
(Kalau `ADD COLUMN IF NOT EXISTS` ditolak karena versi MySQL <8.0.29, jalankan tanpa `IF NOT EXISTS` — sama seperti catatan di spec kode-absen-per-karyawan.)

---

## 6. Testing

**Logic murni (standalone, tanpa DB — perluasan dari `tests/jadwal-libur/test_libur_service.php` yang sudah ada, 13/13 harus tetap hijau):**
- `jendelaTukarSkip()`: hitung batas [awal,akhir] benar buat beberapa hari-ini berbeda (Senin, Jumat, Minggu — biar kasus lintas-minggu ke-cover).
- `tanggalKandidatLibur()`: hasil daftar tanggal cocok hari default dalam rentang, termasuk kasus rentang lintas bulan.
- `ambilOverride()`-equivalent (lewat method baru yang testable tanpa DB, atau lewat `cocokLiburPada` dengan overrides array yang sudah di-expand manual): baris tukar menghasilkan 2 efek (tanggal lama jadi kerja, tanggal baru jadi libur) — termasuk kasus tanggal baru LEBIH DULU dari tanggal lama (skenario Kamis-minggu-ini → Rabu-minggu-depan).

**Butuh verifikasi manual di production:**
- Form: pilih Tukar, cek dropdown tanggal lama cuma nampilin hari libur default beneran; cek tanggal baru gak bisa pilih tanggal di luar jendela 2 minggu.
- Kirim ajuan Tukar → approve → cek `LiburService::isLibur()` efeknya kepakai di kode absen (tanggal lama dapat kode, tanggal baru gak dapat kode) dan cron-alpha (tanggal lama gak ditandai Alpha meski gak absen, karena sekarang dia emang kerja — jadi HARUS absen; tanggal baru gak ditandai Alpha meski gak absen, karena dianggap libur).
- Karyawan tanpa `hari_libur_default` → buka form, pastikan cuma opsi Tambah yang muncul.

---

## 7. Di luar cakupan / dicatat, bukan diperbaiki di sini

- Tanggal baru (Tukar) yang kebetulan bentrok sama ajuan lain yang sudah approved dari SEBELUM redesign ini (jenis lama) — dicek lewat pengecekan bentrok baru di §3.4/§4 poin 4, tapi tidak ada migrasi data buat baris lama yang sudah ada (kalau ada) — dianggap tidak masalah karena baris lama tetap konsisten (1 tanggal 1 efek).
- Validasi "tanggal baru gak boleh bentrok sama tanggal yang UDAH jadi libur lewat ajuan approved lain" (bukan cuma bentrok sama tanggal yang sama persis) — edge case langka, tidak divalidasi di v1 ini, dicatat kalau ada laporan nyata.
- Redesain label/copy fitur lain yang masih nyebut "Batalkan Libur" (kalau ada di luar 2 file blade yang disebut §4) — di luar cakupan, akan dicek lewat grep pas eksekusi kalau ketemu.

---

## 8. Yang TIDAK berubah

Alur izin/sakit/cuti/dinas_luar, halaman absensi, `GajiService`, `cron-alpha.php`/`cron-kode-absen.php` (cuma konsumen `LiburService::isLibur()`, tidak disentuh langsung), sistem approval Owner/Mandor (tetap 1 tombol per ajuan).
