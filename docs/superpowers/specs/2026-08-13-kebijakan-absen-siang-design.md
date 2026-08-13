# Desain: Redesain Absen Siang → "Lapor Progress" + "Kembali Kerja"

**Tanggal:** 13 Agustus 2026
**Status:** Disetujui (brainstorming) — siap ke tahap rencana implementasi
**Pemilik:** Elvan (owner, non-teknis)
**Konteks:** Elvan mau kaji ulang kebijakan absen siang & potongannya. Absen siang sekarang (`app/Http/Controllers/AbsensiController.php`, jendela 13:00-14:00) sebenarnya melayani 2 tujuan sekaligus: (1) deteksi kendala kerja harian, (2) cegah karyawan istirahat lebih lama dari jam istirahat. Digali lewat brainstorming: masalah utamanya, laporan kendala baru masuk jam 1-2 siang — **kelewat telat buat Owner ambil keputusan hari itu juga**. Istirahat resmi jam 12:00-13:00.

---

## 1. Ringkasan

Absen siang yang sekarang (1 checkpoint, jam 13:00-14:00, form dropdown kendala) dipecah jadi **2 checkpoint independen**:

1. **"Lapor Progress"** — jam 11:00-12:00, SEBELUM istirahat. Isinya laporan progress kerja + kendala (kalau ada), digali sampai ke akar masalah. Kalau ada kendala, langsung notif Telegram ke Owner.
2. **"Kembali Kerja"** — jam 13:00 tepat, PAS istirahat berakhir. Cukup 1 tombol tap, gak ada form — murni buat mastiin karyawan gak molor istirahat. Reuse mekanisme potongan prorata yang sekarang udah jalan & terbukti (cuma dilepas dari form laporan).

Kedua checkpoint independen — gak saling ngeblok (karyawan yang kena denda checkpoint 1 tetap wajib checkpoint 2, dan sebaliknya).

---

## 2. Keputusan yang dikunci

