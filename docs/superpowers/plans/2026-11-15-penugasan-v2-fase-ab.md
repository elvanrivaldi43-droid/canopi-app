# Penugasan v2 — Fase A + B Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Project manual bisa punya tahap (diketik tangan, atau dari template), dan daftar project tersusun menurut prioritas + deadline, dengan prioritas hanya bisa diubah Owner/Admin Ops.

**Architecture:** Fase A menambah aksi tahap manual di `ProjectController` + aturan murni di `TahapProduksiService`, tanpa perubahan skema. Fase B menambah satu kolom `projects.prioritas` (VARCHAR), aturan murni di service baru `PrioritasProject`, dan mengubah urutan serta tampilan `projects.index`. Layar Rencana Besok, tabel `penugasan_harian`/`konfirmasi_hadir`, cron, dan Telegram TIDAK disentuh (itu Fase C).

**Tech Stack:** Laravel 13 / PHP 8.3, Blade, tes PHP polos (`php tests/.../test_x.php`, pola `$check`), guardrail `scripts/canopi-check`.

**Spec:** `docs/superpowers/specs/2026-11-14-penugasan-v2-project-dulu-design.md` (bagian 2b, 3.1, 3.2, 4, 5 Fase A & B). Fase C, C2, D, E = rencana terpisah.

## Global Constraints

- SQL skema: manual di phpMyAdmin, idempotent (`ADD COLUMN IF NOT EXISTS`), tanpa `UPDATE`/`DELETE` (guardrail menolaknya), dijalankan Elvan SEBELUM push kode yang bergantung padanya.
- Tes baru WAJIB didaftarkan di `tests/guardrail/manifest.json` di commit yang sama (sisipkan manual, jangan `json.dump` + sort ulang). Salin flag entri `tests/swe/test_tahap_produksi_service.php` untuk tes murni dan entri `tests/keamanan/test_akses_izin.php` untuk tes route.
- Blade: tanpa emoji (pakai SVG), tanpa `confirm()` pada tombol (pakai pola tekan-dua-kali atau tanpa konfirmasi untuk aksi aman), stage per-nama (jangan `git add -A`).
- Prioritas: tepat 4 nilai `urgent`, `prioritas`, `normal`, `ditahan` (urutan ini = peringkat). Default `normal`. Hanya level 1 (Owner) dan 2 (Admin Operasional) boleh mengubah.
- Deadline = `projects.tgl_selesai_target` (kolom sudah ada).
- "Lama" tahap TIDAK disimpan: diturunkan dari `tanggal_mulai_target`/`tanggal_selesai_target`. Lama dihitung INKLUSIF (mulai 10, lama 3 → selesai 12). *(keputusan Claude, bukan Elvan)*
- Ditahan pada Fase B = nilai prioritas level PROJECT yang disetel manual. Ditahan per TAHAP beralasan = Fase C2, bukan sekarang. *(keputusan Claude)*
- Akses tahap manual: level 1,2,3 (grup route `/projects` yang sudah ada).

## Review Focus

1. Tambah tahap ke project berstatus `selesai`/`dibatalkan` → ditolak dengan pesan jelas (bukan diam-diam menempel).
2. Hapus tahap yang sudah dirujuk `penugasan_harian`, atau sudah berstatus bukan `belum`, atau punya PIC → ditolak dengan pesan, bukan error 500 (FK).
3. "Terapkan template" pada project yang SUDAH punya tahap → tidak menggandakan tahap.
4. Nilai prioritas asing dikirim lewat POST (mis. `"urgentt"`, kosong) → ditolak, nilai lama tidak berubah.
5. Mandor (level 3) / level lain mencoba mengubah prioritas → ditolak di route (bukan hanya tombol disembunyikan).

---

### Task 1: Aturan murni tahap manual (tanggal + hapus)

**Files:**
- Modify: `app/Services/TahapProduksiService.php`
- Create: `tests/swe/test_tahap_manual.php`
- Modify: `tests/guardrail/manifest.json`

**Interfaces:**
- Produces: `TahapProduksiService::lengkapiTanggal(?string $mulai, ?string $selesai, ?int $lama): array` → `['mulai' => 'Y-m-d', 'selesai' => 'Y-m-d', 'lama' => int]`; melempar `\InvalidArgumentException` (pesan Indonesia, layak ditampilkan ke user) bila kurang dari dua nilai terisi, `lama < 1`, atau `selesai < mulai`.
- Produces: `TahapProduksiService::alasanTolakHapus(string $status, int $jumlahPic, int $jumlahPenugasan): ?string` → `null` bila boleh hapus, selain itu kalimat alasan.
- Produces: `TahapProduksiService::alasanTolakTambah(string $statusProject): ?string` → `null` hanya bila status project termasuk `persiapan`, `pengerjaan`, `menunggu_dp`, `garansi`.

