# CLAUDE.md — CanopiBSD v2

> Dokumen ini adalah "otak" project untuk Claude Code. Dibaca otomatis tiap sesi baru.
> **WAJIB:** update bagian "Status Terkini" di akhir tiap sesi kerja.

---

## 🎯 IDENTITAS PROYEK

| Item | Detail |
|---|---|
| Sistem | CanopiBSD v2 — sistem manajemen bisnis Pusat Kanopi BSD & Pusat Besi (~14 karyawan) |
| Framework | Laravel 13 (13.12.0), PHP 8.3 |
| Owner | Elvan — **non-teknis**, selalu jelaskan pakai bahasa awam dulu sebelum istilah teknis |
| URL live | https://app.kanopibsd.co.id |
| Repo | https://github.com/elvanrivaldi43-droid/canopi-app |
| Hosting web | Niagahoster shared hosting (cPanel, srv170) — **BUKAN** VPS, tidak ada SSH ke sini |
| VPS terpisah | Hostinger KVM 1 (1vCPU/4GB), IP 187.77.143.121, awalnya untuk n8n/Ferrovabot — sekarang juga jadi tempat Claude Code jalan (`/root/projects/canopi-app`) |
| DB | u8221523_canopi_new (production), MySQL via phpMyAdmin |

---

## ⚙️ PREFERENSI KERJA (WAJIB DIIKUTI)

**Gaya komunikasi (dikunci, jangan diubah):**
- Penasihat, bukan asisten sekadar nurut — akurasi lebih penting dari persetujuan
- Kalau ada masalah/risiko, sebut di baris pertama, jangan ditunda ke paragraf ketiga
- Label keyakinan: `[Pasti]` / `[Kemungkinan Besar]` / `[Menebak]` untuk klaim penting
- Tanpa pujian basa-basi ("pertanyaan bagus", dll)
- Bahasa awam dulu, istilah teknis belakangan — Elvan non-teknis
- Tahan posisi kecuali ada fakta baru, jangan berubah pendapat cuma karena didesak
- Bahasa Indonesia informal, langsung ke inti