| # | Keputusan | Detail |
|---|---|---|
| 1 | Kenapa dipecah jadi 2 | Checkpoint 1 (sebelum istirahat) menjawab tujuan "deteksi kendala cepat". Checkpoint 2 (pas istirahat berakhir) menjawab tujuan "cegah istirahat kelamaan" — gak bisa digabung karena istirahatnya sendiri belum kejadian pas checkpoint 1. |
| 2 | Foto checkpoint 1 | WAJIB 1 foto lokasi, **langsung dari kamera HP, gak boleh pilih dari galeri** (anti-akal-akalan pakai foto lama/palsu). |
| 3 | Isi laporan checkpoint 1 | BUKAN dropdown/pilihan template lagi. Alurnya: foto → 1 pertanyaan progress (digilir, lihat #4) dijawab bebas → "Ada kendala?" Ya/Tidak → kalau Ya, 2 pertanyaan lanjutan FIXED ("Apa kendalanya?" + "Kenapa itu bisa terjadi?") buat gali ke akar masalah. |
| 4 | Variasi pertanyaan progress | Kumpulan pertanyaan (5-10 variasi, ditulis di kode — LIHAT #9) dipilih **beda per-karyawan per-hari** (2 orang bisa dapat pertanyaan beda di hari yang sama; orang yang sama dapat variasi beda dari hari ke hari). Deterministik dari tanggal+user_id, gak perlu tabel/state tambahan. |
| 5 | Balasan otomatis | Setelah submit, karyawan langsung dapat balasan otomatis (bukan AI — dari kumpulan kalimat siap pakai juga, beda versi buat "ada kendala" vs "tidak ada kendala"). |
| 6 | Notif kendala ke Owner | Kalau ada kendala, Owner langsung dapat notif Telegram (progress + kendala + penyebabnya) — reuse jalur notifikasi kendala yang SUDAH ADA sekarang (`kirimNotifKendala()`), cuma dipindah ke checkpoint baru ini. |
| 7 | Denda checkpoint 1 | Kalau lewat jam 12:00 belum lapor sama sekali → potongan **flat Rp20.000** (reuse `POTONGAN_TELAT`, konsisten sama potongan lain di sistem, bukan angka baru). |
| 8 | Checkpoint 2 disederhanakan | Cukup **1 tombol "Lanjut Kerja"** — gak ada foto, gak ada form. **GPS tetap dicek** (diam-diam lewat browser pas tombol ditekan, gak nampilin form/peta ke karyawan — cuma validasi radius kayak sekarang). Toleransi 3 menit dari jam 13:00, potongan **prorata per menit telat** (rumus SAMA PERSIS yang sekarang, gak diubah). Kalau sama sekali gak tap sampai jam 14:00 → potongan flat Rp20.000 (sama kayak sekarang). |
| 9 | Kelola pertanyaan | Ditulis di kode (PHP const, pola sama `STATUS_PEKERJAAN`/`JENIS_KENDALA` yang sudah ada) — BUKAN halaman admin baru. Kalau Elvan mau nambah/ubah pertanyaan nanti, minta lewat sesi Claude Code, bukan self-service. |
| 10 | Cakupan karyawan | Berlaku ke populasi yang sama kayak absen siang sekarang (level kantor 2,4,7 + level workshop 3,5,6) — TIDAK diubah dari sekarang, cuma alur & isinya yang berubah. |

---

## 3. Model data

**Kolom di tabel `absensi` yang DIPAKAI ULANG (nama kolom TIDAK berubah, cuma maknanya digeser sesuai checkpoint baru):**

| Kolom lama | Dulu buat | Sekarang buat |
|---|---|---|
| `foto_siang_1` | 1 dari 3 foto laporan siang | Foto WAJIB checkpoint 1 "Lapor Progress" (live-camera) |
| `lat_siang`/`lng_siang`/`gps_valid_siang` | GPS absen siang | GPS checkpoint 1 (dicek radius kayak sekarang, cuma dipindah waktu) |
| `deskripsi_kendala` | Deskripsi bebas (dibarengi dropdown `jenis_kendala`) | Jawaban "Apa kendalanya?" (checkpoint 1) |
| `ada_kendala` | Toggle ada kendala/tidak | TETAP SAMA, gak berubah |
| `jam_absen_siang` | Jam submit form siang lama | Jam tap "Kembali Kerja" (checkpoint 2) |
| `potongan_siang_dicatat` | Cegah dobel-potong 1 mekanisme lama | Sekarang KHUSUS checkpoint 2 (biar gak nabrak flag checkpoint 1 yang baru, lihat di bawah) |
| `potongan_telat` | Akumulasi semua potongan (telat pagi+siang) | TETAP SAMA — checkpoint 1 & 2 dua-duanya nambah ke kolom ini |

**Kolom BARU yang perlu ditambah:**

| Kolom baru | Tipe | Buat apa |
|---|---|---|
| `jam_lapor_progress` | time, nullable | Jam submit checkpoint 1 |
| `pertanyaan_progress` | text, nullable | Pertanyaan progress yang di-assign hari itu (disimpan verbatim, biar histori tetap jelas walau daftar pertanyaan di kode berubah nanti) |
| `jawaban_progress` | text, nullable | Jawaban bebas karyawan buat pertanyaan progress |
| `kendala_kenapa` | text, nullable | Jawaban "Kenapa itu bisa terjadi?" (follow-up akar masalah, field BARU — beda dari `deskripsi_kendala` yang jawab "apa"-nya) |
| `potongan_progress_dicatat` | boolean, default false | Flag anti-dobel-potong KHUSUS checkpoint 1 (independen dari `potongan_siang_dicatat` yang sekarang jadi milik checkpoint 2) |
| `lat_kembali_kerja`/`lng_kembali_kerja`/`gps_valid_kembali_kerja` | decimal/decimal/boolean, nullable | GPS checkpoint 2 — TERPISAH dari `lat_siang`/`lng_siang` (yang sekarang jadi milik checkpoint 1), karena 2 checkpoint ini beda waktu & lokasi pengecekannya. Diambil diam-diam pas tombol "Lanjut Kerja" ditekan (browser geolocation), TANPA form/peta yang kelihatan ke karyawan. |

**Kolom yang JADI GAK DIPAKAI LAGI (dibiarkan di skema, TIDAK di-drop — YAGNI, gak perlu migration bongkar-pasang):**
`status_pekerjaan`, `jenis_kendala`, `foto_siang_2`, `foto_siang_3`.

---

## 4. Titik integrasi

| # | Bagian | Perubahan |
|---|---|---|
| 1 | `AbsensiController::index()` (baris 50-66) | Logic auto-flat sekarang jadi 2 blok terpisah: satu buat checkpoint 1 (cek jam >= 12:00, pakai flag `potongan_progress_dicatat`), satu buat checkpoint 2 (cek jam >= 14:00, pakai flag `potongan_siang_dicatat`, LOGIC-NYA TIDAK BERUBAH dari sekarang). |
| 2 | `formSiang()`/`absenSiang()` (baris 228-304) | Diganti/dipecah jadi 2 pasang method baru: `formLaporProgress()`/`laporProgress()` (checkpoint 1, alur pertanyaan-bertahap, foto+GPS) dan `formKembaliKerja()`/`kembaliKerja()` (checkpoint 2, cuma 1 tombol — tapi endpoint-nya tetap terima `lat`/`lng` dari JS geolocation buat validasi radius, reuse logic GPS yang sudah ada di `absenSiang()` sekarang). |
| 3 | `getFaseAbsen()` (baris 565+) | Perlu kenal 2 fase baru (`perlu_lapor_progress`, `perlu_kembali_kerja`) menggantikan `perlu_absen_siang` yang sekarang, biar halaman `/absensi` nunjukin tombol yang bener sesuai jam & progress hari itu. |
| 4 | `kirimNotifKendala()` (existing) | Dipanggil dari `laporProgress()` (checkpoint 1) alih-alih dari `absenSiang()` lama — isi pesannya nambah bagian "Kenapa" (dari `kendala_kenapa`), gak cuma "Apa". |
| 5 | Bank pertanyaan & balasan | Const array baru di `AbsensiController`, pola sama `STATUS_PEKERJAAN`/`JENIS_KENDALA` yang sudah ada — `BANK_PERTANYAAN_PROGRESS`, `BALASAN_TANPA_KENDALA`, `BALASAN_ADA_KENDALA`. Pemilihan pertanyaan: `(Carbon::today()->dayOfYear + $user->id) % count(BANK_PERTANYAAN_PROGRESS)` — deterministik, gak butuh state/tabel baru. |
| 6 | Constanta jam | `JAM_MASUK_SIANG` dipertahankan buat checkpoint 2 (13:00). Tambah `JAM_LAPOR_PROGRESS='11:00'` (buka form) dan `JAM_BATAS_LAPOR_PROGRESS='12:00'` (deadline, dulu peran ini dipegang campur sama `JAM_SKIP_SIANG`). `JAM_SKIP_SIANG='14:00'` tetap dipakai checkpoint 2 (deadline auto-flat). |

---

## 5. Halaman & alur baru

| Halaman | Ganti dari | Perubahan |
|---|---|---|
| Form "Lapor Progress" (checkpoint 1) | `absensi/form-siang.blade.php` | Didesain ulang jadi alur bertahap: foto (input kamera, atribut `capture` di HTML biar gak bisa pilih galeri) → pertanyaan progress (teks bebas) → toggle kendala → (kondisional) 2 pertanyaan lanjutan → submit → tampilkan balasan otomatis. |
| Form "Kembali Kerja" (checkpoint 2) | (bagian dari `form-siang.blade.php` lama) | Halaman/tombol baru yang jauh lebih simpel — 1 tombol "Lanjut Kerja", tanpa GPS/foto/form. |
| `absensi/index.blade.php` | — | Tombol aksi menyesuaikan fase baru (`perlu_lapor_progress` / `perlu_kembali_kerja`) dari `getFaseAbsen()`. |

---

## 6. Testing

**Logic murni (standalone, pola sama file test lain di proyek ini):**
- Pemilihan pertanyaan progress: `(dayOfYear + userId) % jumlah` menghasilkan index yang valid & bervariasi (test beberapa kombinasi tanggal+user beda hasil).
- Perhitungan potongan checkpoint 2 (prorata) — **tidak berubah**, tapi tetap dites ulang biar kebukti gak ada regresi (reuse `hitungMenitTelat`/`hitungPotongan` yang sudah ada).
- Auto-flat checkpoint 1 (>= jam 12:00, `potongan_progress_dicatat` belum true) vs checkpoint 2 (>= jam 14:00, `potongan_siang_dicatat` belum true) — dua-duanya independen, dites gak saling ganggu.

**Butuh verifikasi manual di production (gak bisa diuji headless bermakna):**
- Foto checkpoint 1 beneran gak bisa pilih dari galeri (behavior `capture` attribute beda-beda dikit per HP/browser).
- 2 karyawan beda dapat pertanyaan progress yang beda di hari yang sama.
- Kendala yang dilaporkan beneran nyampe ke Owner via Telegram lengkap (progress+kendala+penyebab).
- Checkpoint 2 tombol "Lanjut Kerja" — potongan prorata dihitung bener sesuai menit telat.

---

## 7. Yang TIDAK berubah

Rumus potongan prorata checkpoint 2 (`hitungMenitTelat`/`hitungPotongan`) — sama persis. Radius & aturan GPS per level (kantor ketat, workshop dicatat doang) — sama, cuma dipindah waktu ke checkpoint 1. Nominal Rp20.000 sebagai basis semua potongan telat/gak-lapor di sistem ini — TETAP SATU ANGKA, gak ada angka baru. Populasi karyawan yang wajib (level kantor+workshop) — sama kayak sekarang. Fitur Koreksi Absen (potongan manual per-hari, dibangun pagi ini) — tetap bisa dipakai buat koreksi kedua jenis potongan baru ini juga (kolom `potongan_telat` yang dikoreksi tetap kolom yang sama).

---

## 8. Di luar cakupan / dicatat, bukan dikerjakan di sini

- **Halaman kelola pertanyaan progress buat Owner** — sengaja ditunda (keputusan #9), pertanyaan di kode dulu. Bisa ditambah nanti kalau daftar pertanyaan sering perlu diubah.
- **Tanggapan sistem yang "paham" isi laporan (AI)** — sengaja TIDAK dipakai (dibahas & ditolak eksplisit pas brainstorming), balasan tetap dari kumpulan kalimat siap pakai, bukan AI.
- **Notifikasi ke karyawan soal checkpoint 2** — gak dibahas spesifik, ikut pola existing (balasan simpel aja setelah tap, gak perlu broadcast ke Owner kayak checkpoint 1).