- [ ] **Step 1: Tulis tes `tests/swe/test_tahap_manual.php`** (pola `$check` seperti `test_tahap_produksi_service.php`) dengan kasus:
  - mulai+selesai → lama inklusif: `('2026-11-10','2026-11-12',null)` → lama 3
  - mulai+lama → selesai: `('2026-11-10',null,3)` → selesai `2026-11-12`; lama 1 → selesai = mulai
  - selesai+lama → mulai: `(null,'2026-11-12',3)` → mulai `2026-11-10`
  - ketiganya diisi tak konsisten (`'2026-11-10','2026-11-12',5`) → tanggal menang, lama jadi 3
  - hanya satu nilai → `InvalidArgumentException`; `lama = 0` → exception; `selesai < mulai` → exception; string tanggal sampah → exception
  - `alasanTolakHapus('belum',0,0)` null; `('sedang',0,0)`, `('selesai',0,0)`, `('belum',1,0)`, `('belum',0,2)` masing-masing bukan null
  - `alasanTolakTambah('selesai')` dan `('dibatalkan')` bukan null; `'persiapan'`, `'pengerjaan'`, `'menunggu_dp'`, `'garansi'` null
- [ ] **Step 2: Jalankan** `php tests/swe/test_tahap_manual.php` — Expected: FAIL (method belum ada).
- [ ] **Step 3: Implementasikan ketiga method** di `TahapProduksiService` (pakai `Carbon\Carbon`; validasi format `Y-m-d` dengan `Carbon::createFromFormat` + cek round-trip supaya `2026-02-31` ditolak).
- [ ] **Step 4: Jalankan tes** — Expected: semua PASS, `=== SEMUA TES LULUS ===`.
- [ ] **Step 5: Daftarkan tes di manifest, lalu** `php scripts/canopi-check` — Expected: hijau.
- [ ] **Step 6: Commit** `git add app/Services/TahapProduksiService.php tests/swe/test_tahap_manual.php tests/guardrail/manifest.json` → "Tahap manual: aturan tanggal (dua dari tiga) dan penjaga tambah/hapus".

---

### Task 2: Aksi tambah / hapus / terapkan-template tahap + UI di halaman project

**Files:**
- Modify: `app/Http/Controllers/ProjectController.php`
- Modify: `routes/web.php` (grup `level:1,2,3`, dekat baris route `projects.tahap.mulai`)
- Modify: `resources/views/projects/show.blade.php` (bagian tahap, sekitar baris 156-160)
- Modify: `app/Services/TahapProduksiService.php` (hanya agar `generateUntukProject` tetap dipakai ulang; tidak diubah perilakunya)

**Interfaces:**
- Consumes: `lengkapiTanggal`, `alasanTolakHapus`, `alasanTolakTambah` (Task 1); `TahapProduksiService::generateUntukProject(Project): int` (sudah ada).
- Produces route (nama persis): `projects.tahap.tambah` = `POST /projects/{project}/tahap`; `projects.tahap.hapus` = `DELETE /project-tahap/{projectTahap}`; `projects.tahap.template` = `POST /projects/{project}/tahap/template`.
- Produces: `ProjectController::tambahTahap(Request, Project): RedirectResponse`, `hapusTahap(ProjectTahap): RedirectResponse`, `terapkanTemplate(Project): RedirectResponse`.

