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

Auto-deploy aman dipakai normal (insiden repo lama 9-11 Juli 2026 sudah tuntas diperbaiki — detail di arsip). **Tetap disiplin:** `git pull` dulu sebelum mulai kerja di sesi/device manapun.

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

Fondasi Laravel 13 (Auth Breeze, 7 level + `CheckLevel` middleware, dark/light mode) · Karyawan (CRUD, `tanggal_bergabung` + `tgl_masuk_kerja`, tunjangan default 0) · Registrasi mandiri via token · Absensi (GPS+kode) · Izin+approval · Profil · Penggajian (slip UM 1-15 & gaji 16-akhir) · Kasbon (`status` VARCHAR, masa kerja `tanggal_bergabung → tgl_masuk_kerja → created_at`, gaji bersih dari `gaji_harian×26`, tombol Approve/Tolak Owner) · Log Bensin · Luar Kota · Pipeline (kanban drag-drop SortableJS, kartu `<div>` bukan `<a>`, `bubbleScroll` untuk auto-scroll) · Alur admin↔surveyor (estimasi vs final terpisah, warning >15% selisih) · Profil Lokasi (foto lama Cloudinary; upload foto/video baru R2, GPS owner+surveyor saja) · Wizard RAB 3 step · Nego+approval via Telegram (`rab_approval`, `ApprovalController`) · Validasi+autosave RAB · Penawaran cetak (printable, TTD digital base64, rekening BCA Syariah 0420017279 a.n Mohammad Elvan Rivaldi M) · **Model Biaya v2 lengkap (V2-1 s/d V2-6)** · KPI/Rapor/Ujian online · Sidebar per-level (sempat salah tampil sama semua level, sudah di-fix 24 file)

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
- Hollow count discrepancy di Material Support — **RESOLVED 14 Juli** lewat validasi ke cutting list asli project PA-DUTA (Cutting Optimization Pro). Dugaan "Besi Tambahan dobel" GUGUR — fitur itu benar (dipakai nambah profil 4x6/3x3 yang beda material). Detail akar masalah ada di arsip `docs/history/CLAUDE_STATUS_ARCHIVE_2026-08-15.md` bagian "Temuan validasi PA-DUTA".

---

## 📋 ROADMAP (urutan prioritas terkunci, jangan dibalik urutannya)

