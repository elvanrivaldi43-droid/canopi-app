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
6. SWE (Smart Work Engine) — diloncat maju atas keputusan Bos (16 Agustus), kalibrasi RAB (#1) menyusul belakangan. **Fase 1 (Fondasi Tahap Produksi) LIVE.** Sisa: Fase 2 (skill+rekomendasi PIC), Fase 3 (kapasitas tim), Fase 4 (evaluasi produktivitas) — lihat `docs/superpowers/specs/2026-08-16-swe-smart-work-engine-design.md`
7. Multi-produk (pagar/tralis) — setelah kanopi matang+kalibrasi tuntas

**Ditunda/belum diputuskan:**
- C2b video link (Drive/YouTube) — ditunda ke Sesi Media R2
- Besi Bagian B denah interaktif — ditunda
- Pindah kondisi lokasi dari blok ke Profil Lokasi (luar kota/malam/beban berat) — perlu hati-hati, terpisah
- WhatsApp Business API resmi untuk customer — karyawan sudah memakai bot Telegram masing-masing; Telegram tetap jangan dipaksakan ke customer

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
> adalah salinan byte-for-byte `CLAUDE.md` sebelum perampingan (SHA-256
> `119523e3bbb6ca6ee9fd5ccbd218d9051a1a67e0eee227de21a78df649467455`).
> Kalau tugas menyangkut insiden/fitur lama, cari detailnya di arsip tersebut;
> jangan memuat seluruh arsip untuk pekerjaan yang tidak berkaitan.

### Kondisi production per 15 Agustus 2026

- Repo GitHub = sumber deploy production; auto-deploy FTP dari `main` normal.
- Telegram karyawan + kode absen per-karyawan **LIVE**. Kode bersifat atomik,
  personal, tidak terkirim ganda, dan tidak berlaku setelah ada
  sakit/izin/cuti/dinas luar. **Penjadwal pindah dari cron-job.org ke crontab
  VPS per 17 Agustus 2026** (log permanen di `/root/cron-logs/`, bukan lagi
  retensi 1 hari) — detail di memory `cron-scheduling-vps`.
- Jadwal libur per-karyawan, jam masuk/pulang per-karyawan, validasi silang
  izin↔libur, libur nasional, serta checkpoint Lapor Progress/Kembali Kerja
  **LIVE**.
- Media baru memakai Cloudflare R2. URL production adalah
  `https://media.kanopitangerang.co.id`; jangan kembali memakai `r2.dev` untuk
  upload baru. Foto lama tetap di lokasi asalnya.
- Kerja Hari Libur + final hardening payroll/security **LIVE**: aktivasi
  membatalkan libur tanpa pengganti dan menjadi hari kerja biasa; potongan dan
  lembur tidak dihitung ganda; KPI maksimal 100%; koreksi nominal dan pengelolaan
  payroll seluruh karyawan Owner-only; slip pribadi memakai ownership check;
  Admin tidak dapat mengubah field sensitif atau menaikkan role ke Owner.
- Masa kerja Kasbon memakai urutan
  `tanggal_bergabung → tgl_masuk_kerja → created_at`. Tidak ada backfill otomatis.
  Audit tiga tanggal bersifat read-only dan backfill tetap memerlukan daftar nama
  + persetujuan Bos terpisah.
- **SWE Fase 1 (Fondasi Tahap Produksi) LIVE per 16 Agustus 2026:** tahap produksi
  (potong/las/cat/kirim/instal) otomatis tergenerasi dari template saat RAB deal
  jadi project; halaman `/tahap-master` + `/template-tahap` (Owner); mulai/selesai
  tahap dengan PIC dipilih manual (rekomendasi skill otomatis = Fase 2, belum
  dikerjakan). SQL production sudah dijalankan Bos, sudah push+deploy.

### Pekerjaan aktif — Deterministic Guardrail

Bos memilih workflow **Direct/Single-session Claude + Deterministic Guardrail**:
satu task, satu worktree, satu writer, verifikasi mekanis oleh Hermes, dan maksimal
satu reviewer read-only hanya untuk payroll/auth/SQL/cron/arsitektur berisiko.

Plan Fondasi Tahap 1 dan Verification Gate V2 disimpan lokal di `.hermes/plans/`.
Pekerjaan memakai satu writer per worktree dan tidak memakai continuation Claude.

- Fondasi manifest + `scripts/canopi-check` tersedia di remote `main` melalui
  `20c5f3d`; dua workflow FTP saat rilis fondasi gagal karena
  `Timeout (control socket)`, tetapi production tetap sehat (`/login` 200).
- Hermes Deploy Watchdog aktif sebagai cron script-only setiap 5 menit
  (`no_agent`, tanpa token LLM) dan hanya melapor bila workflow `main` baru selesai.
- Verification workflow dibuat dengan RED→GREEN di `feature/verification-gate`
  (`d7e5432`), tanpa FTP, secret production, DB, migration, atau `.env`.
- Gate lokal final setelah integrasi: 45 tes, 209 file PHP, 226 route,
  119 Blade — PASS.
- Bukti GitHub branch terisolasi:
  - GREEN awal: run `31894530711` — success.
  - NEGATIVE manifest drift: run `31894619793` — failure sesuai desain,
    deploy count 0.
  - Probe dihapus dan pulih GREEN: run `31894714432` — success.
- Selama pembuktian branch terisolasi, final tree probe identik dengan branch GREEN
  dan `origin/main` tetap `20c5f3d` sampai izin rilis diberikan.
- Task 5 sudah **LIVE di `main`**: `deploy.yml` memanggil reusable `verify`
  dan job FTP memakai `needs: verify`. Blok runtime FTP/cache lama terkunci
  byte-identik dengan SHA-256 `a11c7864f04f4bc1e6c3475f211aa051f24bef5f4e9ababa5076e941f55ffde2`.
- Release gate commit `cd6bdca`: GitHub Actions run `31895509235` sukses;
  `verify / Verification Guardrail` success (44 detik), lalu `Deploy via FTP`
  success (24 detik). Smoke production: `/login` 200 dan `/` 302.
- Deterministic Guardrail + verification-before-deploy **CLOSED**. Tahap terpisah
  yang belum dilakukan: MariaDB test lokal, preview VPS, atau feature flag.

### Utang aktif / resume point

1. **Kalibrasi RAB tetap prioritas roadmap #1.** Data masih tes dan belum boleh
   dipakai ke customer asli sampai kalibrasi tuntas. PA-DUTA 4x8 masih kurang foto
   bar #12 untuk menutup validasi target 9 batang. Luas referensi yang benar sekitar
   40 m², bukan bounding-box 51 m².
2. **DenahEditor:** backlog setelah redesign Tambah Tiang adalah pola
   drag=pindah/tekan-tahan=menu untuk Support/Frame, Kelompok C saran-kotak-2-arah,
   dan investigasi tombol “Lanjut → Finalisasi” di HP hanya jika ada reproduksi
   video. Jangan menambal bug HP tanpa bukti.
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
7. **SWE Fase 2 (skill karyawan + rekomendasi PIC otomatis) — brainstorming
   SEDANG JALAN per 21 Agustus 2026, BELUM ditulis ke spec file, BELUM
   disetujui Elvan.** Kalau lanjut sesi baru, **jangan brainstorming ulang
   dari nol** — pakai keputusan yang sudah dibahas ini, cukup presentasikan
   ulang & minta approve:
   - `rab_skill` dapat kolom baru `default_role` (Tukang/Kenek/Tukang&Kenek/
     Manual saja), diatur Owner di halaman Kelola Produktivitas yang sudah
     ada (bukan halaman baru).
   - Tabel baru `user_skill` (user_id, rab_skill_id, sumber: default_role
     atau manual).
   - Halaman Karyawan (form edit yang sudah ada) dapat tambahan kecil:
     checklist skill aktif, yang otomatis kecentang (dari jabatan) ditandai
     beda dari yang manual.
   - `rab_jenis_kerja.skill_default` (sekarang teks bebas, rawan typo)
     diubah jadi **dropdown** pilih dari daftar `rab_skill` — data lama yang
     belum cocok gak dianggap error, cuma rekomendasinya kosong sampai
     Owner buka & simpan ulang pakai dropdown baru.
   - Rekomendasi PIC ("Cari PIC") dihitung LIVE di layar (tanpa reload,
     mirip kalkulator RAB), pakai field qty+tanggal target yang SUDAH ADA
     di panel "Mulai Tahap" Fase 1 — bukan field baru. `target_hari` =
     selisih hari ini ke tanggal target. Formula jumlah orang: sama persis
     yang sudah dikunci di spec bagian 4-B (ceil dari tim default × rasio
     estimasi/target, minimal 1).
   - Ketersediaan karyawan ("lagi sibuk atau nggak") dicek dari status
     `project_tahap_pic`-nya masih "Sedang Berjalan" di tahap MANAPUN —
     BUKAN overlap tanggal (tanggal target sering kosong di Fase 1, gak
     bisa diandalkan).
   - Sifatnya tetap SARAN (badge ✅/🔴), bukan gembok — endpoint
     `mulaiTahap()` yang sudah ada dari Fase 1 TIDAK PERLU diubah sama
     sekali, cuma tampilan checklist-nya yang diperkaya lewat endpoint
     kalkulasi baru (read-only, mirip pola `/rab-blok/hitung`).
   - **Belum dibahas/diputuskan:** urutan tampilan daftar kandidat PIC,
     detail UI tombol "Hitung Saran", dan batas scope resmi Fase 2 (bagian
     terakhir sebelum spec ditulis).

   Spec penuh (semua fase): `docs/superpowers/specs/2026-08-16-swe-smart-work-engine-design.md`
   bagian 6. Plan Fase 1 (sudah selesai, referensi pola):
   `docs/superpowers/plans/2026-08-16-swe-fase1-tahap-produksi.md`.

### Pelajaran aktif dari kronologi (jangan hilang saat arsip tidak dibaca)

- iOS Safari: `position:fixed` dapat rusak bila bersarang di `.page-content`
  (`overflow-y:auto` + `-webkit-overflow-scrolling:touch`). Overlay fullscreen
  perlu direparent ke `document.body`, lalu dikembalikan saat selesai.
- Debug production memakai `Log::error()` sementara karena `LOG_LEVEL=error`
  menyaring `info/debug`; hapus instrumentation setelah bukti didapat.
- Cron/notifikasi harus idempotent: record existing tidak boleh memicu kirim ulang.
- GET harus read-only; pembuatan kode/pesan wajib melalui POST atau cron yang jelas.
- Perubahan schema production selalu SQL idempotent manual di phpMyAdmin sebelum
  push kode yang bergantung padanya; verifikasi kolom/index dari hasil nyata.
- Smoke-test route bukan E2E bisnis. Jangan membuat slip, mengaktifkan karyawan,
  mengirim kode, atau menulis data production hanya untuk pengujian tanpa izin.

### Aturan update status ke depan

- Ganti/ringkas bagian **Pekerjaan aktif** dan **Utang aktif**; jangan menambahkan
  narasi sesi panjang terus-menerus ke file ini.
- Kronologi detail yang selesai dipindahkan ke file baru di `docs/history/` dan
  ditautkan dari sini.
- Keputusan bisnis aktif, risiko production, dan resume point harus tetap ada di
  file ini agar sesi baru tidak perlu membaca arsip penuh.
