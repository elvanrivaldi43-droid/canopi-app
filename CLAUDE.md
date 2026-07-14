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

**SEDANG DIKERJAKAN: Tahap 1A (backend), task-per-task + tes standalone tiap task.** Nol risiko deploy (murni PHP, `php tests/rangka/*.php`, tak sentuh production). Urutan besar sesudahnya: **1A mesin → 1B DenahEditor di RAB opsi (di belakang tab, jalur kotak lama tetap jalan) → 1C validasi PA-DUTA end-to-end lalu sapih+hapus `/rangka-desain` (deploy di sini) → 1D kalibrasi ulang support (target 9 bukan 14) + retune consumable/finishing pakai luas ~40 m²**.

**Status git:** `main` ahead `origin/main`, **BELUM di-push** (keputusan Elvan: deploy hanya setelah bukti PA-DUTA end-to-end di 1C). Fase 1 lama (`/rangka-desain` + fix potong >600cm) sudah ter-merge di `main`.

**Catatan bug laten (di luar scope, buat nanti):** `CuttingService::potong` case-2 mint jid baru → sambungan bisa kurang di kasus ekstrem; `hitungRangka` auto-layout lama pakai intdiv/2 (boleh dipensiunkan setelah DenahEditor menggantikan penuh).