- [ ] **Step 1: Route** — tiga route di atas dalam grup yang sudah ada (`auth`, `level:1,2,3`).
- [ ] **Step 2: `tambahTahap`** — validasi `nama_tahap` required|string|max:150, `tanggal_mulai_target` nullable|date, `tanggal_selesai_target` nullable|date, `lama_hari` nullable|integer|min:1|max:365. Cek `alasanTolakTambah($project->status)`. Bila ada minimal dua dari tiga tanggal/lama → `lengkapiTanggal` (tangkap `InvalidArgumentException` → `back()->withErrors`); bila kurang dari dua → simpan tahap tanpa tanggal target (tanggal opsional di sini; deadline tegas ada di project). `urutan` = `max(urutan)+1` project itu (0 bila kosong), `status` `belum`, `tahap_master_id` NULL, `dibuat_oleh` `Auth::id()`.
- [ ] **Step 3: `hapusTahap`** — hitung `ProjectTahapPic` dan `DB::table('penugasan_harian')->where('project_tahap_id', ...)->count()`, panggil `alasanTolakHapus`; bila ditolak → `back()->with('warning', alasan)`; selain itu hapus + `with('success', ...)`.
- [ ] **Step 4: `terapkanTemplate`** — bila project sudah punya tahap → `back()->with('warning', 'Project ini sudah punya tahap.')` (tanpa menggandakan). Selain itu `generateUntukProject`; hasil 0 → `with('warning', 'Tidak ada template yang cocok dengan jenis project "X". Tambahkan tahap manual.')`.
- [ ] **Step 5: UI** di `show.blade.php`: ganti paragraf kosong (baris 156-157) jadi kotak "Belum ada tahap" + tombol **Terapkan Template**; di bawah daftar tahap (selalu tampil, kecuali project `selesai`/`dibatalkan`) form **Tambah Tahap**: nama, tanggal mulai target, tanggal selesai target, lama (hari) dengan hint "isi dua dari tiga"; tombol hapus kecil per tahap hanya untuk `status === 'belum'`. Ikuti gaya inline-style gelap yang sudah dipakai berkas itu, input `font-size:16px` (cegah zoom iOS).
- [ ] **Step 6: Verifikasi** — `php artisan route:list --name=projects.tahap` menampilkan 3 route baru; `php scripts/canopi-check --full` (Blade compile) hijau.
- [ ] **Step 7: Commit** `git add` tiga file di atas → "Project: tambah/hapus tahap manual + tombol terapkan template".

---

### Task 3: Project manual wajib deadline + tahap otomatis dari template

**Files:**
- Modify: `app/Http/Controllers/ProjectController.php` (`store`, baris 72-130)
- Modify: `resources/views/projects/create.blade.php` (baris 48 dan 108)

**Interfaces:**
- Consumes: `TahapProduksiService::generateUntukProject(Project): int`.

- [ ] **Step 1:** Di `store()` ubah aturan `tgl_selesai_target` jadi `required_without:id_lead|nullable|date` dengan pesan kustom "Tanggal selesai target wajib diisi untuk project manual (tanpa lead)." Project dari lead tetap boleh kosong.
- [ ] **Step 2:** Di dalam transaksi `store()`, setelah `Project::create`, panggil `(new TahapProduksiService)->generateUntukProject($project)` (hasil 0 tidak apa-apa).
- [ ] **Step 3:** Di `create.blade.php`: tambah `<datalist id="jenis-project-opsi">` berisi `Project::$jenisProjectOptions` dan kaitkan ke input `jenis_project` lewat `list=`, supaya ketikan manual cocok persis dengan nama template; tandai input tanggal selesai target `required` bila tidak ada `$lead`.
- [ ] **Step 4: Verifikasi** — `php scripts/canopi-check --full` hijau. Cek manual (dicatat untuk Elvan di Task 7): buat project manual jenis "Kanopi Standar" → halaman project langsung punya tahap dari template.
- [ ] **Step 5: Commit** `git add` dua file → "Project manual: deadline wajib, tahap otomatis dari template".

---

### Task 4: Kolom prioritas + aturan murni `PrioritasProject`

**Files:**
- Create: `docs/sql/2026-11-15-project-prioritas.sql`
- Create: `app/Services/PrioritasProject.php`
- Create: `tests/swe/test_prioritas_project.php`
- Modify: `app/Models/Project.php` (tambah `'prioritas'` ke `$fillable`)
- Modify: `tests/guardrail/manifest.json`

**Interfaces:**
- Produces: `PrioritasProject::NILAI` = `['urgent','prioritas','normal','ditahan']`; `PrioritasProject::LABEL` (nilai → "Urgent"/"Prioritas"/"Normal"/"Ditahan"); `PrioritasProject::WARNA` (nilai → kode hex; urgent merah, prioritas kuning-oranye, normal abu, ditahan abu gelap).
- Produces: `PrioritasProject::valid(?string $nilai): bool`; `PrioritasProject::bolehUbah(int $level): bool` (true hanya untuk 1 dan 2); `PrioritasProject::sisaHari(?string $target, string $hariIni): ?int` (null bila target kosong; negatif bila lewat); `PrioritasProject::urutanSql(): string` → `"FIELD(prioritas,'urgent','prioritas','normal','ditahan')"`.