1. **Selesaikan kalibrasi** (sedang jalan)
2. Consumable fixed+variabel (kalau sering ada project kecil)
3. Tahap Perlindungan Lapangan — pemicu rantai tiang WF→scaffolding+takel, checklist wajib (talang→air rumah?/pohon?→cover jaring/bersih rutin)
4. ~~Sesi Media R2~~ — **SELESAI 14 Agustus** untuk upload baru (foto absen 60 hari + foto/video lokasi via custom domain); data Cloudinary/lokal lama tidak dimigrasi otomatis
5. Portal Customer (PUNCAK) — link acak `/lihat/{kode}` tanpa login, PDF-ke-WA + link portal opsional, TTD online, tracking produksi, booking jadwal, bayar termin — butuh modul pembayaran + SWE dulu
6. SWE (Smart Work Engine) — diloncat maju atas keputusan Bos (16 Agustus), kalibrasi RAB (#1) menyusul belakangan. **Fase 1 (Fondasi Tahap Produksi) LIVE. Fase 2 (Skill Karyawan + Rekomendasi PIC) LIVE per 21 Agustus 2026, belum ditest langsung di web — lihat Utang aktif #7.** Sisa: Fase 3 (kapasitas tim), Fase 4 (evaluasi produktivitas) — lihat `docs/superpowers/specs/2026-08-16-swe-smart-work-engine-design.md`
7. Multi-produk (pagar/tralis) — setelah kanopi matang+kalibrasi tuntas

**ANTREAN POLESAN DenahEditor — TUNTAS 28 Ags 2026** (audit 27 Ags; dikerjakan
sebelum kalibrasi karena murni UI, tak menyentuh angka kalibrasi):
  1. ~~Gambar denah ke penawaran cetak~~ **SELESAI & LIVE** — detail di Utang aktif #0.
  2. ~~Legend hitung batang semua material~~ **SELESAI & LIVE.** Tidak jadi "buka filter
     `hitungBatangWF`": rumus itu (total ÷ 600) salah 3 hal — abai sisa potongan terbuang,
     abai batas 1 sambungan per potong, dan mengasumsikan batang selalu 6m padahal
     `master_material.panjang_batang_cm` bisa lain. Uji 6000 kombinasi ukuran wajar: rumus
     kasar TAK PERNAH lebih besar dari kebutuhan nyata → selisihnya SELALU ke arah kurang
     beli (~8% kasus; contoh 460+449+540+298 = 4 batang, kasar bilang 3). Sekarang angkanya
     dari server: endpoint tipis `POST /rab-blok/cutting` → `RangkaDesignService` (mesin yang
     SAMA dengan harga, jadi angka editor & step Harga tak akan beda). Editor memanggil 1,5
     dtk setelah gambar berhenti berubah, melewati panggilan kalau susunan batang sama, dan
     MEMBUANG jawaban yang datang untuk gambar yang sudah berubah. `hitungBatangWF` DIHAPUS
     (kode mati + rumus menyesatkan). Test `tests/rangka/test_cutting_denah.php`.
  3. (a) ~~`prompt()` ketik-sisi~~ **SELESAI & LIVE** — tap garis frame kini memilih sisi itu
     di panel "Ukur Sisi" lalu fokus ke kotak angkanya (panel dibuka kalau terlipat; fokus =
     kosongkan, blur balikin nilai lama; JANGAN `select()` — memicu menu sistem iOS).
     (b) gestur sudut ala Tiang (geser=pindah, tahan=menu) **SENGAJA TIDAK DIKERJAKAN**
     (keputusan Elvan 28 Ags): area gestur kanvas baru stabil setelah beberapa kali
     perbaikan, risiko regresi > manfaat, dan isi menunya sendiri belum jelas.
  4. (a) ~~field model mati `target`/`autoKotak`~~ **DIHAPUS** (diverifikasi tak pernah dibaca;
     model lama boleh tetap membawanya, diabaikan). (b) ~~blok tipe KANOPI di wizard~~
     **DIPENSIUNKAN 28 Ags** atas keputusan Elvan (-120 baris). **Lingkup sengaja terbatas:**
     mesin `CuttingService::hitungRangka` TIDAK dihapus — masih dipakai untuk bentuk awal blok
     Denah (`RangkaDesignService`) dan halaman cutting list (`CuttingController::hitung/cetak`);
     `'kanopi'` di `PipelineLead`/`ProduktivitasController` juga tak disentuh (itu jenis PRODUK,
     konteks lain). Data lama tak dihilangkan diam-diam: kartunya jadi penanda kuning "Blok
     format lama", server membalas peringatan (bukan Rp0 senyap), validasi wizard memblokir
     lanjut, dan `buildPenawaran` MELEWATI blok itu (kalau diikutkan, dokumen customer cuma
     menampilkan "0 x 0 cm"). **Perlu dicek Bos:** lead lama ber-blok kanopi (mis. dina)
     menampilkan penanda kuning, bukan error/kartu kosong.
  **Belum satu pun divalidasi Bos di HP** (kecuali yang disebut di Utang #0).

**Ditunda/belum diputuskan:**
- **Blok "× N unit" untuk order volume/massal** (disetujui Elvan 27 Ags 2026, dikerjakan SETELAH kalibrasi) — kasus: 60 kanopi = 2-3 tipe unik × jumlah, bukan 60 blok. Gambar denah 1x, besi/upah/durasi dikali N (nginap otomatis ikut benar karena baca durasi total). PR bisnis yang harus diputuskan saat bangun: efisiensi produksi massal (per-unit lebih cepat dari satuan) supaya tak kalah harga di project volume — jangan cuma "harga satuan × N"
- C2b video link (Drive/YouTube) — ditunda ke Sesi Media R2
- Besi Bagian B denah interaktif — ditunda
- Pindah kondisi lokasi dari blok ke Profil Lokasi (luar kota/malam/beban berat) — perlu hati-hati, terpisah
- WhatsApp Business API resmi untuk customer — karyawan sudah memakai bot Telegram masing-masing; Telegram tetap jangan dipaksakan ke customer
- **RAB Multi-Opsi: edit bareng multi-user (kayak Trello/Google Sheets)** — muncul 27 Ags 2026
  dari laporan Elvan soal dialog konflik-autosave yang bahaya (lihat Utang aktif #9). Skenario
  bisnis nyata: surveyor input hasil survei di HP di lapangan, admin/owner edit lead yang sama
  di kantor bersamaan → last-write-wins sekarang (1 kolom `rab_snapshot` JSON besar per lead,
  lihat Autosave RAB #9) bikin salah satu KETIMPA, bukan digabung otomatis. Solusi beneran =
  granularitas simpan per-Opsi/per-Blok (bukan 1 blob JSON), itu perubahan arsitektur data
  besar — BUKAN quick-fix. **Sengaja BELUM diputuskan kapan/apakah dikerjakan** — Elvan
  eksplisit bilang "simpan di roadmap dulu" (bukan approval buat langsung mulai). Butuh sesi
  `/plan`/brainstorm terpisah sebelum eksekusi apa pun, sesuai aturan kerja standar perubahan
  besar.

---

## 🔧 CATATAN TEKNIS (jangan diulang kesalahannya)

- Laravel `Http::` facade **tidak jalan** di shared hosting Niagahoster → pakai `curl_init`
- `getenv()` lebih andal dari `env()` untuk baca token di shared hosting
- Kolom user: `status` (bukan `is_active`), `gaji_bulanan` (bukan `gaji`)
- Field `tanggal_bergabung` **HARUS** di-cast `'date'` di `User.php` model
- DB pakai `DB::table` (bukan Eloquent) di endpoint kritis untuk hindari masalah fillable
- **Notifikasi:** Owner via Telegram dengan `TELEGRAM_OWNER_TOKEN`/`TELEGRAM_OWNER_CHAT_ID` dari environment. Karyawan memakai `TelegramService` + `users.telegram_chat_id`. Customer belum punya jalur resmi; tunggu WhatsApp Business API, jangan dipaksa memakai Telegram
- **Storage foto:** upload baru memakai Cloudflare R2 melalui custom domain `media.kanopitangerang.co.id`; jangan kembali ke `r2.dev`. Foto absen baru retensi 60 hari, foto/video lokasi permanen; data Cloudinary/lokal lama tetap di lokasi asal sampai dibersihkan/migrasi terpisah
- **`LOG_LEVEL=error` di production** → `Log::info()`/`Log::debug()` kefilter, tak nyampe `laravel.log`. Debug log sementara pakai `Log::error()` biar pasti kebaca, hapus lagi setelah dipakai

---

## ❓ MODUL YANG BELUM ADA RANCANGAN SAMA SEKALI

**Jangan asumsi ini sudah selesai atau sudah ada rencana — kalau disinggung, ini butuh sesi diskusi khusus dulu (`/plan`), bukan langsung coding.**

- **Modul Keuangan / Laporan Profit** — menu-nya SUDAH ada di sidebar & dashboard, tapi isinya cuma placeholder ("Data dari modul Keuangan — belum tersedia", terkonfirmasi dari tampilan live 11 Juli). Belum ada rancangan struktur DB, belum ada keputusan apa saja yang dihitung (pemasukan dari mana, pengeluaran apa saja, bagaimana relasinya ke RAB/Project/Payroll). **Ini gap besar** — banyak modul lain (RAB, Project, Penggajian) sudah jalan tapi belum ada 1 tempat yang merangkum semuanya jadi laporan profit real.
- **SP Karyawan (Surat Peringatan otomatis)** — statusnya TIDAK JELAS, kemungkinan besar belum selesai dibangun meski KPI/Rapor sudah jalan. Perlu dicek langsung ke Elvan sebelum diasumsikan ada atau tidak.
- **Manufacture Tracking** — kemungkinan besar konsepnya sudah diserap ke rencana SWE (lihat Roadmap #6), tapi ini BELUM DIKONFIRMASI resmi oleh Elvan. Jangan asumsi keduanya sama sebelum ditanya langsung.

---

## 📌 STATUS TERKINI (update tiap akhir sesi kerja)

> **Arsip kronologi lengkap:** `docs/history/CLAUDE_STATUS_ARCHIVE_2026-08-15.md`
> (s.d. 15 Agustus) dan `docs/history/CLAUDE_STATUS_ARCHIVE_2026-08-21.md`
> (s.d. 21 Agustus, termasuk kronologi lengkap Deterministic Guardrail — run ID,
> SHA komit tiap tahap, hasil GREEN/NEGATIVE branch terisolasi). Kalau tugas
> menyangkut insiden/fitur lama, cari detailnya di arsip; jangan memuat seluruh
> arsip untuk pekerjaan yang tidak berkaitan.

### Kondisi production per 21 Agustus 2026

- Repo GitHub = sumber deploy production; auto-deploy FTP dari `main`, dijaga
  **Deterministic Guardrail** (`verify.yml` + `scripts/canopi-check` jalan
  sebelum tiap FTP deploy) — **CLOSED** (selesai antara 15-21 Agustus 2026,
  tanggal pasti & kronologi lengkap di arsip 21 Agustus).
- **LIVE:** Telegram karyawan + kode absen per-karyawan (atomik, personal,
  tak terkirim ganda), jadwal libur per-karyawan, jam masuk/pulang
  per-karyawan, validasi silang izin↔libur, libur nasional, checkpoint
  Lapor Progress/Kembali Kerja, Kerja Hari Libur + hardening payroll/security
  (aktivasi=hari kerja biasa tanpa pengganti, potongan/lembur tak dobel, KPI
  maks 100%, koreksi nominal Owner-only). Penjadwal cron pindah ke crontab
  VPS per 17 Agustus (log permanen `/root/cron-logs/`) — detail di memory
  `cron-scheduling-vps`.
- Media baru pakai Cloudflare R2 via `https://media.kanopitangerang.co.id`
  (jangan kembali ke `r2.dev`); foto lama tetap di lokasi asal.
- Masa kerja Kasbon: urutan `tanggal_bergabung → tgl_masuk_kerja → created_at`,
  tanpa backfill otomatis.
- **SWE Fase 1 (Fondasi Tahap Produksi) LIVE 16 Agustus**, **Fase 2 (Skill
  Karyawan + Rekomendasi PIC) LIVE 21 Agustus** — checklist testing yang
  masih perlu dijalankan Bos ada di Utang aktif #7 di bawah.

### Utang aktif / resume point

0. **Gambar denah ikut ke penawaran cetak — LIVE 27/28 Ags 2026, GAMBARNYA
   BELUM DILIHAT Bos** (alurnya sendiri sudah tervalidasi: tombol "Buat
   Penawaran" kini membuka halaman penawaran dengan benar di HP; yang belum
   dicek khusus tampilan gambar denahnya, butuh lead ber-blok DENAH — lead
   yang dipakai Bos 28 Ags kebetulan blok Kanopi jadi memang tanpa gambar). Antrean polesan DenahEditor #1 selesai. Saat tombol
   "Buat Penawaran" ditekan, SVG yang sudah ada di layar di-snapshot:
   `denahCetak()` (`rab-opsi/index.blade.php`) klon SVG → buang alat bantu
   editor (pita sentuh transparan, bulatan titik sudut, handle ujung support,
   garis bantu snap, tooltip) + lepas sorotan support/balok sementara lalu
   pulihkan → `DenahConv.svgCetak()` (fungsi MURNI string, test
   `tests/rangka/test_svg_cetak.mjs`) memetakan palet layar ke palet kertas.
   Ditampilkan di `penawaran/show.blade.php` sbg `<img>` data-URI — sengaja
   BUKAN SVG tempel-langsung: di dalam `<img>` browser mematikan script &
   sumber luar, jadi nol kode pembersih sendiri.
   **Perilaku disengaja (jangan salah lapor):** (a) foto BEKU — penawaran
   yang sudah dibuat tak ikut berubah kalau RAB diedit lagi; (b) penawaran
   yang dibuat SEBELUM 27 Ags tak punya gambar, harus tekan "Buat Penawaran"
   ulang; (c) lingkaran tiang yang TAMPAK juga ber-class `hit` — kalau
   menyaring elemen, patokannya warna transparan, JANGAN class itu.
   `pipeline_leads.penawaran_json` sudah dicek Bos 27 Ags: **LONGTEXT**, aman
   (SQL cadangan `docs/sql/2026-08-27-penawaran-json-longtext.sql` tak jadi
   dipakai). **Tampak samping/angle SENGAJA belum dikerjakan** — model denah
   cuma simpan 1 angka `tinggi` utk semua tiang, tanpa kemiringan atap; gambar
   samping dari data sekarang akan datar rata (salah utk kanopi yang miring
   buang air). Butuh data baru dulu → sesi `/plan` terpisah, sudah disetujui
   Elvan sbg "atas dulu, samping nyusul".

1. **Kalibrasi RAB tetap prioritas roadmap #1.** Data masih tes dan belum boleh
   dipakai ke customer asli sampai kalibrasi tuntas. PA-DUTA 4x8 masih kurang foto
   bar #12 untuk menutup validasi target 9 batang. Luas referensi yang benar sekitar
   40 m², bukan bounding-box 51 m².
2. **DenahEditor:** ~~Redesign Tambah Tiang~~ — **SELESAI & LIVE 21 Agustus 2026**
   sebagai "measurement-first tiang numerik + ghost preview" (panel tiang numerik
   tambah/edit/hapus/fokus + Undo/Redo/autosave, ghost preview crosshair sebelum
   commit, origin koordinat kiri-depan; `public/js/denah-editor.js`, test
   `tests/rangka/test_tiang_numerik.mjs`).
   ~~Redesign Support~~ — **SELESAI & LIVE 21 Agustus 2026** (dikerjakan via
   subagent-driven-development, 7 task + final review + 1 fix wave), **sudah
   divalidasi manual oleh Bos langsung di web/HP (checklist 3 kelompok: grid,
   manual, undo+regresi Frame/Tiang — semua normal).** Pola drag=pindah/
   tahan=menu (persis pola Tiang) sekarang berlaku juga untuk garis support
   manual, titik ujungnya, dan support grid otomatis (yang terakhir ini
   sekarang bisa digeser dikunci searah, lalu naik-kelas jadi entri manual).
   Panel daftar Support baru (S1/S2 + Fokus/Hapus), kontrol Support
   dikonsolidasi ke 1 tab (buka tab = otomatis aktifkan mode). Detail:
   `docs/superpowers/specs/2026-08-21-denah-support-drag-panel-design.md`,
   `docs/superpowers/plans/2026-08-21-denah-support-drag-panel.md`.
   ~~Ribbon "berceceran"~~ — **SELESAI & LIVE 22 Agustus 2026 (divalidasi Bos di HP).**
   Ribbon dirapikan jadi **3 tab (Rangka/Support/Tiang), tiap tab = 1 mode**
   (buka tab = aktif mode-nya); besi default nempel ke domain masing-masing;
   tab Ukuran/Ukur Sisi/Besi/Mode dibubarkan; Snap grid + Ganti besi pindah ke
   quickbar. Dikerjakan via subagent-driven-development (2 task + final review).
   Spec `docs/superpowers/specs/2026-08-22-denah-rangka-tab-konsolidasi-design.md`,
   plan `docs/superpowers/plans/2026-08-22-denah-ribbon-3-tab.md`. Plus polesan
   yang juga LIVE: quickbar jadi ikon SVG 1 baris (+tooltip), Snap grid sampai
   1cm (grid latar dijaga min ~8px), hint mode default dihilangkan (petunjuk
   aksi sesaat tetap), panel Support disembunyikan saat kosong, **dpad geser
   kanvas saat zoom-in** (tekan-tahan; tombol tengah=recenter) + **pan diberi
   batas biar gambar tak bisa hilang/"tersesat"**.
   Backlog **Frame yang MASIH tersisa**: (a) pola drag=pindah/tekan-tahan=menu
   untuk sudut & sisi rangka (vertex masih drag lama, sisi masih input angka) —
   BELUM dikerjakan; (b) **opsi B navigasi kanvas**: toolbar/tab nempel di mode
   BIASA saat zoom (bukan cuma fullscreen), atau scroll native — ditunda, perlu
   plan (nyentuh area zoom/iOS-sensitif). Catatan: mode **"Perbesar Layar"
   (fullscreen)** sudah nge-pin toolbar+tab+Selesai saat zoom (reparent ke body,
   aman iOS) — arahkan Bos ke situ untuk zoom berat. "Kelompok C saran-kotak-2-arah"
   SELESAI (terserap ke Spacing Per-Sumbu). Sisa lain: investigasi tombol
   "Lanjut → Finalisasi" di HP **hanya jika ada reproduksi video**.
   ~~Kotak/lekukan nempel pas di sudut jadi "duri" sisi ekstra~~ — **SELESAI &
   LIVE 22 Agustus 2026 (divalidasi Bos di HP).** Laporan Bos: kotak 100x100
   ditempel pas pojok bikin sisi ganjil (F5 nyeleneh) alih-alih bentuk L bersih.
   Akar masalah: `combineBox` gak pernah cek sudut lama jadi segaris kalau
   kotak mentok di ujung sisi. Fix: `removeCollinear` buang vertex segaris,
   `combineBoxWithMeta`+`reindexBoxes` jaga pembukuan `combinedBoxes` (drag-
   kotak-utuh) tetap benar walau ada sudut lama yang kebuang. Test baru di
   `tests/rangka/test_box_union.mjs` (kasus sudut awal/akhir + regresi notch
   tengah-sisi).
   **Gelombang polesan 22 Agustus malam (semua LIVE, sebagian sudah divalidasi
   Bos):** (a) **+Sudut/−Sudut jadi mode sticky-toggle** — tap sekali nyala
   terus (bisa kerja berkali-kali), tap lagi/pindah tab/aksi lain = mati;
   sinkron visual tombol 1 titik di `render()`; Undo/Redo sengaja
   MEMPERTAHANKAN mode ini (penanda alat, bukan data). (b) **Reset kotak bisa
   di-Undo** (dulu `resetBox()` ngosongin riwayat total — bug). (c) **Titik
   "+ Sudut" diproyeksikan nempel ke garis sisi** (`closestOnSegment`, test
   `test_closest_segment.mjs`) — tap meleset gak lagi bikin "paruh burung".
   (d) **Anti-jebakan gestur mobile menyeluruh** (audit atas permintaan Bos):
   blok pinch/double-tap zoom HALAMAN (gesturestart + touch-action;
   viewport meta `app.blade.php` ternyata gak punya larangan zoom — guard ini
   satu-satunya benteng), anti-seleksi teks + touch-callout seluruh kartu+menu
   (input dikecualikan), blok contextmenu dalam editor (menu paste input tetap
   hidup), overscroll-behavior (anti pull-to-refresh), user-drag none.
   Residual sadar tanpa fix: edge-swipe back iOS (mitigasi autosave),
   force-zoom aksesibilitas Android/OS (jangan dilawan). Default material
   denah/kanopi baru: Frame+Tiang cari nama besi mengandung "5x10", Support
   "4x8" (data lama yang kadung kesimpen TIDAK ditimpa — ganti manual).
   **Pelajaran deploy:** file test baru WAJIB didaftarkan di
   `tests/guardrail/manifest.json` di commit yang sama (deploy #127/#128 merah
   karena ini, bukan FTP flaky — cek Actions/reproduksi `php scripts/canopi-check`
   dulu sebelum nyalahin FTP).
   **Redesign Support ID Stabil — LIVE & TERVALIDASI TUNTAS Bos di HP
   (23-27 Ags 2026, seluruh checklist lolos).** Dua fase pratinjau→kunci
   otomatis, garis grid nyimpan JALUR bukan ujung, toggle move quickbar,
   pindah=ketik angka relatif, panel ceklis.
   Spec bagian 3 = batasan disengaja — jangan salah lapor bug. Spec/plan:
   `docs/superpowers/{specs,plans}/2026-08-23-denah-support-id-stabil*`.
   **Gelombang lanjutan 23-24 Ags yang juga LIVE (semua via SDD + review):**
   (a) **Garis support numerik + "Pecah jadi manual"** — form ketik posisi
   (datar/tegak + cm) dgn ghost preview, potongan BERHENTI di frame (tak
   menyeberangi coakan); pecah jalur grid → entri manual per potongan (hapus
   yg tak perlu → hitungan besi benar). Plan:
   `docs/superpowers/plans/2026-08-23-denah-support-jalur-manual.md`.
   (b) **Pindah garis manual lurus di-re-clip ke frame** (kasus S16: geser
   masuk coakan → terbelah/berhenti, keluar frame → ditolak); arah dropdown
   difilter tegak-lurus garis. (c) **Label kanvas rapi** (aturan Elvan): frame
   di LUAR garis + rotasi ikut arah sisi, support di dalam, garis tegak anchor
   30%, label adaptif potongan pendek — TERVALIDASI. (d) **Balok Melintang
   B1..Bn** (portal frame + bracing): entitas baru `S.balok[]`+`balokSeq`,
   ujung tipe Tiang (ikut geser) / Titik bebas (X-dari-kiri/Y-dari-depan,
   konvensi sama panel Tiang), pilih besi per balok, legend "WF: N batang 6m"
   (ceil total/600), hapus tiang → cascade ujung balok dibekukan jadi titik
   bebas (1 langkah Undo). Plan:
   `docs/superpowers/plans/2026-08-24-denah-balok-melintang.md`.
   (e) **Fix jari hantu pinch** — pointermove kini refresh stempel `t`;
   sebelumnya 1 pointerup hilang (swipe notifikasi iOS) = SELURUH kanvas mati
   permanen di semua tab sampai reload (kasus nyata 24 Ags malam).
   (f) **Polesan hasil validasi 25-27 Ags (semua LIVE & tervalidasi):** pita
   sentuh frame digating mode bentuk/besi (ujung support di tepi frame bisa
   ditarik); garis ikut jari live saat tarik ujung terkunci; panel Support
   TIDAK auto-buka dari tap kanvas (layout loncat bikin tap meleset); balok
   tersorot = halo kuning (warna besi tetap tampil) + Fokus balok jadi toggle;
   form titik bebas balok pakai konversi X-dari-kiri/Y-dari-depan.
   **VALIDASI MANUAL TUNTAS 27 Ags — seluruh checklist lolos:** kunci/undo/
   sorot/pindah angka/ceklis/coakan-terbelah, label rapi, semua skenario balok
   (tiang↔tiang, bracing, custom, cascade, ganti besi, batang WF, tap tak
   nyangkut A-E), + support manual terkunci, Pecah jadi manual, pindah manual
   re-clip (masuk coakan terbelah / keluar frame ditolak), besi nempel ke
   garis saat frame berubah (inti ID stabil), Susun Ulang + nomor lanjut.
   **Perilaku disengaja (jangan salah lapor):** nomor S/B tak pernah dipakai
   ulang; entri manual TIDAK ikut frame saat bentuk berubah (patuh tangan
   user; penyesuaian = pindah 1x utk memicu re-clip); tombol "Pulihkan yang
   dihapus" tetap hidup KHUSUS fase pratinjau sesuai spec 2.5 (fase terkunci
   pakai ceklis + toggle Semua — bukan utang lagi, jangan dihapus).
   **Gelombang UI polish tambahan 27 Ags malam (LIVE, belum dikonfirmasi Bos
   di HP):** (a) Padatkan kartu lead/tab opsi/opsi-bar/header blok denah +
   kartu Nama Opsi & Finishing RAB Multi-Opsi, batas 16px input/select
   (cegah auto-zoom iOS Safari). (b) Panel Rangka dipadatkan + tombol teks
   diganti ikon, panel auto-lipat setelah tap Reset/+Sudut/-Sudut/+Kotak.
   (c) Besi frame/Support/Tiang + popup Ganti Material: `<select>` native
   diganti combobox custom ketik-cari (`_wireBesiCombo()`, 1 implementasi
   4 titik pakai; nilai tak valid saat blur direvert, native `<input
   list=datalist>` sengaja dihindari — dukungan iOS Safari tak konsisten).
   **3 bug ditemukan Bos 27 Ags malam & sudah difix semua:** (1) fokus
   ke input tadinya manggil `input.select()` -- di iOS Safari itu memicu
   menu sistem "Potong/Salin/Tempel/Isi-Auto" yang nutupin & rebutan
   sentuhan sama dropdown custom di bawahnya. Fix: kosongkan `.value`
   langsung saat fokus (bukan select-all) -- blur tetap balik ke nilai
   lama otomatis kalau ditinggal kosong. (2) Item dropdown dipilih lewat
   `pointerdown`+`preventDefault()` (dulu buat cegah blur nutup list
   duluan) -- ternyata preventDefault di pointerdown JUGA membatalkan
   gestur scroll dari titik sentuh itu, jadi jari nyentuh list langsung
   kepilih, gak sempat digeser cari dulu. Fix: pilih lewat event `click`
   (fire setelah touchend/scroll selesai), blur tetap nunda tutup list
   150ms (logika lama) jadi cukup waktu buat click kebaca. `.de-combo-list`
   sudah punya `max-height:180px;overflow-y:auto` dari awal, scroll kini
   jalan normal. (3) Besi support cuma nongol 1 baris dropdown-nya --
   baris "Besi support" itu paling bawah di panel `.de-ribbon-strip`
   (panel Rangka/Support/Tiang, `overflow-y:auto;max-height:45vh`),
   dropdown absolute-nya ikut kepotong batas panel itu. Fix: saat fokus,
   panel digeser (`strip.scrollTop`) biar input mepet ke atas, nyisain
   ruang penuh 180px (~5 baris) di bawahnya buat dropdown.
   (d) Ukur Sisi: awalnya chip F1..Fn (tap dulu baru kotak ketik muncul),
   lalu direvisi lagi jadi **dropdown** pilih F1..Fn + checkbox "Tampilkan
   semua" di sampingnya (checked = semua kotak ketik F1..Fn muncul
   sekaligus, unchecked = cuma yang dipilih di dropdown) — permintaan Elvan
   27 Ags malam setelah lihat versi chip. (e) Panel Support & Tiang: versi
   pertama cuma ganti tombol "Buka/Lipat" jadi checkbox "Tampilkan semua"
   — DICOBA Bos di HP, DITOLAK (14 support numpuk begitu dicentang, gak
   ada cara pilih satu tanpa nyisir daftar panjang). Direvisi jadi
   **dropdown pilih S#/T# dulu → baris edit baru muncul utk yang dipilih**,
   checkbox "Tampilkan semua" tetap di sampingnya buat balik ke daftar
   penuh kalau perlu. Dropdown pakai `this.selSup`/`this.selTiang` yang
   sama dipakai tombol Fokus & tap-canvas (Support) biar 2 jalur pilih itu
   sinkron. Checklist aktif/nonaktif per baris Support & tombol
   Fokus/Hapus/Ganti besi/Pecah TIDAK disentuh. Tiang sebelumnya TIDAK
   punya konsep "pilih satu" sama sekali (semua T1..Tn selalu full-
   expand) — sekarang nambah `selTiang`/`tiangShowAll` pola identik
   Support (hapus tiang → `selTiang` direset null krn indeks ikut geser;
   tambah tiang baru → auto-terpilih). Diverifikasi node --check + 13 test
   .mjs rangka + canopi-check --full sebelum tiap push; commit
   `540dbcd..HEAD`. **Dropdown Support/Tiang & Ukur Sisi SUDAH dicoba Bos
   di HP dan OK** ("sudah berhasil semua"). Fix combobox besi (poin c)
   **TERVALIDASI Bos di HP 27 Ags malam** -- list bisa di-scroll jari tanpa
   langsung kepilih, menu Paste iOS tak nongol, dropdown besi Support penuh.
   (f) **Bug ditemukan Bos 27 Ags malam & sudah difix:** semua tulisan di
   panel editor denah (Rangka/Support/Tiang, label, tombol) "jadi
   transparan" begitu APP dipindah ke mode gelap. Root cause: elemen
   panel (`.de-card` dkk) set `background:#fff` tapi TIDAK pernah set
   `color` sendiri -- teks warisan warna dari `<body>` halaman, dan mode
   gelap app nyetel `body` jadi abu-abu terang (`text-slate-200`, buat
   latar gelap `app.blade.php`). Panel denah SELALU berlatar terang
   apapun mode app-nya (gak ada varian gelapnya sendiri), jadi teks abu-
   abu-terang-di-atas-putih itu nyaris tak kebaca -- bukan CSS transparan
   beneran, murni warna teks ke-inherit salah. Fix: tambah
   `color:#334155` eksplisit di selector gabungan
   `.de-card,.de-matmenu,.de-tiangmenu,.de-supportmenu` (baris yang sudah
   ada buat anti-seleksi-teks) -- 1 baris nutup ke-4 kontainer panel
   sekaligus (termasuk popup Ganti Material/menu Tiang/Support yang
   belum sempat dilaporkan tapi kena bug sama). Diverifikasi node
   --check + 13 test .mjs rangka + canopi-check --full. **TERVALIDASI Bos
   di HP 27 Ags malam** (mode gelap, teks panel kebaca normal).
   (g) **Permintaan Elvan 27 Ags malam — konvensi posisi Support disamakan
   ke Tiang + panjang ditambahkan ke deskripsi:** sumbu H (`datar`) di
   `describeLockedSupport` dulu "Ncm dari atas" diukur dari `bb.y0` (tepi
   atas model) -- BEDA dari panel Tiang yang originnya `bb.y1`
   ("depan"/sisi terbuka kanopi, lihat `tiangToOffset`/`denahOrigin`).
   Diubah jadi "Ncm dari depan" diukur dari `bb.y1`, PERSIS konvensi
   Tiang (sumbu V "dari kiri"/`bb.x0` sudah cocok dari awal, tak diubah).
   `manualEntriesFromJalur` (form "+ Garis support ketik posisi") ikut
   diubah ke `bb.y1 - cmRel` biar angka yang diketik & yang ditampilkan
   di panel konsisten satu arti. **Panjang ditambahkan** ke deskripsi
   (mis. "datar · 151cm dari depan · 400cm") -- dijumlah dari member
   ber-id sama di `mem` (`buildMembers`), BUKAN dihitung ulang manual, jadi
   otomatis benar kalau garis kepotong coakan jadi >1 potongan (jumlah
   total, sama logika hitungan besi yang sudah ada). `describeLockedSupport`
   nambah parameter `mem` opsional (dua pemanggil di `renderSupportPanel`
   sudah punya `mem` di scope, tinggal diteruskan). Entri manual TIDAK
   diubah (sudah tampilkan panjang dari awal, tanpa posisi). Test lama
   `test_support_pick.mjs`/`test_support_jalur_manual.mjs` disesuaikan +
   3 assert baru utk parameter `mem`. Diverifikasi node --check + 13 test
   .mjs rangka (tanpa FAIL) + canopi-check --full. **TERVALIDASI Bos di HP
   27 Ags malam** (deskripsi baca "Ncm dari depan" + panjangnya).
   (h) **Preview pindah Support (permintaan Elvan 27 Ags malam, langsung
   nyusul poin g):** form "Terapkan" pindah Support (Arah+cm, berlaku
   utk manual MAUPUN grid/bawaan -- satu editRow yang sama) dulu commit
   LANGSUNG begitu tombol ditekan, tanpa lihat dulu -- beda dari pola
   Tiang (ghost preview nempel kursor sebelum Tambah). Ditambah live
   preview: tiap ganti Arah/ketik cm, `moveManualReclip` (fungsi MURNI,
   sudah ada) dipanggil tanpa commit, hasilnya digambar ghost dashed cyan
   lewat `drawSupJalurPreview` (dipakai bareng form "+ Garis support" --
   satu fungsi, dua pemakai). Preview dibersihkan otomatis tiap
   `renderSupportPanel` beneran di-render-ulang (ganti pilihan/tab/dst)
   supaya gak nyangkut nempel salah entri; TIDAK dibersihkan tiap
   keystroke (biar fokus input gak ilang tiap huruf, pola sama form
   tambah). **Bug ditemukan Bos & sudah difix (dibuktikan lewat
   reproduksi `node -e` langsung, bukan tebakan):** utk entri GRID/
   bawaan (bukan manual), `moveManualReclip` balikin `{axis,pos}` mentah
   tanpa `a`/`b` -- `drawSupJalurPreview` butuh titik gambar, jadi crash
   diam-diam (preview manual sendiri sudah benar dari awal, cuma grid yg
   rusak). Fix: convert lewat `jalurSegments(S,axis,pos)` kalau entri gak
   punya `a`/`b`. Dikunci 1 assert regresi baru di
   `test_support_jalur_manual.mjs`. **Lalu ketahuan fix ini GAGAL DEPLOY
   diam-diam (FTP flaky, insiden sama kayak dulu)** -- diverifikasi via
   `curl` langsung ke file live vs lokal (beda, server ketinggalan 2
   commit), di-re-trigger via commit kosong, diverifikasi lagi sampai
   `diff` live vs lokal IDENTICAL. **TERVALIDASI Bos di HP** setelah
   redeploy -- garis preview sudah muncul normal, termasuk utk support
   bawaan/grid.
   (i) **Tombol Support jadi ikon + compact (permintaan Elvan 27 Ags
   malam):** "+ Support manual"/"Pulihkan yang dihapus"/"Susun Ulang"
   (teks panjang, gampang wrap jadi banyak baris di HP) diganti ikon SVG
   + label pendek ("Manual"/"Pulihkan"/"Susun Ulang"), pola SAMA persis
   Reset/Sudut/Kotak di tab Rangka (`.de-mini` + `.de-ico`, title=tooltip
   penjelasan). **Bukan bug** kalau tab Support kelihatan cuma ada
   3 tombol itu -- kontrol arah/mode/kotak grid (`rowSupArah`/
   `rowSupH`/`rowSupV`, nama `rowSupArah` per poin (j) di bawah) SENGAJA
   cuma tampil di fase PRATINJAU (sebelum dikunci); begitu terkunci
   (S1..Sn sudah ada, kasus Elvan), yang
   relevan cuma edit per-garis lewat panel "Support (N)" di bawahnya.
   Diverifikasi node --check + 13 test .mjs rangka + canopi-check --full.
   (j) **Panel Support fase PRATINJAU dipadatkan (Elvan lihat langsung
   fase ini, bukan cuma fase terkunci):** dulu 1 baris gabungan Arah+
   Ideal-per-kotak+Saran+hint (`rowSupSpacing`) bikin wrap jadi 2-3 baris
   di HP + label "Horizontal"/"Vertikal" full-text makan tempat. Dipecah
   & dipadatkan: (1) `rowSupSpacing` dibelah jadi `rowSupArah` (dropdown
   Arah + tombol "Saran" [ikon lampu] 1 baris) dan `rowSupIdeal` (input
   Ideal per kotak + hint, baris terpisah). (2) **`rowSupIdeal` cuma
   tampil kalau arah BUKAN "2 arah"** (1 arah h/v saja) -- permintaan
   eksplisit Elvan, alasan: pas 2 arah sudah ada 2 baris Kotak(cm)
   sendiri-sendiri (H dan V) buat diisi manual, "Ideal+Saran" jadi kurang
   perlu; Saran sendiri tetap SELALU ada di baris Arah (mengisi H & V
   sekaligus dari nilai Ideal terakhir, logika `applySaran()` TIDAK
   diubah). (3) Label "Horizontal"/"Vertikal" diganti ikon garis + huruf
   "H"/"V" (title=tooltik nama lengkap), `.de-sup-axname` min-width
   diperkecil drastis -- baris Mode+Kotak(cm) kini muat 1 baris di HP.
   Diverifikasi node --check + 13 test .mjs rangka + canopi-check --full.
2b. ~~Deploy FTP flaky~~ **DIPERBAIKI 28 Ags** — upload kini diulang otomatis sampai
   3x di `deploy.yml` (28 Ags gagal 2x dalam sehari, run 33097366635 & 33156528739,
   guardrail HIJAU keduanya, pulih hanya dengan diulang). Percobaan ke-3 SENGAJA tanpa
   `continue-on-error`: kalau itu pun gagal, deploy tetap MERAH — jangan pernah dibuat
   hijau-palsu. `tests/guardrail/test_deploy_verification_gate.php` mengunci: FTP action
   tepat 3 & versi sama, `continue-on-error` tepat 2, percobaan terakhir diperiksa tak
   punya `continue-on-error`. Terbukti jalan di run pertama (percobaan 1 sukses, 2-3
   dilewati). **Cara cek deploy yang benar tetap: bandingkan file live vs lokal
   (`curl` + `diff`)** — JANGAN percaya Actions hijau saja, pernah hijau tapi file tak
   naik. Notifikasi Telegram saat deploy merah: belum, Elvan pilih retry saja.
3. `public/cron-kpi.php` masih dead code karena referensi
   `bootstrap/autoload.php` lama; notif KPI bulanan belum nyata sampai diperbaiki
   sebagai task terpisah.
4. Verifikasi production yang masih layak dilakukan bila ada laporan: foto absen
   baru benar-benar tersimpan R2; role Admin/Mandor pada alur kerja hari libur;
   slip lembur hanya sekali; kode hari ini tidak dibuat oleh GET; cron alpha
   menghitung karyawan yang diaktifkan lalu mangkir.
5. Audit/backfill `tanggal_bergabung` belum dilakukan. Jangan membuat/menjalankan
   `UPDATE` sebelum hasil SELECT nama + tiga tanggal diperiksa Bos.
6. Foto absen lokal lama dan `foto-absen-bersih.php` baru boleh dipensiunkan
   setelah retensi/migrasi data lama benar-benar selesai.
7. **SWE Fase 2 (skill karyawan + rekomendasi PIC) — LIVE per 21 Agustus 2026,
   dikerjakan via subagent-driven-development (10 task + final review),
   deploy sukses — TAPI BELUM DITEST LANGSUNG DI WEB.** Yang perlu dicek Bos:
   - Buka halaman **Kelola Produktivitas** → tabel skill sekarang ada kolom
     "Kategori" (dropdown Tukang/Kenek/Tukang & Kenek/Manual saja) — semua
     skill lama default-nya "Manual saja". **Isi kategori tiap skill dulu**
     (mis. skill "Las" → Tukang), baru fitur auto-attach di bawah ini jalan
     — sebelum diisi, checklist skill karyawan akan selalu kosong otomatis.
   - Buka **halaman edit salah satu Karyawan** → harus ada section baru
     "Skill yang Dimiliki" (checklist), skill yang otomatis dari jabatan
     ditandai beda dari yang manual. Kalau jabatan karyawan itu gak
     mengandung kata "tukang"/"kenek" (mis. "Surveyor", "Admin Sales"),
     harus muncul badge kuning peringatan kategori gak terdeteksi.
   - Buka **project apapun → tahap produksi → "Mulai Tahap"** → harus ada
     tombol baru "Hitung Saran" di atas daftar PIC. Isi qty + target
     selesai, klik tombol → cek jumlah tukang/kenek yang disarankan masuk
     akal, dan badge cocok/sibuk/tidak-cocok muncul di tiap nama.
   - Endpoint `mulaiTahap()` Fase 1 sengaja TIDAK diubah — pastikan alur
     "Mulai Tahap" yang lama (pilih PIC manual, submit) masih jalan normal
     seperti sebelumnya.

   Spec penuh (semua fase): `docs/superpowers/specs/2026-08-16-swe-smart-work-engine-design.md`
   bagian 4-B & 5. Plan Fase 2 (referensi detail + daftar file yang
   berubah): `docs/superpowers/plans/2026-08-21-swe-fase2-skill-rekomendasi-pic.md`.

8. **DenahEditor — Spacing Support Per-Sumbu — SELESAI & LIVE (push + deploy
   sukses 22 Agustus 2026).** Dikerjakan via subagent-driven-development
   (Task 1-5 + final whole-branch review), backward-compat diverifikasi empiris
   (harness ekuivalensi 25.200 variasi model lama, nol selisih vs `buildMembers`
   lama). Plus 2 polesan lanjutan yang juga sudah LIVE: (a) tata letak panel
   Support per-sumbu dirapikan + di mode "jumlah kolom" muncul hasil bagi rata
   "= N cm/kotak" live; (b) label angka cm pada garis support **vertikal**
   diputar -90 (ikut arah garis), horizontal tetap mendatar.
   Spec/plan: `docs/superpowers/{specs,plans}/2026-08-21-denah-support-spacing-per-sumbu*`.
   - **Dua perilaku yang perlu diwanti-wanti saat cek (bukan bug baru, jangan
     salah lapor):** (1) Undo TIDAK membatalkan perubahan spacing (perilaku
     lama field tunggal, cuma lebih gampang ketemu lewat 2 sumbu). (2) Ganti
     mode/nilai spacing bisa bikin garis support yang tadinya "dihapus" muncul
     lagi / garis lain hilang — flag "dihapus" dikunci ke index posisi, bukan
     identitas garis (sekelas perilaku field `kotak` tunggal lama).
   - Checklist manual A-D (laporan Task 5) belum tentu sudah dijalankan Elvan
     tuntas di device; kalau ada anomali baru, cek ke sana dulu.
9. **Autosave RAB — SELESAI & LIVE 27 Ags 2026** (dipicu 2 kasus nyata:
   tabrakan dua tab 24 Ags + kerja hilang karena sinyal putus). Tiga lapis:
   (1) draft lokal localStorage per-lead, ditulis sebelum tiap kirim, dihapus
   hanya saat sukses; draft tersisa saat load = ditawarkan "lanjut dari
   draft?"; (2) retry otomatis 6 dtk untuk gagal jaringan/5xx (4xx non-409 =
   pesan jelas tanpa retry, 419 = sesi habis); (3) guard konflik: klien bawa
   `base_md5`, server (`SnapshotGuard::conflict`, test
   `tests/rab/test_snapshot_guard.php`) balas 409 kalau snapshot tersimpan
   sudah berubah -> tab basi berhenti menimpa (baca-saja) + confirm reload.
   Guard busy `_asBusy`/`_asPending` mencegah fetch paralel 409-palsu;
   `simpanKeLead` ikut update `BASE_MD5`. Kompat mundur penuh (klien lama
   tanpa base_md5 tetap jalan), nol perubahan skema DB.
   **Residual sadar (bukan bug, dicatat dari review):** (a) Simpan Final di
   tab yang sudah konflik masih BISA menimpa (aksi eksplisit; confirm-nya
   sudah diberi peringatan keras); (b) race autoSave vs simpanFinal bisa
   bikin 1x dialog 409 palsu — draft aman, reload memulihkan. Kolom
   `pipeline_leads.rab_snapshot` SUDAH DICEK Bos 27 Ags: LONGTEXT — aman,
   tak ada risiko snapshot kepotong. Seluruh #9 TERVALIDASI di HP 27 Ags
   (tes mode pesawat + draft pulih + konflik dua tab ditolak).
   **Bug lanjutan ditemukan Bos 27 Ags malam & sudah difix:** `hapusOpsi()`
   (hapus tab Opsi) dan `hapusBlok()` (hapus kartu blok) cuma nyopot
   elemen dari DOM, TIDAK PERNAH manggil `autoSave()` -- beda dari semua
   aksi lain yang pada akhirnya lewat jalur autosave (denah onChange
   didebounce ke `jadwalkanHitung`, navigasi wizard manggil `autoSave()`
   langsung). Efeknya: hapus opsi/blok kelihatan hilang di layar, tapi
   server gak pernah tau -- refresh sebelum ada autosave lain kepicu
   (mis. ganti step wizard) balikin lagi opsi/blok yang tadi dihapus,
   BUKAN bug database duplikat/gagal hapus. Fix: tambah `autoSave();` di
   akhir kedua fungsi itu (`resources/views/rab-opsi/index.blade.php`).
   **Bug SATU KELAS ditemukan lagi 27 Ags malam (langsung nyusul yg di
   atas):** ganti "Nama opsi aktif" & nama blok (termasuk nama "Denah")
   juga TIDAK TERSIMPAN kalau refresh -- akar masalah sama: input `opsiNama`,
   select `opsiFinishing`, & field blok (`b-nama`, field kanopi, dst) cuma
   update `dataset`/atribut di DOM, gak ada satupun yang manggil
   `jadwalkanHitung()`/`autoSave()`. Cuma DenahEditor (`onChange`) & tombol
   wizard yang punya jalur simpan; field form biasa tidak. Fix: tambah
   `jadwalkanHitung(pane)` di listener `opsiNama`(input)/`opsiFinishing`
   (change), dan di listener `change` level-kartu `tambahBlok()` (satu
   listener delegated ini nangkep SEMUA field di dalam kartu -- nama blok,
   field kanopi, dst -- sekali pasang). Diverifikasi canopi-check --full
   (Blade compile). **TERVALIDASI Bos di HP 27 Ags malam** -- kedua fix
   "hilang saat refresh" (hapus opsi/blok & rename opsi/blok) sudah benar:
   hapus blok lalu refresh tetap hilang, rename opsi bertahan.
   **Bug UX ditemukan Bos 27 Ags malam (2 dialog beruntun bikin bingung &
   bahaya) & sudah difix:** konflik 409 -> dialog "Data di server LEBIH
   BARU, OK=reload" -> abis reload, dialog KEDUA otomatis muncul "Ada
   DRAFT LOKAL, OK=lanjut dari draft itu" -- padahal draft itu PERSIS
   versi yg BARU SAJA ditolak server (kalah baru dari tab/device lain).
   Kalau OK-OK tanpa baca (gampang, sama-sama mulai kata "OK"), efeknya
   nimpa BALIK data server yang lebih baru pakai draft basi -- bisa bikin
   hasil survei lapangan (device lain) hilang kalau ke-timpa admin/owner
   yang edit lead yang sama di device lain (skenario nyata: surveyor
   sedang survei pakai HP, admin/owner edit lead yang sama di kantor).
   Fix: `sessionStorage` flag ditulis sebelum `location.reload()` di
   dialog konflik; IIFE pemuatan baca flag itu -- kalau reload KARENA
   konflik, draft dibuang diam-diam (toast info singkat via
   `simpanStatus`, bukan dialog interaktif lagi), data server yang
   dipakai TANPA konfirmasi kedua. Reload biasa (bukan dari konflik,
   mis. abis ditutup paksa/sinyal putus) tetap dapat dialog draft-
   restore seperti biasa -- tak berubah. Diverifikasi canopi-check --full
   (Blade compile). Belum ada laporan validasi manual HP (butuh 2
   device/tab beneran + sinyal 409 asli buat tes ulang).
   **Diskusi arsitektur multi-user (Trello/Sheets-style) muncul dari
   laporan ini** -- dicatat penuh di ROADMAP bagian "Ditunda/belum
   diputuskan", BUKAN di sini (biar gak dobel + gak drift kalau salah
   satu diupdate). Intinya: BELUM diputuskan kapan/apakah dikerjakan,
   Elvan eksplisit minta "simpan di roadmap dulu".

### Pelajaran aktif dari kronologi (jangan hilang saat arsip tidak dibaca)

- iOS Safari: `position:fixed` rusak bila bersarang di `.page-content`.
  **AKAR MASALAHNYA SUDAH DICABUT 28 Ags 2026** — biangnya `-webkit-overflow-scrolling:touch`
  di `.page-content` (`layouts/app.blade.php`), bukan `overflow-y:auto`-nya.
  **JANGAN pasang balik properti itu** (ada komentar peringatan di CSS-nya);
  sudah usang juga — momentum scroll bawaan sejak iOS 13.
  Gejalanya: bar aksi bawah hilang TOTAL di HP pada 4 halaman sekaligus
  (rab-opsi + rab-blok `.actbar`, kelola_material `.ka-actions`,
  produktivitas `.pk-actions`) — dilaporkan Elvan sbg "tombol Lanjut →
  Finalisasi tidak muncul", sempat lama tak terpecahkan karena ditebak-tebak.
  Dibuktikan empiris lewat halaman uji sekali pakai di HP Elvan (bar identik
  di dalam wadah = tak muncul, di luar = muncul; "induk pengunci" TIDAK ADA;
  begitu properti dimatikan bar langsung muncul di posisi yang memang sudah
  benar: `rect.bottom` = `innerHeight`). **TERVALIDASI Elvan di HP.**
  Tambalan lama yang masih ada dan tetap aman dibiarkan (tak perlu dicabut,
  tapi juga tak perlu ditiru lagi): reparent ke `document.body` pada overlay
  fullscreen DenahEditor dan modal `libur-nasional`.
- iOS Safari memblokir `window.open()` DIAM-DIAM bila dipanggil setelah `await`/
  `.then()` (dianggap bukan hasil langsung sentuhan jari) — tombol terlihat MATI
  padahal aksinya sukses. Untuk membuka halaman hasil sesudah fetch, pakai
  `location.href`, jangan tab baru. **Kejadian nyata 28 Ags 2026** pada "Buat
  Penawaran" (`rab-opsi`), tervalidasi Elvan; `window.open` yang dipanggil
  SINKRON langsung di handler klik (WA quote `rab/wizard` & `rab/show`, surat
  kasbon) tidak kena — jangan ikut diubah.
- Jangan biarkan kegagalan DIAM. Aksi yang bisa melempar error (mis.
  `buildPenawaran()`) wajib punya jalur pesan ke user — tombol yang "tidak
  bereaksi" tanpa pesan adalah kelas bug yang paling lama tak terpecahkan di
  project ini (dua kejadian 28 Ags: bar tak tampil + tab diblokir).
- Debug production memakai `Log::error()` sementara karena `LOG_LEVEL=error`
  menyaring `info/debug`; hapus instrumentation setelah bukti didapat.
- Cron/notifikasi harus idempotent: record existing tidak boleh memicu kirim ulang.
- GET harus read-only; pembuatan kode/pesan wajib melalui POST atau cron yang jelas.
- Perubahan schema production selalu SQL idempotent manual di phpMyAdmin sebelum
  push kode yang bergantung padanya; verifikasi kolom/index dari hasil nyata.
- Smoke-test route bukan E2E bisnis. Jangan membuat slip, mengaktifkan karyawan,
  mengirim kode, atau menulis data production hanya untuk pengujian tanpa izin.
- `public/hot` (penanda `npm run dev` lokal) sempat ke-commit 11 Juli & ikut
  ter-deploy — `@vite()` production jadi ngarah ke dev-server lokal yang gak
  ada (`http://[::1]:5173`). Ketemu & difix 22 Agustus, sudah masuk
  `.gitignore`. Kalau abis `npm run dev` di lokal, cek `git status` sebelum
  commit — file ini gampang kebawa gak sengaja.

### Aturan update status ke depan

- Ganti/ringkas bagian **Pekerjaan aktif** dan **Utang aktif**; jangan menambahkan
  narasi sesi panjang terus-menerus ke file ini.
- Kronologi detail yang selesai dipindahkan ke file baru di `docs/history/` dan
  ditautkan dari sini.
- Keputusan bisnis aktif, risiko production, dan resume point harus tetap ada di
  file ini agar sesi baru tidak perlu membaca arsip penuh.