**Cara kerja teknis:**
1. **Mode manual/ask-before-edit** — jangan auto-accept edit tanpa konfirmasi, kecuali diminta khusus
2. **Fitur baru/perubahan besar** → susun rencana dulu (`/plan`), diskusikan tujuan bisnisnya, baru eksekusi setelah disetujui
3. **Bug fix** → JANGAN asumsi bug itu ada. Buktikan dulu dengan reproduksi/eksekusi kode nyata sebelum ubah apapun (lihat contoh kasus hollow 5x10 di bawah — dugaan awal ternyata salah, dan itu ketahuan justru karena diverifikasi jalan beneran, bukan cuma dibaca)
4. Build dipecah langkah kecil, ditest satu per satu — jangan sekaligus banyak perubahan tak berhubungan dalam 1 commit
5. SQL harus idempotent (`IF NOT EXISTS`, aman kalau error #1060 dilewati)
6. Tiap akhir sesi/modul selesai, tampilkan ringkasan progres tanpa diminta

---

## 🚨 DEPLOY WORKFLOW

```
Edit kode → git commit → git push ke GitHub
→ GitHub Actions (deploy.yml) OTOMATIS jalan
→ FTP ke server Niagahoster (protocol: ftp, BUKAN ftps — Niagahoster block ftps dari IP GitHub)
→ Cache Laravel auto-clear
→ Selesai ±1-2 menit
```

**Insiden 9-11 Juli 2026 (SUDAH SELESAI diperbaiki):** Repo GitHub sempat berisi source code lama, push pertama menimpa banyak file dengan versi lama → web down total. Sudah diperbaiki tuntas 11 Juli — repo sekarang = cerminan persis server production. Auto-deploy aman dipakai normal. **Tetap disiplin:** `git pull` dulu sebelum mulai kerja di sesi/device manapun.

File diagnostik di server (boleh dipakai, jangan hapus): `bersih-bersih.php`, `lihat-log.php`.

**Pelajaran deploy mahal (jangan terulang):**
- Controller jangan ke-upload ke folder views (dan sebaliknya)
- Hapus semua `.php` di `storage/framework/views/` tiap ganti file blade
- Cek spasi siluman di nama folder cPanel (rename untuk lihat nama asli)
- Typo nama file sering jadi biang "view not found"
- Baris kembar/duplikat di tabel DB → error 1062 → hard reset browser setelah bersihkan DB
- `laravel.log` menumpuk lama→baru, yang relevan di paling bawah — kosongkan dulu, baru picu error, biar baca yang baru
- Emoji di blade file bisa korup di server → pakai SVG icon, jangan pernah emoji
- File tes (`teswa.php`, `testelegram.php`, dll) di `public/` **HARUS dihapus** setelah selesai dipakai (risiko keamanan)

---

## 🏗️ ARSITEKTUR RAB — PRINSIP INTI (jangan dilanggar)

- **Satu mesin block-mode** (`hitungSatuBlok` tanpa margin → margin ditambah di level Opsi), BUKAN dua wizard terpisah
- Struktur: `OPSI[] → BLOK[] → komponen`. Blok di-**on/off**, tidak dihapus. Opsi diduplikat lalu diubah (bukan bikin dari nol tiap kali)
- Estimasi admin vs harga final surveyor **disimpan terpisah** (untuk bukti + belajar admin mana yang sering meleset)
- Produktivitas RAB (dipakai untuk harga) vs tabel tahap SWE (dipakai untuk manajemen produksi) = **DUA TABEL TERPISAH**, jangan pernah digabung
- **Margin = untung dari harga jual**: `pokok ÷ (1 − margin)`. BUKAN markup (`modal × (1 + markup)`) — beda rumus, jangan tertukar

**Model Biaya v2 — komponen lengkap (semua sudah jalan & terbukti):**
- V2-1: fab/inst terpisah (produktivitas_inst sendiri, `hariFab + hariInst`)
- V2-2: consumable rangka+atap per m² (per jenis atap, kolom `rab_atap.consumable`)
- V2-3a: add-on berat durasi (kecepatan satuan/hari, `upah = (qty/dFab + qty/dInst) × upahHariTim`); halaman kelola di `/addon` (`AddonController`, owner-only)
- V2-4: insentif per kondisi kerja (`kena = 'inst'/'fabinst'`, dipisah `pengaliInst×hariInst + pengaliFab×hariFab`)
- V2-5a: finishing standar (per m² rangka, melekat otomatis)
- V2-5b: powder coating (pilihan per-opsi via `pane.dataset.finishing` + `select .opsi-finishing`, default Standar)
- V2-6: nginap dihitung dari hari INSTALASI, operasional per-opsi (transport/genset/nginap/makan terpisah di rincian)
- Tim fab vs inst dipisah (`rab_jenis_kerja` + kolom `jml_tukang_inst`/`jml_kenek_inst`, fallback ke tim fab kalau kosong)
- Centang "atap pasang di rangka lama/reparasi" → upah pasang atap HANYA muncul kalau dicentang
- Besi tambahan manual per blok (tombol "+ Besi Tambahan", dropdown `BESI_SEMUA`)

---

## 📐 ATURAN BISNIS PENTING (logika, bukan sekadar preferensi)

- **GPS** = bukti kehadiran fisik. Hanya Owner (level 1) dan Surveyor (level 3) boleh "Ambil GPS" di profil lokasi
- **Jarak workshop→lokasi**: input MANUAL, bukan auto-haversine — keputusan sengaja Elvan untuk akurasi harga
- **Nginap diputus OTOMATIS oleh sistem** (bukan surveyor nebak): km≥15 & durasi 3-5 hari → hotel; >5 hari → kontrakan; <3 hari → PP harian
- **Makan** hanya berlaku kalau km≥15 (makan luar kota)
- **Tim yang berangkat ke lokasi**: tukang+kenek SEMUA orang ikut, bukan cuma tim inst

**Sistem 7 level user:**
| Level | Peran | Akses |
|---|---|---|
| 1 | Owner (Elvan) | Penuh, termasuk modal/margin |
| 2 | Admin Operasional | Lihat harga jual, TIDAK lihat modal/tarif |
| 3 | Supervisor/Mandor | Lihat harga jual, TIDAK lihat modal/tarif; akses pipeline+RAB |
| 4 | Marketing | Hanya lead milik sendiri |
| 5 | Teknisi | Terbatas — absen, tugas, gaji sendiri |
| 6 | Driver | Terbatas — absen, log bensin, tugas, gaji sendiri |
| 7 | Toko | Minimal |

---

## ✅ MODUL SELESAI & TERBUKTI JALAN

Fondasi Laravel 13 (Auth Breeze, 7 level + `CheckLevel` middleware, dark/light mode) · Karyawan (CRUD, `tanggal_bergabung` + `tgl_masuk_kerja`, tunjangan default 0) · Registrasi mandiri via token · Absensi (GPS+kode) · Izin+approval · Profil · Penggajian (slip UM 1-15 & gaji 16-akhir) · Kasbon (`status` VARCHAR, syarat masa kerja≥1thn dari `tanggal_bergabung`, gaji bersih dari `gaji_harian×26`, tombol Approve/Tolak Owner) · Log Bensin · Luar Kota · Pipeline (kanban drag-drop SortableJS, kartu `<div>` bukan `<a>`, `bubbleScroll` untuk auto-scroll) · Alur admin↔surveyor (estimasi vs final terpisah, warning >15% selisih) · Profil Lokasi (foto Cloudinary, GPS owner+surveyor saja) · Wizard RAB 3 step · Nego+approval via Telegram (`rab_approval`, `ApprovalController`) · Validasi+autosave RAB · Penawaran cetak (printable, TTD digital base64, rekening BCA Syariah 0420017279 a.n Mohammad Elvan Rivaldi M) · **Model Biaya v2 lengkap (V2-1 s/d V2-6)** · KPI/Rapor/Ujian online · Sidebar per-level (sempat salah tampil sama semua level, sudah di-fix 24 file)

**CuttingService — sudah DIVERIFIKASI dengan eksekusi PHP nyata (11 Juli):** TIDAK ada bug dobel-hitung frame/support (504 skenario diuji, 0 overlap, guard `$midV/$midH` bekerja benar).

---

## 🔄 SEDANG BERJALAN — KALIBRASI (Juli 2026)

**⚠️ STATUS: DATA MASIH TES — belum boleh dipakai ke customer asli sebelum kalibrasi tuntas.**

Proyek referensi: alderon 51m², harga jual Rp 41 juta.

**Temuan terkunci:** margin 30% (bukan 45%) · consumable rangka ~Rp30.000/m² · consumable atap alderon ~Rp40.000/m² · finishing ~Rp50.000/m² · produktivitas instalasi 8,5 m²/hari · upah sudah mendekati akurat

**Prinsip kalibrasi:**
- Isi MODAL, bukan harga jual (margin ditambah otomatis oleh mesin)
- Produktivitas = kecepatan m²/hari, BUKAN lama hari
- Pakai hari kerja BERSIH (fokus penuh, tanpa tunggu barang/disambi project lain)
- Ambil dari 3-5 project, rata-ratakan, kurangi 10-15% dari kecepatan maksimal
- Panduan lengkap ada di `PANDUAN_KALIBRASI.md`, harga besi di tabel `master_material`

**Belum terpecahkan:**
- WF melintang/gawang belum dimodelkan benar (workaround sementara: set jumlah tiang=4)
- Hollow count discrepancy di Material Support — **RESOLVED 14 Juli** lewat validasi ke cutting list asli project PA-DUTA (Cutting Optimization Pro). Dugaan "Besi Tambahan dobel" GUGUR — fitur itu benar (dipakai nambah profil 4x6/3x3 yang beda material). Akar masalah sebenarnya 3, lihat "Temuan validasi PA-DUTA" di bawah.

---

## 📋 ROADMAP (urutan prioritas terkunci, jangan dibalik urutannya)

1. **Selesaikan kalibrasi** (sedang jalan)
2. Consumable fixed+variabel (kalau sering ada project kecil)
3. Tahap Perlindungan Lapangan — pemicu rantai tiang WF→scaffolding+takel, checklist wajib (talang→air rumah?/pohon?→cover jaring/bersih rutin)
4. Sesi Media R2 (Cloudflare R2: setup + retensi foto absen ~60 hari + 1 cara upload seragam semua modul + migrasi dari Cloudinary) — **WAJIB sebelum** modul volume-besar (absensi v2/portal customer) live
5. Portal Customer (PUNCAK) — link acak `/lihat/{kode}` tanpa login, PDF-ke-WA + link portal opsional, TTD online, tracking produksi, booking jadwal, bayar termin — butuh modul pembayaran + SWE dulu
6. SWE (Smart Work Engine) — PALING AKHIR. Tabel tahap produksi terpisah dari tabel produktivitas RAB, rekomendasi PIC per tahap, tracking hari kerja asli, auto-koreksi produktivitas dari data nyata
7. Multi-produk (pagar/tralis) — setelah kanopi matang+kalibrasi tuntas

**Ditunda/belum diputuskan:**
- C2b video link (Drive/YouTube) — ditunda ke Sesi Media R2
- Besi Bagian B denah interaktif — ditunda
- Pindah kondisi lokasi dari blok ke Profil Lokasi (luar kota/malam/beban berat) — perlu hati-hati, terpisah
- WhatsApp Business API resmi untuk notifikasi karyawan (14 orang) — Telegram jangan dipaksakan ke karyawan/customer, itu khusus Owner

---

## 🔧 CATATAN TEKNIS (jangan diulang kesalahannya)

- Laravel `Http::` facade **tidak jalan** di shared hosting Niagahoster → pakai `curl_init`
- `getenv()` lebih andal dari `env()` untuk baca token di shared hosting
- Kolom user: `status` (bukan `is_active`), `gaji_bulanan` (bukan `gaji`)
- Field `tanggal_bergabung` **HARUS** di-cast `'date'` di `User.php` model
- DB pakai `DB::table` (bukan Eloquent) di endpoint kritis untuk hindari masalah fillable
- **Notifikasi:** Owner (1 orang) via Telegram (curl langsung, token di kode bukan `.env`). Karyawan (14 orang): rencana pindah ke WhatsApp Business API resmi — jangan paksa mereka pakai Telegram
- **Storage foto:** Cloudflare R2 arah jangka panjang (bandwidth gratis, tak bisa suspend, murah). Cloudinary free tidak cukup untuk volume absensi+portal. Bangun infrastruktur media SEKALI dengan pola sama untuk semua modul, jangan tambal-sulam per modul
- **`LOG_LEVEL=error` di production** → `Log::info()`/`Log::debug()` kefilter, tak nyampe `laravel.log`. Debug log sementara pakai `Log::error()` biar pasti kebaca, hapus lagi setelah dipakai

---

## ❓ MODUL YANG BELUM ADA RANCANGAN SAMA SEKALI

**Jangan asumsi ini sudah selesai atau sudah ada rencana — kalau disinggung, ini butuh sesi diskusi khusus dulu (`/plan`), bukan langsung coding.**

- **Modul Keuangan / Laporan Profit** — menu-nya SUDAH ada di sidebar & dashboard, tapi isinya cuma placeholder ("Data dari modul Keuangan — belum tersedia", terkonfirmasi dari tampilan live 11 Juli). Belum ada rancangan struktur DB, belum ada keputusan apa saja yang dihitung (pemasukan dari mana, pengeluaran apa saja, bagaimana relasinya ke RAB/Project/Payroll). **Ini gap besar** — banyak modul lain (RAB, Project, Penggajian) sudah jalan tapi belum ada 1 tempat yang merangkum semuanya jadi laporan profit real.
- **SP Karyawan (Surat Peringatan otomatis)** — statusnya TIDAK JELAS, kemungkinan besar belum selesai dibangun meski KPI/Rapor sudah jalan. Perlu dicek langsung ke Elvan sebelum diasumsikan ada atau tidak.
- **Manufacture Tracking** — kemungkinan besar konsepnya sudah diserap ke rencana SWE (lihat Roadmap #6), tapi ini BELUM DIKONFIRMASI resmi oleh Elvan. Jangan asumsi keduanya sama sebelum ditanya langsung.

---

## 📌 STATUS TERKINI (update tiap akhir sesi kerja)

**11 Juli 2026:** Setup Claude Code selesai di VPS Hostinger (`/root/projects/canopi-app`). Investigasi bug hollow 5x10 selesai — terbukti BUKAN bug di CuttingService (diverifikasi eksekusi PHP nyata, 504 skenario). Kemungkinan sumbernya di fitur Besi Tambahan Manual, menunggu contoh kasus nyata dari Elvan. Repo GitHub sudah disinkronkan penuh dengan server production, insiden deploy 9-11 Juli sudah tuntas diperbaiki.

**14 Juli 2026 — Validasi cutting engine ke project nyata PA-DUTA (Kanopi Alderon, ~40 m²):**

Divalidasi lawan cutting list asli (Cutting Optimization Pro). Angka resmi (Statistics → Utilized bars): **hg 5x10 = 10 batang, hg 4x8 = 9, hg 3x3 = 4, hg 4x6 = 4** (stok 600cm).

*Temuan validasi PA-DUTA (4 hal):*
1. **Bug potong >600cm KONFIRMASI & sudah dibikin fix** (belum dipasang ke production). `CuttingService::potong()` menaruh potongan >600cm ke 1 batang → sisa NEGATIF, 0 sambungan → material kehitung KURANG. Fix: pecah jadi batang penuh + sambungan. Sudah diuji standalone, kasus ≤600cm identik (tidak merusak verifikasi 11 Juli). **TODO: pasang ke `app/Services/CuttingService.php`.**
2. **Stok potong harus PER-MATERIAL, bukan hardcode 600.** Hollow = 600cm. WF-150 dari vendor khusus = sampai 1200cm & bisa custom <7m → palang 7m TANPA sambungan. `const STOCK = 600` harus jadi parameter per-material.
3. **Profil (4x6 & 3x3) itu MENERUS keliling luar, bukan per-blok.** Cutting list: profil = 3 sisi (depan 700 + kiri 730 + kanan 528), belakang dibuang = 4 batang. Model per-blok salah (keluar 5).
4. **Model support 4x8 TERLALU RAPAT.** Model auto grid-83 dua-arah + anggap semua garis dalam = support → keluar 14, asli 9. Realita (dari gambar bertanda hitam=5x10/pink=4x8): garis dalam VERTIKAL = 5x10 spine (3 balok tengah @492) + ada balok tengah horizontal; support 4x8 pink lebih jarang. **TODO: kalibrasi ulang aturan support** biar output = 9.

*Yang sudah BENAR:* frame 5x10 (10=10 persis, termasuk dedup sisi berbagi antar blok + 3 balok tengah), luas atap dihitung dari bentuk asli berlekuk (~40 m², BUKAN bounding-box 51 m² — **ini bisa geser kalibrasi consumable/finishing per-m² yang tadinya dasar 51**).

*Infra baru 14 Juli:* Node.js v22 + uv + graphify terpasang. Upload-inbox untuk Elvan kirim foto/PDF ke Claude: service systemd `claude-upload` (port 8891, auto-nyala, file masuk `/root/inbox/`). Skill Claude Code: superpowers, ponytail, find-skills, frontend-design, graphify.

**14 Juli 2026 (lanjutan) — Perancang Rangka Fase 1 SELESAI di branch (belum merge/deploy):**

Fix potong >600cm sudah LIVE di produksi (commit di main, terverifikasi lewat cutting-test 700×730). Lalu didesain fitur besar **Perancang Rangka** (editable member-list): satu kanopi = daftar batang yang bisa diedit, tiap batang punya besi sendiri — melebur blok/profil/besi-tambahan. Spec: `docs/superpowers/specs/2026-07-14-perancang-rangka-design.md`. Plan Fase 1: `docs/superpowers/plans/2026-07-14-perancang-rangka-fase1.md`.

>>> RESUME POINT (mulai di sini kalau lanjut) <<<
**ARAH BARU (14 Juli, disetujui Elvan) — Denah Interaktif di RAB Opsi, v2.** Menggantikan pendekatan halaman terpisah `/rangka-desain`. Denah interaktif editable per blok, **dilebur ke RAB opsi** (bukan menu/halaman sendiri). `/rangka-desain` (halaman/rute/controller/menu owner) **dibuang** setelah jalur baru terbukti. Konsep member-list + mesin (`RangkaDesignService`/`CuttingService`) tetap dipakai ulang.
- **Spec desain final:** `docs/superpowers/specs/2026-07-14-denah-interaktif-rab-design.md` (18 keputusan terkunci — poligon editable, ukur sisi cm, arah support 3 pilihan, kotak saran simetris, besi per-bagian + warna/legenda, stok potong per-material, dsb).
- **Plan Tahap 1A (mesin/backend):** `docs/superpowers/plans/2026-07-14-denah-rab-tahap1a-engine.md` — stok per-material + jalur `tipe:'denah'` di `CuttingController::hitungSatuBlok` + reproduksi PA-DUTA lewat tes standalone.

**Prototype UX SUDAH DIVALIDASI Elvan (cocok).** File `tests/rangka/denah_prototype.html` (di-commit sbg referensi bangun DenahEditor asli). Standalone, disajikan lewat harness `tests/rangka/preview_server.php` (untracked, gitignore) di `http://187.77.143.121:8892/denah`. Fitur teruji: seret sudut mulus (rAF, tanpa render ulang saat drag, snap saat lepas), +/− sudut, panel "Ukur sisi" ketik cm presisi (koma diterima, tak di-snap), Undo 40-langkah, arah support + kotak saran, support manual dengan titik geser, tiang, besi per-bagian + warna/legenda. Konverter denah→members client-side → POST `/rangka-desain/hitung` (engine asli) → biaya real-time.

**TAHAP 1A (backend) SELESAI — 5 task, semua tes standalone hijau** (`php tests/rangka/test_{hitung,stok,stok_material,denah_blok,paduta}.php`). Nol risiko deploy (murni PHP, tak sentuh production):
- T1 `CuttingService::potong($pieces, $stock=null)` — stok potong per-panggilan (default 600).
- T2 `RangkaDesignService::hitung(..., array $stok=[])` — stok per-material (WF s/d 1200).
- T3 `CuttingController::stokMap()` — baca kolom `panjang_batang_cm` master_material (try/catch → `[]` bila kolom absen, aman).
- T4 cabang `tipe:'denah'` di `hitungSatuBlok` (members → RangkaDesignService, luas dari denah); jalur `kanopi`/`manual` TIDAK berubah.
- T5 reproduksi PA-DUTA: **5x10=10, 3x3=4, 4x6=4 REPRODUKSI PERSIS** dari cutting list asli (foto `/root/inbox`).

**2 utang 1A (butuh Elvan):**
1. **SQL belum dijalankan** (VPS ini tak ada DB) — jalankan di phpMyAdmin production: `ALTER TABLE master_material ADD COLUMN IF NOT EXISTS panjang_batang_cm INT NOT NULL DEFAULT 600;` lalu isi WF = 1200. Selama belum → stokMap() balik `[]`, semua default 600 (tak crash).
2. **PA-DUTA 4x8=9 belum ter-reproduksi** — bar #12 cutting list tak terekam di 2 screenshot (`inbox/*cutting list*kalibarasi*`). Potongan terlihat baru 8 batang. Butuh foto bar #12 buat menutup. (Catatan: temuan lama "support 14 vs 9" itu soal MODEL auto-layout = 1D, bukan engine cutting.)

**TAHAP 1B (DenahEditor + integrasi UI) SELESAI — merged ke `main`** (subagent-driven, review akhir opus = READY TO MERGE). Plan: `docs/superpowers/plans/2026-07-15-denah-rab-tahap1b-editor.md`; ledger: `.superpowers/sdd/progress.md`.
- `public/js/denah-editor.js` — modul classic-script (IIFE, `globalThis`, TANPA ESM export krn package `type:module`): `DenahConv` (geometri murni denah→members, tes `node tests/rangka/test_konverter.mjs`) + kelas `DenahEditor` (SVG editor per-blok, port dari prototype).
- `resources/views/rab-opsi/index.blade.php` — tipe blok baru **`denah`** (tombol `+ Blok Denah`), aditif; **kanopi/manual TAK berubah**. `bacaBlok` kirim `members`+`luas_m2`+`harga`+`denah`(model); `isiBlok`/`tambahBlok` rehidrasi dari `rab_snapshot`; `hapusBlok`/`hapusOpsi` panggil `DenahEditor.destroy()`.
- Biaya denah muncul saat klik **"Hitung Harga"** (via `bacaBlok`→`/rab-blok/hitung`→jalur `tipe:denah` 1A), bukan live — sama pola kanopi.

**BLOCKER 1C — SUDAH DIPERBAIKI (15 Juli, lokal):** `buildPenawaran()` kini punya cabang `tipe==='denah'` (ukuran=luas denah + frame/support/tiang default + atap, bentuk sama kanopi → dirender `penawaran/show.blade.php`). Sisa: verifikasi visual PDF saat deploy 1C.

Urutan besar sesudahnya: **1C = fix `buildPenawaran()` denah + jadikan denah default (opsional) + hapus `/rangka-desain` + VALIDASI di browser/DB nyata (drag UI, autosave→reload, "Hitung Harga" e2e, reproduksi PA-DUTA lewat UI) → deploy → 1D kalibrasi ulang support (target 9) + retune consumable/finishing pakai luas ~40 m²**.

**Status git:** `main` **SUDAH di-push & deploy (15 Juli)** — 1A+1B+fix buildPenawaran live ke production. (Sebelumnya ahead ~38 commit) 1A+1B semua sudah di production. Utang 1A: (1) **SELESAI PENUH (15 Juli)** — kolom `master_material.panjang_batang_cm` dibuat + baris WF di-set **1200** (dikonfirmasi Elvan). Stok per-material WF-1200 kini aktif di production DB. (2) foto bar #12 untuk tutup PA-DUTA 4x8=9 — **masih kurang** (satu-satunya sisa utang 1A).

**Catatan bug laten (di luar scope, buat nanti):** `CuttingService::potong` case-2 mint jid baru → sambungan bisa kurang di kasus ekstrem; `hitungRangka` auto-layout lama pakai intdiv/2 (boleh dipensiunkan setelah DenahEditor menggantikan penuh).

**16 Juli 2026 — Bug #8 (WF 12m) CLOSED, ternyata bukan bug:** Diverifikasi lewat log debug sementara (dihapus lagi setelah dipakai) — `stok_wf:1200` terbaca benar dari DB (`WF 200 12m` sudah pas namanya, dugaan lama "nama tak cocok" gugur). "2 batang" yang sempat dikira bug itu minimal matematis (support 700cm + 2 tiang 300cm = 1300cm > 1 batang 1200cm). Pelajaran baru: `LOG_LEVEL=error` di production nge-filter `Log::info()`, pakai `Log::error()` buat debug sementara.

**16 Juli 2026 — Fitur #9 Gabungan Kotak SELESAI dibangun (subagent-driven), di-push ke production:** Cara baru bikin bentuk campur di DenahEditor — "+ Tambah Kotak" nempel keluar (nambah) atau ke dalam (lekukan), arah otomatis dari posisi drag, 1 algoritma (`DenahConv.combineBox`). Brainstorm→spec→plan→implementasi lengkap: `docs/superpowers/specs/2026-07-16-gabungan-kotak-design.md`, `docs/superpowers/plans/2026-07-16-gabungan-kotak-implementation.md`. Final review opus (2 pass, READY TO MERGE) menangkap & memperbaiki 2 bug penting: fokus input hilang tiap ketik di panel span/menjorok (full `render()` menghancurkan `<input>`), dan divergensi validasi offset antara preview vs saat "Terapkan" (box yang keliatan pas malah ditolak). Sisi miring tetap manual (tak diubah). **Belum ada verifikasi drag/tap nyata di browser production** — checklist ada di plan Task 3.

**Gabungan Kotak DIKONFIRMASI ELVAN (16 Juli) — jalan normal di production** (tambah/lekukan/Terapkan/Undo/blok lain tak terganggu). #9 CLOSED.

**16-17 Juli 2026 — #10 Kelompok A (zoom+ukuran+ortho-support+ribbon+fullscreen) SELESAI & DIKONFIRMASI ELVAN, 10 iterasi deploy dalam 1 hari:**

6 permintaan update UI DenahEditor (16 Juli) dipecah 3 kelompok: **A** = zoom+ukuran+ortho-support (dikerjakan), **B** = drag-pindah-besi+snap-tengah (belum), **C** = saran-kotak-2-arah (belum). Detail lengkap ada di tabel #10 di atas.

Kelompok A dikerjakan via brainstorm→spec→plan→subagent-driven-development (fresh subagent per task + code review tiap task + final whole-branch review), lalu 9 iterasi PERBAIKAN lanjutan berdasarkan tes nyata Elvan di HP (5 foto dikirim via upload-inbox, sangat membantu diagnosis — beberapa bug baru ketemu akar pastinya setelah lihat foto, bukan dari deskripsi teks). Semua di `public/js/denah-editor.js`, plan: `docs/superpowers/plans/2026-07-16-denah-ui-kelompok-a-*.md` (implementation/fixes/fullscreen-mode), ledger lengkap tiap task: `.superpowers/sdd/progress.md`.

**Fitur/fix yang jadi (dikonfirmasi Elvan):**
- Ribbon 5-tab (Ukuran/Support/Besi/Mode/Ukur Sisi) — akhirnya jadi overlay melayang+sticky (2 iterasi gagal sebelumnya: push-down mendorong kanvas turun, lalu tombol Selesai numpuk panel ribbon)
- Pinch-zoom+pan+tombol Reset
- **Mode Layar Penuh** ("Perbesar Layar") — `this.el` di-reparent ke `document.body` selama aktif, LALU BALIK ke posisi asli saat "Selesai". Alasan: `position:fixed` RUSAK di Safari iOS kalau elemen bersarang di kontainer `overflow-y:auto`+`-webkit-overflow-scrolling:touch` (app pakai ini di `.page-content`, `layouts/app.blade.php`) — elemen ikut ke-scroll bareng kontainer, bukan nempel viewport beneran. **Pelajaran penting buat modal/overlay lain di masa depan: cek dulu apa bersarang di `.page-content` sebelum pakai `position:fixed` polos.**
- Ortho-snap support manual, plus bug "lurus pas drag, bengkok pas lepas jari" — snap-grid tanpa syarat di `pointerup` menggeser lagi sumbu yang barusan ortho-snap ke anchor non-kelipatan-grid (anchor sering presisi dari resize/"Ukur Sisi", bukan kelipatan grid). **Diperbaiki di 2 cabang drag terpisah** (`sup` lalu ketahuan lagi di `vert`/sudut poligon, pola bug identik) — **pelajaran: kalau perbaiki bug kelas ini, cek SEMUA cabang serupa sekaligus, jangan cuma yang dilaporkan duluan**
- `toCm()`: `getScreenCTM()` → `getBoundingClientRect()` (drag presisi saat zoom, getScreenCTM tak konsisten ngurai CSS transform leluhur di sebagian browser HP)
- Ukuran visual (titik sudut kecil, garis+label besar, konsisten ke titik handle support manual juga)
- Undo + **Redo** baru (redoStack dibersihkan di `pushUndo()` — satu titik terpusat, otomatis kepakai di semua ~13 lokasi mutasi)
- Popup "Ganti Besi": label nama batang (Frame F3/Support S5/Tiang T2 + panjang cm, nomor SAMA persis kayak di kanvas) + tombol Batal + clamp posisi biar tak kepotong tepi layar

**BELUM DIKERJAKAN (sengaja ditunda, daftar tunggu — bukan lupa):**
- Magnifier/offset-indicator biar jari tak nutupin garis pas drag presisi (zoom & support manual) — masalah UX umum, perlu didesain
- Input panjang diketik utk support manual (kayak "Ukur Sisi" tapi utk support) — Elvan sendiri usul "mungkin bagian selanjutnya"
- `.de-matmenu` (popup ganti-besi) SENDIRI masih bersarang di `.page-content` di mode non-fullscreen — berpotensi kena bug iOS Safari yang sama (lihat di atas) kalau discroll SELAGI popup terbuka. BELUM ada laporan nyata dari Elvan — jangan diperbaiki sebelum ada bukti, sesuai prinsip "jangan asumsi bug ada."

>>> RESUME POINT — 18 Juli: REDESAIN TOTAL Tambah Tiang (`0833447`) DIKONFIRMASI ELVAN BAGUS — tekan-tahan wajib + menu konfirmasi berhasil nutup 2 celah lama (tap-meleset, pinch-zoom nyasar) yang B-1..B-4 gagal tutup total. Tes yang sama nemu bug BARU: geser tiang lama susah "kena" + tekan-tahan nyasar buka menu Tambah Tiang. Root cause ketemu (`1c211fb`): threshold hit-test tiang pakai `this.SC` (skala auto-fit konten, TETAP) bukan skala TAMPIL aktual di layar (beda kalau HP nyusutin svg via CSS max-width:100% — di layar 360px toleransi sentuh nyata jadi ~12px, bukan ~24px yang dimaksud). Fix: `screenScale(el)` basis sama kayak `toCm()`. Dibuktikan reproduksi jsdom (kode lama gagal, kode baru lulus) + regresi 18+11 tes lama tetap hijau, sudah di-push. **LANJUT: tunggu Elvan tes ulang fix ini + Kelompok B di HP** (B-1..B-4 kemungkinan besar juga ketutup lewat fix yang sama, cek ulang jangan diasumsikan). Kalau ada laporan bug tiang baru lagi, JANGAN tambal cepat, minta video/reproduksi dulu. **Terpisah, belum diselidiki:** tombol "Lanjut → Finalisasi"/"+ Opsi" macet di HP Elvan (dilaporkan lagi 18 Juli, dulu dikonfirmasi tak terkait Kelompok B) — butuh video, jangan nebak. Setelah tiang beres: rancang pola sama (drag=pindah, tekan-tahan=menu) buat Support manual/otomatis + Frame, lalu lanjut Kelompok C (saran-kotak-2-arah). Utang lama opsional tak mendesak: foto bar #12 cutting list PA-DUTA (tutup validasi 4x8=9). <<<

**5 Agustus 2026 — Migrasi notifikasi WA (Fonnte, banned) → Telegram karyawan, Task 1-10 SELESAI (subagent-driven-development), sudah di-merge lokal ke `main` (BELUM di-push).**

Plan: `docs/superpowers/plans/2026-08-05-notifikasi-telegram-karyawan.md` (spec: `docs/superpowers/specs/2026-08-05-notifikasi-telegram-karyawan-design.md`). 12 task total, dikerjakan lewat subagent-driven-development (fresh implementer + review per task, review akhir whole-branch pakai opus).

**Yang jadi:**
- `App\Services\TelegramService::kirim(?string $chatId, string $pesan): bool` — satu jalur kirim kanonik, pure PHP, testable tanpa DB (`tests/telegram/test_telegram_service.php`). Token dari `.env` (`TELEGRAM_KARYAWAN_TOKEN`), bukan hardcode.
- `TelegramWebhookController` — webhook publik (`POST /telegram/karyawan/webhook`, dikecualikan CSRF) yang terima `/start <token>` dari Telegram, simpan `chat_id` ke `users.telegram_chat_id`.
- Tombol "Hubungkan Telegram" di halaman Profil (deep-link `t.me/<bot>?start=<token>`).
- 9 controller + 3 file cron dimigrasi dari `kirimWA()`/Fonnte ke `TelegramService` — semua `kirimWA()`/`FonnteService` lama DIHAPUS TOTAL (bukan cuma diganti pemanggilannya). Konfirmasi lewat grep seluruh repo: nol sisa referensi Fonnte/kirimWA di luar 12 file yang disentuh.
- **Bug tersembunyi ikut ketemu & diperbaiki di sepanjang jalan:** (1) `FonnteService` ternyata TIDAK PERNAH ADA sebagai file — `KpiController`/`cron-kpi.php` motret class itu, artinya jalur SP-notif & hasil-ujian selama ini fatal error kalau kepanggil (sekarang fixed, Task 10). (2) Pola bug berulang: method notif sudah dimigrasi ke Telegram DI DALAMNYA, tapi PEMANGGIL LUAR-nya masih nge-gate pakai `if ($x->no_hp)` — jadi karyawan yang udah connect Telegram tapi belum isi no HP tetap gak dapat notif. Ketemu & di-fix di `LuarKotaController` (Task 6) dan `TugasHarianController` (Task 7), sudah dicek nggak ada di controller lain.
- Review akhir (whole-branch, model opus) nemu 2 gap Important yang langsung diperbaiki (commit `81951c6`): `parse_mode=Markdown` bikin pesan GAGAL TERKIRIM DIAM-DIAM kalau teks bebas (nama customer, alasan izin, dll) mengandung karakter `_`/`*`/`` ` ``/`[` tak berpasangan (Telegram nolak API call-nya) — sekarang retry sekali tanpa Markdown kalau kena. Plus `TelegramService` sebelumnya nol logging — sekarang `Log::error()` kalau token kosong atau API nolak, biar kegagalan kirim kelihatan, bukan senyap kayak insiden Fonnte kemarin.
- `RabController::kirimNotifDeal()` (notif WA ke customer) SENGAJA dinonaktifkan (early `return`), BUKAN dipindah — customer bukan `User` sistem, gak bisa connect Telegram. Nunggu WhatsApp Business API resmi (roadmap #5 — "Telegram jangan dipaksakan ke karyawan/customer" sekarang jadi pengecualian sengaja untuk karyawan, khusus customer tetap berlaku).

**⚠️ BELUM di-push ke GitHub — JANGAN push sebelum SQL ini jalan di phpMyAdmin production (Elvan konfirmasi 5 Agustus: belum dijalankan):**
```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS telegram_chat_id VARCHAR(50) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS telegram_link_token VARCHAR(64) NULL;
```
Kalau push duluan sebelum SQL jalan: auto-deploy jalan otomatis, `/profil` 500 buat semua 14 karyawan (nulis ke kolom yang belum ada), plus izin/kendala/2 cron ikut error sampai SQL dijalankan.

**Utang lain (dicatat review akhir, TIDAK diperbaiki di branch ini — di luar scope/pre-existing, bukan disembunyikan):**
- `public/cron-kpi.php` sebenarnya DEAD CODE dari sebelum migrasi ini — motret `bootstrap/autoload.php` yang sudah tak ada sejak Laravel 5.5+ (bug pre-existing, bukan diperkenalkan sesi ini). Notif KPI bulanan lewat Telegram = phantom sampai ini diperbaiki terpisah.
- ~~Absen kode harian tanpa fallback in-app~~ — SUDAH DIPERBAIKI, lihat entri "Kode Absen Per-Karyawan" di bawah (5 Agustus, sesi kedua).
- Token Fonnte lama masih ada di histori git (repo public) — akun sudah banned jadi risiko rendah, tapi sebaiknya di-revoke juga di dashboard Fonnte.
- Token bot Owner (approval RAB) di `ApprovalController.php` MASIH hardcode di kode — ini Task 12 (independen, terpisah dari migrasi karyawan ini, tunggu Elvan revoke token lama dulu via BotFather).

**Task 11 progress (5 Agustus) — SQL sudah jalan, sudah push+deploy, bot dibuat, webhook terdaftar, tes koneksi + tes kirim (kode absen) lewat Owner sendiri SUKSES.** Sisa: umumkan ke 14 karyawan buat klik "Hubungkan Telegram" di halaman Profil masing-masing (Step 8, belum dilakukan — nunggu rollout kode-absen-per-karyawan di bawah kelar dulu, biar sosialisasi digabung sekali jalan, bukan dua kali).

**Task 12 (independen, belum dikerjakan):** pindah token Owner dari hardcode di `ApprovalController.php` ke `.env` — tunggu Elvan revoke token lama dulu via BotFather.

---

**5 Agustus 2026 (sesi kedua) — Kode Absen Per-Karyawan SELESAI (subagent-driven-development), sudah di-merge lokal ke `main` (BELUM di-push).**

Lanjutan langsung dari migrasi Telegram di atas — begitu Elvan tes kirim kode absen lewat akun sendiri, langsung kepikiran: kode absen selama ini **satu kode buat SEMUA karyawan per hari** (siapapun yang tahu kode bisa dipakai absen atas nama siapapun — celah titip-absen). Brainstorm→spec→plan→implementasi (4 task) di sesi yang sama.

Spec: `docs/superpowers/specs/2026-08-05-kode-absen-per-karyawan-design.md`. Plan: `docs/superpowers/plans/2026-08-05-kode-absen-per-karyawan.md`.

**Yang jadi:**
- Tabel `kode_absen` dapat kolom `user_id` — sekarang 1 kode = 1 karyawan = 1 hari (bukan 1 kode buat semua).
- Cron `cron-kode-absen.php` generate + kirim kode PERSONAL per karyawan aktif (idempotent, aman di-trigger ulang). Karyawan yang belum connect Telegram tetap dapat kode di DB (buat fallback dashboard), cuma gak ada pesan terkirim.
- **Perbaikan keamanan intinya** (Task 3): `AbsensiController::absenMasuk()` & `validasiKode()` sekarang cek kode HARUS milik user yang login (`where('user_id', $user->id)`), bukan cuma "kode valid hari ini". Kode si A gak bisa dipakai si B walau B tahu kodenya. **Diverifikasi khusus oleh review akhir** (dilacak dari generate di cron sampai validasi di controller, digrep seluruh repo — nol jalur lain yang kelewat).
- Halaman baru `/absensi/kode-hari-ini` (Owner + Supervisor/Mandor) — tabel kode semua karyawan hari ini + status connect Telegram, buat direlay manual ke yang belum connect.

**Bug ikut ketemu & diperbaiki lewat review akhir (bukan cuma "ready to merge" langsung):**
- Owner (level 1) ternyata jadi TERKUNCI PERMANEN dari fitur absen masuk (cron sengaja skip level 1) — dicek ke Elvan langsung: **Owner memang gak pernah absen masuk**, jadi tombol "Absen Masuk" disembunyikan khusus buat level 1 (bukan diikutkan ke cron).
- Supervisor (level 3) punya akses ke halaman `/absensi/kode-hari-ini` tapi TANPA link menu buat nemuinnya (plan sendiri yang kelewat, cuma nambah link di sidebar Owner) — sudah ditambah.
- Layar tempat karyawan ngetik kode (`form-masuk.blade.php`) masih nyebut "WhatsApp" di 5 tempat — sisa sebelum migrasi Telegram, ketinggalan, bikin instruksi ke karyawan salah arah (WA sudah mati). Sudah diganti semua ke "Telegram".
- Dashboard fallback tadinya cuma baca doang — karyawan baru/reaktivasi setelah jam 06:30 gak kebagian kode dan dashboard gak bisa nolong. Sekarang self-healing (generate on-the-fly kalau belum ada).
- Cron loop dikasih try/catch — 1 karyawan error DB gak lagi bikin semua yang setelahnya ikut gagal.
- 1 fix kecil tambahan dari scoped re-review: perbandingan level pakai `===` (strict) diganti `==` — kolom `level` gak ada cast di model, isu potensial silent-fail yang bisa balikin lagi bug "Owner lihat tombol rusak".

**⚠️ BELUM di-push — sebelum push, jalankan SQL ini dulu di phpMyAdmin production:**
```sql
ALTER TABLE kode_absen ADD COLUMN IF NOT EXISTS user_id BIGINT UNSIGNED NULL;
ALTER TABLE kode_absen ADD UNIQUE INDEX IF NOT EXISTS kode_absen_tanggal_user_unique (tanggal, user_id);
```
(Kalau baris index ditolak karena versi MySQL <8.0.29, jalankan tanpa `IF NOT EXISTS` di baris itu saja — detail di spec.)

**PENTING, beda dari migrasi Telegram kemarin:** setelah SQL jalan, **cron HARUS di-trigger manual di hari yang sama juga** (`https://app.kanopibsd.co.id/cron-kode-absen.php?key=canopi_cron_2026`) — karyawan yang sudah ada belum otomatis punya kode `user_id` sampai cron pagi berikutnya. Kalau push/SQL dijalankan siang hari, semua karyawan tetap terkunci sampai besok pagi kalau cron gak dipicu manual.

**STATUS 5 Agustus (akhir hari) — SELESAI PENUH & LIVE:** SQL kode_absen sudah jalan, sudah push+deploy, cron manual sudah di-trigger hari itu juga, kode absen per-karyawan aktif di production. Elvan sudah kabari ke SEMUA 14 karyawan buat klik "Hubungkan Telegram" di Profil. **Task 11 (setup bot + rollout karyawan) CLOSED.**

**Belum diverifikasi eksplisit (bukan blocker, sekadar catatan buat sesi depan kalau ada laporan):** cron jam 06:30 WIB besok jalan otomatis via **cronjob.org eksternal** (bukan cPanel Cron Jobs — lihat memory `cron-scheduling-cronjob-org.md`), belum ada konfirmasi langsung dari Elvan bahwa kode absen personal beneran masuk pagi itu. Kalau ada laporan "kode gak masuk", cek dashboard cronjob.org dulu (bukan cPanel), baru cek isi `kode_absen` tabel/log Laravel.

**Task 12 SELESAI (5 Agustus, sore) — token bot Owner sudah dirotasi.** Elvan revoke token lama via BotFather, token baru diisi ke `.env` server (`TELEGRAM_OWNER_TOKEN`, `TELEGRAM_OWNER_CHAT_ID=8385647457`), `ApprovalController.php::kirimTelegram()` diubah dari hardcode ke `getenv()` — commit `b5dbefd`, sudah push+deploy, **diverifikasi Elvan langsung**: trigger approval RAB, notif Telegram tetap masuk pakai token baru. Token lama yang sempat ke-expose di histori commit publik sudah tidak valid lagi (di-revoke).

**>>> SEMUA 12 TASK DARI KEDUA MIGRASI (Fonnte→Telegram + Kode Absen Per-Karyawan) SELESAI & LIVE per 5 Agustus 2026. Tidak ada task nyisa dari sesi ini. <<<**

**Sisa kerjaan (di luar scope kedua migrasi ini, dicatat biar gak lupa, bukan urgent):**
- `public/cron-kpi.php` masih dead code (bug pre-existing, `bootstrap/autoload.php` tak ada sejak Laravel 5.5+) — notif KPI bulanan lewat Telegram belum jalan sampai ini diperbaiki terpisah.
- Token Fonnte lama masih ada di histori git — akun sudah banned, risiko rendah, tapi belum di-revoke di dashboard Fonnte.

---

**6 Agustus 2026 — Sesi debug notif Telegram: 3 bug ketemu & 2 sudah di-fix, 1 masih menggantung (403 cron via cron-job.org).**

1. **Notif izin ke Owner gak masuk — BUKAN bug, sudah beres.** Akar masalah: akun Elvan sendiri (level 1) belum pernah klik "Hubungkan Telegram" di Profil-nya sendiri (beda bot dari bot approval-RAB yang dipakai sebelumnya). Setelah connect manual, beres.

2. **FIX (live, commit `bf569e8`):** `public/cron-kode-absen.php` dulu ngirim kode absen ke SEMUA karyawan aktif tanpa cek status izin — karyawan yang izin/sakit/cuti hari itu tetap kebagian kode. Sekarang exclude user yang ada row `absensi` hari itu dengan status `sakit`/`izin`/`cuti`/`dinas_luar`.

3. **FIX (live, commit `e69a097`):** `cron-kode-absen.php` ternyata TIDAK idempotent buat pengiriman — walau kode dipakai ulang (gak generate baru), script tetap kirim ULANG pesan Telegram ke semua karyawan connect tiap kali berhasil jalan. Ketauan pas kejadian nyata: karyawan lapor terima kode 4x (04:00, 04:33, 05:10, 06:47) dalam 1 pagi — kombinasi klik gak sengaja + kemungkinan retry cron-job.org + job manual susulan. Sekarang begitu ketemu kode existing → langsung `continue`, gak lanjut kirim.

4. **BELUM SELESAI — `cron-kode-absen.php` sering 403 kalau diakses persis di jam bulat** (06:30:08, 13:00:13, 20:00:15 gagal; 06:47 dua hari berturut-turut sukses). Diagnosa dengan 2 file tes kembar (`cron-test-403.php` tanpa DB, `cron-test-403c.php` Laravel+DB tanpa Telegram — sudah dihapus lagi, commit `7982d37`) membuktikan ini BUKAN soal `.htaccess`/isi script/parsing key (file kembar sukses di jam yang sama saat file asli 403). Dugaan kuat: proteksi anti-bot di hosting (Niagahoster jalan di infra Hostinger/LiteSpeed) kena trigger pas jam bulat rame trafik cron dari banyak pengguna cron-job.org sekaligus — tapi ini **belum dikonfirmasi lewat log server** (gak ada akses cPanel/WAF dari Claude Code, VPS ini beda mesin dari hosting). Sumber kiriman jam 04:00 & 04:33 juga masih misteri — dicek TERBUKTI bukan dari Claude Code (commit pertama sesi ini baru jam 05:07 WIB, dan deploy toh cuma FTP sync, gak pernah trigger endpoint apapun).

**TODO sesi depan:** Elvan cek dashboard cron-job.org — (a) SEMUA job yang ngarah ke `cron-kode-absen.php` (curiga ada job lama/dobel yang kelupaan, itu bisa jelasin kiriman 04:00 & 04:33), (b) response detail (header/body) dari eksekusi yang 403 buat lihat itu block dari layer mana. Kalau gak ketemu akar pastinya, solusi praktis (belum diterapkan): geser jadwal kirim dari jam bulat (06:30) ke jam ganjil (mis. 06:23) — sudah kebukti kerja 2x di jam 06:47.

---

**11 Agustus 2026 — Fitur baru "Jadwal Libur Per-Karyawan" SELESAI & LIVE (subagent-driven-development, 7 task + final review + 1 fix round).**

Dipicu pertanyaan simpel ("karyawan libur tetap dapat kode absen?"), investigasi nemuin masalah lebih serius: `cron-alpha.php` juga gak kenal jadwal libur — karyawan yang punya hari libur tetap tapi gak pernah ngajuin izin buat itu (dikonfirmasi Elvan: memang gak pernah) bisa ke-tandain **Alpha**, yang motong gaji hari itu DAN langsung jatuhin kelas KPI sebulan ke "none". Plus `GajiService::hitungHariKerja()` ngitung hari kerja SERAGAM (semua hari kecuali Minggu) buat semua karyawan, bias buat siapapun yang liburnya bukan hari Minggu.

Spec: `docs/superpowers/specs/2026-08-11-jadwal-libur-karyawan-design.md`. Plan (7 task): `docs/superpowers/plans/2026-08-11-jadwal-libur-karyawan.md`.

**Yang jadi:**
- `users.hari_libur_default` (nullable, 0=Minggu..6=Sabtu) — jadwal libur tetap per-karyawan, diisi Owner lewat form edit/tambah karyawan.
- Tabel `jadwal_libur` — ajuan tukar/skip/tambah libur per-tanggal, alur approval mirip izin (Owner/Mandor approve/tolak, notif Telegram dua arah). Halaman karyawan: `/jadwal-libur` (riwayat) + `/jadwal-libur/ajukan`. Halaman Owner/Mandor: `/jadwal-libur/approval` (link ditaruh di KEDUA sidebar — Owner dan Pipeline/level 3 — biar gak ulang gap lama kayak `kode-hari-ini` yang sempat cuma ada di sidebar Owner).
- `App\Services\LiburService::isLibur()` — satu sumber kebenaran tunggal (override approved menang, fallback ke default), dipakai di 3 titik yang tadinya nganggep semua karyawan kerja tiap hari: `cron-alpha.php` (skip Alpha), `cron-kode-absen.php` (skip kirim kode), `GajiService::hitungHariKerja()` (hari kerja per-karyawan, bukan seragam).
- Logic murni (`cocokLiburPada`/`hitungHariKerjaPada`) dipisah dari wrapper database, testable tanpa DB — `tests/jadwal-libur/test_libur_service.php`, 13/13 lulus.

**Ketemu & dibenerin lewat final whole-branch review (opus), sebelum sampai production:**
- **Kritis:** SQL deploy awal LUPA backfill — tanpa `UPDATE users SET hari_libur_default = 0 WHERE ... IS NULL`, semua 14 karyawan mulai dengan NULL ("gak ada libur"), diam-diam nganggep mereka kerja 31 hari (bukan 26) bulan ini, nurunin persen kehadiran & kelas KPI semua orang di slip gaji berikutnya. Baris `UPDATE` sudah ditambahkan ke SQL final SEBELUM dijalankan Elvan — **dikonfirmasi tidak ada regresi**.
- **Penting:** `ProfilController` (halaman profil karyawan sendiri) ternyata punya salinan lama logic "kerja tiap hari kecuali Minggu" yang gak kesentuh 7 task awal (konsumer ke-4 yang gak ketauan sampai review whole-branch) — sekarang ikut lewat `LiburService` (nambah parameter opsional `$sampaiHari` buat varian "bulan-berjalan").
- **Penting:** karyawan yang mendadak kerja di hari liburnya (gak direncanain) gak ada kode absen nunggu — ditambah indikator "🗓️ Libur" di halaman `/absensi/kode-hari-ini` (Owner/Mandor) biar keliatan itu memang libur, bukan error, dan bisa direlay manual kalau perlu.
- 2 minor ikut dibenerin: karyawan baru sekarang bisa langsung diisi jadwal libur pas dibuat (dulu cuma bisa lewat Edit), dan halaman detail karyawan (`show.blade.php`) sekarang nampilin jadwal libur default-nya.

**⚠️ SQL sudah dijalankan Elvan (11 Agustus) — konfirmasi: tanpa error.** Sudah push + deploy (`commit 8b9ba8e`).

**BELUM diverifikasi (checklist sesi depan kalau ada laporan aneh):**
- Set `hari_libur_default` buat minimal 1-2 karyawan lewat form Edit, cek tersimpan.
- Ajuan jadwal libur dari sisi karyawan → notif Telegram ke Owner/Mandor → approve/tolak → notif balik ke karyawan.
- Karyawan yang sudah di-set libur: cek `cron-alpha.php` gak nandain dia Alpha di hari itu, dan `cron-kode-absen.php` gak kirim kode ke dia.
- Slip gaji bulan berikutnya: `hari_kerja` beda per-karyawan sesuai jadwal libur masing-masing (bandingkan minimal 2 karyawan beda jadwal).
- Halaman `/profil` karyawan sendiri: persen kehadiran & kelas KPI konsisten sama yang di slip gaji Owner (dulu dua sumber logic beda, sekarang seharusnya satu).

**Dicatat, bukan diperbaiki (di luar cakupan sesi ini):**
- `JadwalLiburController::approve()`/`reject()` gak ada guard "udah diproses" (bisa double-notif kalau di-klik 2x/tab basi) — sengaja dibiarin karena `IzinAbsenController` punya gap yang sama persis, mending konsisten dua-duanya belum diperbaiki daripada beda perlakuan.
- `LiburService::hitungHariKerja()` ada inkonsistensi kecil gaya kode (`?:` vs `??` buat parameter `$sampaiHari`) — gak kepakai sama caller manapun sekarang (cuma dipanggil dengan nilai ≥1), jadi gak berbahaya, tapi dicatat buat siapapun yang nambah caller baru nanti.

---

**11 Agustus 2026 (sesi kedua) — Fitur "Jam Masuk/Pulang Per-Karyawan" SELESAI & di-push (subagent-driven-development, 6 task + final whole-branch review + 1 fix round), kerja langsung di `main` (pilihan Elvan, bukan worktree).**

Lanjutan dari brainstorm yang sempat kepotong sesi sebelumnya (konteks tinggi) — keputusan sudah dikunci waktu itu, sesi ini re-verifikasi kode masih akurat lalu langsung tulis spec+plan+eksekusi. Ganti telat/lembur dari konstanta hardcode (`JAM_MASUK`/`JAM_LEMBUR`, sama buat semua karyawan) ke kolom `users.jam_masuk`/`jam_pulang` per-karyawan yang sudah ada di DB tapi selama ini dekoratif. Spec: `docs/superpowers/specs/2026-08-11-jam-masuk-pulang-per-karyawan-design.md`. Plan: `docs/superpowers/plans/2026-08-11-jam-masuk-pulang-per-karyawan.md`.

**Yang jadi:**
- `AbsensiController::absenMasuk()`/`absenPulang()` — telat & lembur baca `$user->jam_masuk`/`jam_pulang`, bukan konstanta seragam.
- Gate `JAM_BUKA_ABSEN` (06:30) dikecualikan buat karyawan mode luar kota aktif (`LuarKota::sedangLuarKota()`) — bisa absen masuk lebih pagi (mis. berangkat jam 3-4 pagi).
- `JAM_SETENGAH` (10:00) dan absen siang (13:00-14:00) TETAP seragam, sengaja tidak ikut geser per keputusan brainstorm.
- Validasi `date_format:H:i` ditambah ke field `jam_masuk`/`jam_pulang` di form Karyawan (`KaryawanController::store()`/`update()`).
- Konstanta mati `JAM_MASUK`/`JAM_LEMBUR`/`JAM_PULANG` dihapus dari `AbsensiController`.
- Test standalone (pola sama `tests/jadwal-libur/*.php`): `tests/absensi/test_gate_buka_absen.php` (5/5 PASS) + `tests/absensi/test_jam_individu.php` (7/7 PASS, verifikasi nol-regresi format `H:i` vs `H:i:s`).

**Ketemu & dibenerin lewat final whole-branch review (opus), sebelum push:**
- **Penting:** `resources/views/karyawan/edit.blade.php` render nilai mentah `H:i:s` ke `<input type="time">` — begitu Task 5 nambah `date_format:H:i`, form Edit Karyawan bisa gagal simpan (termasuk field lain yang gak terkait) kalau browser submit `07:00:00` bukan `07:00`. Di-fix `substr(...,0,5)`, mengikuti pola yang sudah ada di `absensi/rekap.blade.php`.
- **Penting:** default `jam_masuk` di form tambah karyawan baru masih `07:30` — beda dari standar backfill `07:00` yang dikunci di keputusan brainstorm. Karyawan baru pasca-deploy bisa diam-diam dapat ambang telat beda 30 menit dari yang lain. Di-fix ke `07:00`.
- 2 minor ikut dibenerin: variabel `$jamLemburMax` mati (di-compact ke view yang gak pernah bacanya) dihapus; `tests/absensi/test_jam_individu.php` yang diminta spec tapi kelewat waktu eksekusi 6 task, ditambahkan di ronde fix akhir.

**Dicatat, bukan diperbaiki (temuan reviewer di luar cakupan implementasi, buat jadi perhatian Elvan bukan bug kode):**
- Kalau `jam_masuk` seorang karyawan di-set LEBIH PAGI dari 06:30 (gate `JAM_BUKA_ABSEN` yang tetap seragam), dia otomatis telat tiap hari tanpa pesan error yang jelas kenapa — kombinasi 2 keputusan brainstorm yang gak saling ketemu. **Kalau mau set jam masuk karyawan, jangan di bawah 06:30.**
- Gate `JAM_BUKA_ABSEN` cuma dicek di `formMasuk()` (tampilan), endpoint POST `absenMasuk()` gak pernah menegakkannya — pre-existing dari sebelum fitur ini, bukan regresi baru.

**⚠️ SQL backfill WAJIB dijalankan sebelum kode ini aktif dipakai** (dikonfirmasi Elvan sudah/akan dijalankan bareng push 11 Agustus):
```sql
UPDATE users SET jam_masuk = '07:00:00', jam_pulang = '17:00:00'
WHERE status = 'aktif';
```
Tanpa ini: karyawan yang belum di-backfill tetap pakai default lama dari migrasi (`07:30`/`17:00`) — bukan crash, cuma diam-diam beda ambang telat 30 menit dari standar armada sampai di-backfill manual.

**Status git:** push ke `main` sukses (commit `0153a2d`, 8 commit total sesi ini) — auto-deploy GitHub Actions jalan otomatis ±1-2 menit.

**BELUM diverifikasi (checklist sesi depan kalau ada laporan aneh):**
- Set `jam_masuk` custom (mis. 08:00) buat 1 karyawan, absen jam 07:30 → harus `hadir` bukan `telat`.
- Karyawan yang jamnya TIDAK di-custom → tetap `telat` di 07:30 seperti sebelumnya (nol-regresi).
- Karyawan mode luar kota aktif → absen masuk jam 05:00 lolos (dulu diblokir sampai 06:30).
- Edit karyawan TANPA ubah field jam → simpan berhasil (ini regresi yang sempat ketemu & sudah di-fix, tapi belum dites nyata di production).
- Lembur dengan `jam_pulang` custom → mulai dihitung dari jam custom, bukan 17:00.

---

**12 Agustus 2026 — Fitur "Gabungkan Ajuan Jadwal Libur Jadi 1 Form (Tukar/Skip/Tambah)" SELESAI & di-push (subagent-driven-development, 5 task + final whole-branch review + 1 fix round).**

Dipicu Elvan minta 1 form buat tukar/skip/tambah libur (bukan 2 ajuan terpisah kayak sebelumnya). Brainstorm penuh (banyak klarifikasi kasus nyata: tukar maju, tukar mundur lintas minggu, jendela 2-minggu Senin-Minggu). Spec: `docs/superpowers/specs/2026-08-11-tukar-libur-1-form-design.md`. Plan: `docs/superpowers/plans/2026-08-11-tukar-libur-1-form.md`.

**Yang jadi:**
- 3 jenis ajuan eksplisit di 1 form: **Tukar** (geser 1 hari libur, tanggal lama+baru dalam 1 baris data, approve/tolak otomatis atomik), **Skip** (dulu "Batal", cuma relabel — value DB tetap `batal`), **Tambah** (gak berubah). Tabel `jadwal_libur` dapat kolom `tanggal_baru` (nullable) + enum `jenis` dapat nilai `tukar`.
- `LiburService::cocokLiburPada()`/`hitungHariKerjaPada()` (logic inti, teruji dari fitur sebelumnya) **TIDAK disentuh sama sekali** — baris `tukar` di-expand jadi 2 entry override sintetis (`batal`+`tambah`) lewat method pure baru `expandTukar()`, jadi 4 titik konsumen (`cron-alpha.php`, `cron-kode-absen.php`, `GajiService`, `ProfilController`) otomatis dapat dukungan Tukar tanpa disentuh.
- Tanggal Tukar/Skip dibatasi sistem (bukan cek manual Elvan lagi): harus beneran hari libur default karyawan, dalam jendela sisa-minggu-ini+minggu-depan (Senin-Minggu). Tambah tetap bebas (H+1 aja) — karyawan sering ajukan jauh-jauh hari buat acara.
- Form `create.blade.php` didesain ulang total: 3 kartu jenis, field tanggal berubah dinamis via JS (pola `disabled` toggle biar cuma jenis aktif yang ke-submit).
- Test standalone `tests/jadwal-libur/test_libur_service.php` bertambah dari 13 jadi 25 assertion (expandTukar, jendelaTukarSkip, tanggalKandidatLibur) — semua hijau.

**Ketemu & dibenerin lewat final whole-branch review (opus), sebelum push:**
- **Penting:** 2 perbandingan `dayOfWeek !==`/`===` ke `$user->hari_libur_default` (kolom `unsignedTinyInteger` TANPA cast) di `JadwalLiburController::store()` — persis kelas bug yang sama kayak `level` yang dibenerin di sesi jadwal-libur sebelumnya. Satu sisi gagal TERTUTUP (Skip/Tukar selalu ditolak), sisi lain gagal TERBUKA (gerbang "tanggal pengganti harus hari kerja" gak pernah nyala). Di-fix `!=`/`==` (loose), ikutin preseden yang sudah ada di repo ini, BUKAN nambah cast baru.
- Minor: pesan sukses di `approve()` kelewat pakai `labelTanggal()`, jadi approval Tukar cuma nampilin tanggal lama — di-fix.
- Reviewer verifikasi manual kasus tricky (dites hitung tangan + jalanin test beneran): swap lintas bulan (Juli→Agustus) gak nyasar/kehilang di query `hitungHariKerjaPada()`, dan jaminan "approve/tolak atomik" di `approve()`/`reject()` beneran gak ada jalur update-sebagian.

**Dicatat, bukan diperbaiki (parkir di ledger, low-impact, disetujui gak masuk scope ini):**
- `labelTanggal()` bisa null-deref kalau baris `tukar` punya `tanggal_baru` NULL — gak bisa kejadian lewat `store()` (satu-satunya penulis), cuma resiko kalau ada yang edit DB manual.
- Select tanggal Skip/Tukar belum ada atribut `required` — submit kosong nampilin pesan error bahasa Inggris Laravel, bukan native browser prompt.
- Kalau Elvan ubah `hari_libur_default` seorang karyawan SETELAH ajuan Tukar/Skip di-approve (dalam jendela 2 minggu yang sama) — karyawan itu bisa "untung" (dapat hari pengganti tanpa beneran kehilangan hari lama). Jarang kejadian (14 karyawan), gak dirugikan siapapun, gak diperbaiki di v1.

**⚠️ SQL WAJIB dijalankan sebelum kode ini aktif** (dikonfirmasi Elvan sudah jalan 12 Agustus, sempat kena error "No database selected" — solusinya klik nama database dulu di sidebar phpMyAdmin sebelum buka tab SQL):
```sql
ALTER TABLE jadwal_libur MODIFY COLUMN jenis ENUM('tambah','batal','tukar') NOT NULL;
ALTER TABLE jadwal_libur ADD COLUMN IF NOT EXISTS tanggal_baru DATE NULL AFTER tanggal;
```
Tanpa ini: `tanggal_baru` gak ada di DB → `LiburService::ambilOverride()` query-nya gagal → 500 serentak di `/profil` (semua karyawan), `/absensi/kode-hari-ini`, generate slip gaji, DAN 2 cron pagi (karyawan gak dapat kode absen).

**Status git:** push ke `main` sukses (commit `0e15657`) — auto-deploy GitHub Actions jalan otomatis ±1-2 menit.

**BELUM diverifikasi (checklist sesi depan kalau ada laporan aneh):**
- Karyawan tanpa `hari_libur_default` → form cuma nampilin opsi Tambah.
- Ajukan Tukar → dropdown "Tanggal Lama" cuma nampilin hari libur default beneran dalam 2 minggu; "Tanggal Baru" gak bisa pilih di luar jendela atau di hari libur default.
- Ajukan Tukar → approve → cek kode absen: tanggal lama DAPAT kode (dianggap kerja), tanggal baru TIDAK dapat kode (dianggap libur).
- Ajukan Skip → approve → `cron-alpha.php` gak nandain Alpha di tanggal itu meski gak absen (dianggap kerja beneran, harus absen).
- Riwayat & approval nampilin 2 tanggal ("dari → ke") buat Tukar.
- Notif Telegram (ajuan masuk & hasil) buat Tukar nampilin 2 tanggal dengan benar.
- Coba ajukan tanggal yang bentrok sama ajuan lain (pending/approved) → ditolak.

---

**12 Agustus 2026 (sesi ketiga) — Fitur "Validasi Silang Izin ↔ Jadwal Libur" SELESAI & di-push (subagent-driven-development, 2 task + final whole-branch review + 1 follow-up fix).**

Dipicu pertanyaan Elvan soal beda Izin/Sakit/Cuti vs Tambah Libur — ketauan dua sistem itu gak saling ngecek sama sekali, karyawan bisa dapat ajuan approved di keduanya buat tanggal yang sama. Spec: `docs/superpowers/specs/2026-08-12-validasi-silang-izin-libur-design.md`. Plan: `docs/superpowers/plans/2026-08-12-validasi-silang-izin-libur.md`.

**Yang jadi:**
- `IzinAbsenController::store()` sekarang cek `JadwalLibur` (tanggal ATAU tanggal_baru) sebelum simpan izin baru.
- `JadwalLiburController::store()` sekarang cek `IzinAbsen` sebelum simpan ajuan Tukar/Skip/Tambah baru — buat Tukar, dua-duanya (tanggal lama & baru) dicek.
- Scope per-karyawan (2 karyawan beda boleh bentrok tanggal), status `pending`/`approved` yang dianggap aktif (`rejected` gak menghalangi).
- `dinasLuar()` (dicatat langsung Owner/Mandor) SENGAJA dikecualikan dari validasi ini — keputusan sadar Owner, bukan celah yang perlu dicegah sistem.

**Ketemu & dibenerin lewat final whole-branch review (opus) + 1 follow-up fix (dikonfirmasi Elvan, bukan diputuskan sepihak):**
- **Bug lama ikut kebenerin sekalian** (bukan dari fitur ini, tapi jadi lebih kerasa dampaknya): `approve()`/`reject()` di KEDUA controller gak ada penjaga "udah diproses" — tab browser basi bisa approve ulang/approve ajuan yang udah ditolak, yang bisa bikin validasi silang baru ini balik jebol. Ini bug yang sama yang sengaja ditunda pas sesi jadwal-libur sebelumnya (biar dua controller konsisten sama-sama belum dibenerin) — sekarang dibenerin BARENG di keduanya sekaligus (4 fungsi: `IzinAbsenController::approve()`/`reject()`, `JadwalLiburController::approve()`/`reject()`), sekalian nutup bug notif dobel yang juga udah lama ada.
- **Ketemu efek samping gak sengaja:** query validasi silang di `JadwalLiburController` ternyata otomatis ikut ngeblokir Dinas Luar juga (bukan cuma Izin/Sakit/Cuti) — dikonfirmasi ke Elvan, TERNYATA itu perilaku yang diinginkan (karyawan dinas luar emang gak boleh dapat libur ekstra di tanggal sama), cuma pesan errornya dibenerin biar nyebut "dinas luar" juga.

**Status git:** push ke `main` sukses (commit `89ef840`) — auto-deploy GitHub Actions jalan otomatis ±1-2 menit. **Tidak ada SQL production yang perlu dijalankan** buat fitur ini (murni kode, gak ada tabel/kolom baru).

**BELUM diverifikasi (checklist sesi depan kalau ada laporan aneh):**
- Karyawan A ajuin Cuti tanggal X (pending) → coba ajuin Tukar Libur tanggal X → harus ditolak.
- Karyawan A ajuin Izin tanggal X, Karyawan B (beda orang) ajuin Tukar Libur tanggal X → harus TETAP BISA.
- Owner catat Dinas Luar di tanggal yang karyawan itu punya ajuan Jadwal Libur aktif → harus TETAP BISA (Dinas Luar dikecualikan dari sisi dia yang nyatet).
- Karyawan ajuin Jadwal Libur di tanggal yang Owner udah catat Dinas Luar buat dia → harus DITOLAK (arah sebaliknya, baru dikonfirmasi 12 Agustus).
- Buka 2 tab approval izin/libur, proses salah satu di tab 1, coba approve/tolak lagi di tab 2 (basi) → harus ditolak dengan pesan "Ajuan ini sudah diproses."

---

**13 Agustus 2026 — 2 perbaikan kecil modul Absensi (sidebar Owner + koreksi potongan siang), langsung di `main`, sudah di-push.**

Dipicu Elvan gak nemu fitur koreksi absen lewat menu sidebar (cuma nyempil di kartu shortcut Dashboard), lanjut ke kebutuhan hapus/kurangin potongan absen siang secara manual (kebijakan Owner, bukan bug).

**1. Sidebar Owner dibenerin (commit `8cf6daf`):**
- Ketemu ada 2 halaman absensi mirip nama yang beda isi: `/absensi/rekap-bulanan` (link sidebar lama "Rekap Bulanan", filter bulan/tahun, **read-only, tanpa tombol koreksi**) vs `/absensi/rekap` (cuma bisa diakses lewat kartu Dashboard, filter tanggal harian + tombol "✏️ Koreksi"). Elvan yang lewat sidebar gak akan pernah nemu fitur koreksinya.
- Link sidebar Owner "Absensi" (`/absensi`, form+riwayat absen PRIBADI — Owner gak pernah absen masuk, dikonfirmasi 11 Agustus) diganti jadi "Rekap Absen" → `/absensi/rekap` (dicek dulu: nol tempat lain di kode yang gantung ke link sidebar itu, aman diganti).

**2. Koreksi potongan telat/siang manual (commit `da0b405`):**
- Modal Koreksi di `/absensi/rekap` sekarang punya field baru **"Potongan Telat/Siang (Rp)"** (`resources/views/absensi/rekap.blade.php`), pre-filled dari `potongan_telat` yang lagi tercatat, bisa diubah ke 0 atau angka lain — buat kasus Owner mau maafkan potongan tanpa harus ubah status absen.
- **Bug laten ikut ketemu & dibenerin sekalian:** `AbsensiController::koreksi()` (baris ~449-487) sebelumnya hitung ulang `gaji_hari_ini` dari nol pas ubah status, TANPA mempertimbangkan `potongan_telat` yang sudah tercatat — jadi tiap kali Owner koreksi status (apapun alasannya), efek potongan siang diam-diam KEHAPUS dari gaji padahal angka `potongan_telat`-nya sendiri gak berubah (2 kolom jadi gak nyambung). Sekarang `gajiHariIni` di controller subtract `potongan_telat` (yang baru atau existing) buat status `hadir`/`telat`/`setengah_hari`.
- `koreksiManual()` (buat karyawan yang belum absen sama sekali) SENGAJA tidak disentuh — gak relevan, entry baru gak punya potongan siang sebelumnya.

**Status git:** push ke `main` sukses, 2 commit (`8cf6daf`, `da0b405`) — auto-deploy GitHub Actions jalan otomatis ±1-2 menit.

**BELUM diverifikasi (checklist sesi depan kalau ada laporan aneh):**
- Sidebar Owner: klik "Rekap Absen" → harus ke halaman dengan filter tanggal + tombol Koreksi (bukan 404/ke halaman lama).
- Modal Koreksi: buka buat karyawan yang lagi kena potongan siang → field "Potongan Telat/Siang" harus udah keisi angka yang bener (bukan 0).
- Ubah potongan ke 0 → Simpan → cek gaji hari itu di rekap naik balik sesuai gaji harian penuh (buat status hadir/telat).
- Koreksi status TANPA ubah field potongan → potongan yang lama harus tetap sama (bukan ke-reset ke 0 gak sengaja).

---

**13 Agustus 2026 (sesi kedua) — Fitur "Libur Nasional / Libur Bersama" SELESAI & LIVE (brainstorm→spec→plan→subagent-driven-development, 5 task + final whole-branch review + 2 putaran fix), langsung di `main` (pilihan Elvan), sudah push+deploy.**

Dipicu pertanyaan Elvan soal Lebaran (libur ~2 minggu)/Tahun Baru/17 Agustus — dicek ke kode, sistem cuma punya jadwal libur PER-KARYAWAN (fitur 11 Agustus), nol konsep libur yang berlaku ke SEMUA karyawan sekaligus. Tanpa ini `cron-alpha.php` bakal nandain semua karyawan Alpha pas libur nasional. Spec: `docs/superpowers/specs/2026-08-13-libur-nasional-design.md`. Plan (5 task): `docs/superpowers/plans/2026-08-13-libur-nasional.md`.

**Yang jadi:**
- Tabel baru `libur_nasional` (nama+rentang tanggal) dan `libur_nasional_piket` (pengecualian per-karyawan per-tanggal, buat driver/teknisi yang tetap piket).
- `LiburService` (sudah ada dari fitur jadwal-libur individual) diperluas: `expandLiburNasional()` (pure, pola sama `expandTukar()`) + `ambilLiburNasional()` (wrapper DB), di-merge ke `isLibur()`/`hitungHariKerja()` **paling depan** (libur nasional menang lawan jadwal pribadi kalau bentrok, kecuali karyawan itu piket) — `cocokLiburPada()`/`hitungHariKerjaPada()` (logic inti, 25 assertion lama) **TIDAK disentuh sama sekali**, otomatis ke-cover ke 5 titik konsumen (4 yang direncanakan + `AbsensiController::kodeHariIni()` yang ketemu pas final review, bonus gratis).
- Halaman baru `/libur-nasional` — kalender bulanan visual, Owner klik 2 tanggal buat pilih rentang libur baru + kelola piket per-tanggal; karyawan lain lihat read-only. Link ditambah ke KEDUA sidebar (Owner + karyawan lain), bukan cuma satu — belajar dari gap "Rekap Absen" sesi pagi ini.
- Notifikasi Telegram: broadcast ke semua karyawan connect pas libur nasional baru ditambah, notif personal ke karyawan yang ditunjuk piket.

**Ketemu & dibenerin lewat proses review berlapis (bukan cuma "jadi lalu di-push"):**
- **Task-review Task 4:** 2 bug XSS/escaping JS (apostrof di nama libur bikin dialog konfirmasi hapus gagal muncul tanpa pesan error — form submit langsung tanpa pengaman; nama karyawan diakhiri backslash bisa menyuntik `<script>`) — diperbaiki, sempat ketemu 1 lagi SETELAH fix pertama (escape `@json()` yang baru dipasang malah bikin data mentah masuk ke `innerHTML` tanpa HTML-escape) — dirantai perbaikannya sampai bersih.
- **Final whole-branch review (model paling capable, nemu 3 hal yang gak mungkin ketangkep review per-task):**
  1. `@json()` Blade ternyata kehilangan flag keamanannya sendiri kalau argumennya mengandung koma (bug internal Laravel Blade compiler, `compileJson()` pakai `explode(',', ...)` naif) — proteksi XSS dari fix task-4 TERNYATA gak aktif, cuma "kebetulan aman" dari default PHP. Diverifikasi ulang oleh reviewer dengan compile Blade beneran, bukan cuma baca kode.
  2. Piket dicocokkan lewat `libur_nasional_id` — gagal SENYAP kalau 2 libur nasional beda nama tanggal-nya overlap (contoh nyata: "Cuti Bersama" 15-19 Agustus + "HUT RI" 17 Agustus terpisah) — karyawan yang di-piket-in tetap dianggap libur di baris yang gak kebagian data piketnya. Spec sudah eksplisit bilang kolom itu "buat tampilan doang, BUKAN logic inti" tapi implementasi awal malah makai buat logic. Fix: piket dicocokkan per user+tanggal SAJA (bukan per-`libur_nasional_id`), sekalian nutup N+1 query.
  3. Modal kalender pakai `position:fixed` bersarang di `.page-content` (`overflow-y:auto`+`-webkit-overflow-scrolling:touch`) — jebakan iOS Safari yang PERSIS sama yang udah pernah kejadian nyata di DenahEditor (16 Juli). Ketangkep SEBELUM Elvan lapor dari HP, bukan sesudah — beda dari pola-pola sebelumnya yang nunggu laporan nyata dulu.
- Semua 3 temuan Important di atas: 1 putaran fix, di-re-review scoped, ADDRESSED semua, 0 breakage baru. Test standalone: `tests/libur-nasional/test_libur_nasional.php` (7 assertion, termasuk kasus 2-libur-overlap) + `tests/jadwal-libur/test_libur_service.php` (25 assertion lama) — 100% lulus dua-duanya di titik commit terakhir.

**Insiden kecil pas deploy (bukan bug kode, dicatat buat sesi depan):** SQL `CREATE TABLE` yang dikasih ke Elvan sempat 2x gagal di phpMyAdmin — ternyata ekstensi pengecek-ejaan browser (kemungkinan Grammarly) diam-diam "membetulkan" garis bawah jadi spasi di kolom yang kebaca sebagai frasa wajar (`dibuat_oleh`→`dibuat oleh`, `user_id`→`user id`, `libur_nasional_id`→`libur nasional id`) sementara `tanggal_mulai`/`created_at` yang gak dikenali sebagai frasa tetap utuh — solusinya matikan ekstensi itu atau pakai jendela Incognito buat halaman phpMyAdmin. **Pelajaran buat sesi depan:** kalau SQL manual gagal parse dengan pola aneh (garis bawah hilang selektif), curigai ekstensi browser dulu sebelum curiga SQL-nya sendiri.

**Status git:** push ke `main` sukses, 10 commit (`d3a943d`..`81fceb9`) — auto-deploy GitHub Actions jalan otomatis ±1-2 menit. **SQL sudah dijalankan Elvan di phpMyAdmin production** (2 tabel baru, dikonfirmasi sukses setelah masalah ekstensi browser teratasi).

**Dicatat, bukan diperbaiki di sesi ini (minor dari final review, low-impact, gak masuk fix wave):**
- Broadcast Telegram libur nasional baru gak di-scope `status='aktif'` — karyawan resign yang masih punya `telegram_chat_id` bakal tetap kebagian pengumuman.
- Tambah piket yang sama 2x (klik ulang) gak nge-double-row (`firstOrCreate` sudah benar), TAPI tetap kirim notif Telegram ulang — mirip pola insiden kode-absen-4x (6 Agustus), belum sampai jadi masalah nyata karena piket jarang di-klik ulang.
- Validasi gagal (misal nama libur >100 karakter) balik ke kalender TANPA pesan error yang kelihatan — form cuma nge-reset diam-diam.
- `LiburNasional::piket()` (relasi hasMany) gak pernah dipakai — `destroy()` andelin FK cascade langsung, bukan lewat relasi ini.
- Flash banner sukses/error di halaman ini render 2x (pola lama yang sudah ada di halaman lain kayak `addon/index.blade.php`, bukan bug baru).

**BELUM diverifikasi (checklist sesi depan kalau ada laporan aneh):**
- Owner buka `/libur-nasional` (lewat sidebar ATAU dashboard) → kalender kelihatan, klik "+ Tambah Libur Nasional" → klik 2 tanggal → modal muncul terisi otomatis → simpan → tanggal ter-highlight.
- Broadcast Telegram nyampe ke karyawan yang connect pas libur nasional baru ditambah.
- Klik tanggal yang sudah libur nasional → modal Kelola Piket → tambah 1 karyawan → badge "📌 1 piket" muncul + karyawan itu dapat notif personal.
- Karyawan yang di-piket-in TETAP dapat kode absen besok paginya (`cron-kode-absen.php`), karyawan lain di tanggal sama TIDAK dapat kode.
- `cron-alpha.php` gak nandain Alpha siapapun pas libur nasional (kecuali yang piket).
- Login sebagai karyawan biasa (bukan Owner) → buka `/libur-nasional` dari sidebar → read-only, gak ada tombol tambah/kelola.
- Modal Tambah/Kelola Piket kebuka BENER di HP (khususnya iOS Safari) — ini yang diantisipasi lewat fix #3 di atas SEBELUM ada laporan, jadi belum ada bukti nyata dari device asli.