- [ ] **Step 1: SQL** `ALTER TABLE projects ADD COLUMN IF NOT EXISTS prioritas VARCHAR(12) NOT NULL DEFAULT 'normal';` dengan komentar kepala seperti `2026-10-26-absensi-pulang-cepat.sql` ("Jalankan SEBELUM push"). VARCHAR, bukan ENUM (nilai bisa bertambah tanpa ALTER). Tanpa UPDATE/DELETE.
- [ ] **Step 2: Tulis tes** `tests/swe/test_prioritas_project.php`: `valid('urgent')` true, `valid('urgentt')` false, `valid('')` false, `valid(null)` false; `bolehUbah` true untuk 1 dan 2, false untuk 3,4,5,6,7; `sisaHari('2026-11-20','2026-11-15')` = 5, `('2026-11-15','2026-11-15')` = 0, `('2026-11-10','2026-11-15')` = -5, `(null,'2026-11-15')` null; `urutanSql()` persis string di atas; `array_keys(LABEL) === NILAI` dan `array_keys(WARNA) === NILAI`.
- [ ] **Step 3: Jalankan** — Expected: FAIL (kelas belum ada).
- [ ] **Step 4: Implementasikan `PrioritasProject`** (kelas statik murni, tanpa DB/facade, gaya `PenugasanAturan`) dan tambah `'prioritas'` ke `$fillable`.
- [ ] **Step 5: Jalankan tes** — Expected: semua PASS. Daftarkan di manifest, `php scripts/canopi-check` hijau.
- [ ] **Step 6: Commit** `git add docs/sql/2026-11-15-project-prioritas.sql app/Services/PrioritasProject.php app/Models/Project.php tests/swe/test_prioritas_project.php tests/guardrail/manifest.json` → "Project: kolom prioritas + aturan murni PrioritasProject".

---

### Task 5: Ubah prioritas (Owner + Admin Ops saja)

**Files:**
- Modify: `app/Http/Controllers/ProjectController.php`
- Modify: `routes/web.php` (grup BARU `['auth','level:1,2']`, di luar grup `level:1,2,3`)
- Modify: `resources/views/projects/show.blade.php` (kartu kecil di dekat status project)
- Create: `tests/keamanan/test_akses_prioritas.php`
- Modify: `tests/guardrail/manifest.json`

**Interfaces:**
- Consumes: `PrioritasProject::valid`, `LABEL`, `NILAI`.
- Produces: route `projects.prioritas` = `PATCH /projects/{project}/prioritas`; `ProjectController::ubahPrioritas(Request, Project): RedirectResponse`.

- [ ] **Step 1: Tulis tes route** (pola `tests/keamanan/test_akses_izin.php`, baca `php artisan route:list --json`): `projects.prioritas` memuat middleware `level:1,2` dan TIDAK ada di grup `level:1,2,3`; tiga route tahap dari Task 2 memuat `level:1,2,3`.
- [ ] **Step 2: Jalankan** — Expected: FAIL (route belum ada).
- [ ] **Step 3: Route + `ubahPrioritas`** — validasi `prioritas` dengan `PrioritasProject::valid` (tidak valid → `back()->withErrors`, nilai lama tidak berubah); simpan; `with('success', 'Prioritas diubah ke X')`.
- [ ] **Step 4: UI** — di `show.blade.php`, hanya bila `PrioritasProject::bolehUbah(auth()->user()->level)`: `<select>` 4 nilai + tombol Simpan (form PATCH). Untuk level lain tampilkan lencana prioritas saja (read-only).
- [ ] **Step 5: Jalankan tes route** — Expected: PASS. Daftarkan di manifest; `php scripts/canopi-check --full` hijau.
- [ ] **Step 6: Commit** `git add` kelima berkas → "Project: ubah prioritas khusus Owner dan Admin Ops".

---

### Task 6: Daftar project berurut menurut antrean + deadline

**Files:**
- Modify: `app/Http/Controllers/ProjectController.php` (`index`, baris 27-58)
- Modify: `resources/views/projects/index.blade.php` (kartu project, sekitar baris 56-90)

**Interfaces:**
- Consumes: `PrioritasProject::urutanSql()`, `LABEL`, `WARNA`, `sisaHari`.

- [ ] **Step 1:** Di `index()`, ganti `orderByRaw("FIELD(status,...)")` jadi tiga urutan: `orderByRaw("status IN ('persiapan','pengerjaan') DESC")`, `orderByRaw(PrioritasProject::urutanSql())`, `orderBy('created_at')` (yang memesan duluan di atas). Project selesai/garansi/dibatalkan jatuh ke bawah, urutan lama diganti created_at menurun HANYA untuk kelompok itu bila mudah; bila tidak, biarkan satu aturan sama — tidak ada yang bergantung padanya.
- [ ] **Step 2:** Di kartu project tampilkan lencana prioritas (warna dari `WARNA`; `normal` boleh tanpa lencana) dan baris deadline: "Deadline 20 Nov — sisa 5 hari", merah dan "terlambat N hari" bila `sisaHari < 0`, "deadline belum diisi" (amber) bila kosong dan status aktif. Tanpa emoji; ganti pin emoji `📍` yang sudah ada hanya bila menyentuh baris itu (jangan perluas lingkup).
- [ ] **Step 3: Verifikasi** — `php scripts/canopi-check --full` hijau. `php -l` kedua berkas.
- [ ] **Step 4: Commit** `git add` dua file → "Daftar project: urut prioritas + tanggal pesan, tampilkan deadline dan sisa hari".

---

### Task 7: Rollout dan serah terima

**Files:**
- Modify: `CLAUDE.md` (bagian Status Terkini, ringkas)
- Modify: `docs/superpowers/specs/2026-11-14-penugasan-v2-project-dulu-design.md` (status: Fase A+B selesai/live)

- [ ] **Step 1:** `php scripts/canopi-check --full` hijau; `git log origin/main..HEAD` hanya berisi commit rencana ini + commit spec yang tertahan.
- [ ] **Step 2 (Elvan):** jalankan `docs/sql/2026-11-15-project-prioritas.sql` di phpMyAdmin; verifikasi `SHOW COLUMNS FROM projects LIKE 'prioritas'` mengembalikan satu baris default `normal`. **Push dilarang sebelum ini** (halaman daftar project akan error bila kolom belum ada).
- [ ] **Step 3:** push; cek deploy nyata dengan `curl` (POST `/projects/1/tahap` tanpa token → 419; GET `/projects/1/prioritas` → 405, bukan 404), bukan hanya Actions hijau.
- [ ] **Step 4 (checklist uji untuk Elvan, di HP):** (a) buka project joni → bagian tahap punya form Tambah Tahap; tambah tahap dengan mulai+lama, selesai terisi otomatis benar; hapus tahap uji → berhasil, tahap yang sudah ditugaskan → pesan penolakan; (b) buat project manual jenis "Kanopi Standar" tanpa tanggal selesai → ditolak; dengan tanggal → tahap template muncul; (c) akun Admin Ops mengubah prioritas jadi Urgent → project naik ke atas daftar; akun Mandor tidak melihat pilihan prioritas; (d) project tanpa deadline tampil amber "deadline belum diisi".
- [ ] **Step 5:** Perbarui CLAUDE.md (status Penugasan v1 live tanpa crontab, hasil uji 15 Nov, Fase A+B, tahap uji "UJI Potong & Las"/"UJI Finishing" di project joni masih menunggu dibersihkan) dan status spec.
- [ ] **Step 6: Commit** `git add CLAUDE.md docs/superpowers/specs/2026-11-14-penugasan-v2-project-dulu-design.md` → "docs: status Penugasan v2 Fase A+B".

---

## Self-review

- **Spec coverage:** 2b input tahap manual (dua dari tiga) → T1-T2; project manual tanpa tahap (bagian 4, a+b) → T2 (tombol template + manual) + T3 (otomatis); deadline wajib → T3; empat prioritas + pin Owner/Admin Ops → T4-T5; urutan FIFO + prioritas + deadline/sisa hari (3.2) → T6. Tidak dicakup dengan sengaja (Fase C/C2/D/E): layar project→tahap→pekerja, Gerbang Kesiapan, Ditahan per tahap, acknowledge Urgent, konsekuensi uang.
- **Konsistensi nama:** `lengkapiTanggal`, `alasanTolakHapus`, `alasanTolakTambah`, `PrioritasProject::{NILAI,LABEL,WARNA,valid,bolehUbah,sisaHari,urutanSql}`, route `projects.tahap.{tambah,hapus,template}` dan `projects.prioritas` dipakai konsisten antar task.
- **Proporsi:** tak ada badan fungsi di rencana; hanya tanda tangan, nilai dari spec, dan nama tes.
