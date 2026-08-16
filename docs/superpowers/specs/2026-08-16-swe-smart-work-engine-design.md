# Desain: SWE (Smart Work Engine) — Manajemen Tahap Produksi

**Tanggal:** 16 Agustus 2026
**Status:** Disetujui (brainstorming) — siap ke tahap rencana implementasi
**Pemilik:** Elvan (owner, non-teknis)
**Konteks:** SWE adalah roadmap #6 (dikunci "PALING AKHIR"), **sengaja diloncat urutannya** atas keputusan Elvan — kalibrasi RAB (roadmap #1, belum tuntas) menyusul belakangan. SWE = "Manufacture Tracking" yang pernah disebut di catatan gap sebelumnya (dikonfirmasi 1 hal yang sama). Belum ada rancangan sebelumnya — modul ini didesain dari nol lewat brainstorming penuh.

**Prinsip arsitektur yang WAJIB dijaga (dari CLAUDE.md, jangan dilanggar):** tabel produktivitas RAB (`rab_jenis_kerja`, dipakai untuk **harga jual**) dan tabel tahap SWE (dipakai untuk **manajemen produksi**) adalah **DUA TABEL TERPISAH** — SWE boleh MEMBACA `rab_jenis_kerja`/`rab_skill`, dan boleh MENGUSULKAN perubahan ke situ (lewat alur approval eksplisit), tapi tidak pernah digabung strukturnya.

---

## 1. Ringkasan

SWE memecah 1 `project` (yang sudah otomatis terbuat saat RAB di-approve jadi "deal") menjadi **tahap-tahap produksi** (potong, las, cat, kirim, instalasi, dst) yang bisa dilacak statusnya, dikasih PIC (penanggung jawab) lewat rekomendasi berbasis skill+kapasitas, dan dari situ membuka 3 kemampuan baru:

1. **Tahap Produksi** — fondasi: tiap project punya daftar tahap dengan status/tanggal/PIC, otomatis terbentuk dari template sesuai jenis project.
2. **Kapasitas Tim** — tau kapan tim kekurangan/kelebihan orang per skill, notif Telegram seketika (bukan cron) begitu terdeteksi defisit, dan aksi konkret buat karyawan yang nganggur (pindah tahap lain, atau diliburkan).
3. **Evaluasi Produktivitas** — data tahap yang selesai dijadikan usulan kalibrasi ke `rab_jenis_kerja` (durasi & jumlah orang), tapi HARUS di-approve manual oleh Owner sebelum berlaku.

Skill karyawan pakai daftar `rab_skill` yang **sudah ada** (dipakai juga untuk tarif upah RAB) — tidak bikin daftar skill baru.

---

## 2. Keputusan yang dikunci

| # | Keputusan | Detail |
|---|---|---|
| 1 | Titik pemicu tahap | **Bukan** langkah manual baru. Tahap otomatis ke-generate persis di titik yang sudah ada: `RabController::approve()` → status "deal" → `Project::create()`. Kalau gagal generate tahap (jenis project tidak punya template, atau error apapun), project tetap kebuat normal — SWE tidak boleh mengganggu alur deal. |
| 2 | Data lama | Tidak ada migrasi/backfill — project yang ada sekarang masih data dummy (dikonfirmasi Elvan), belum ada project nyata yang perlu diberi tahap retroaktif. |
| 3 | Urutan tahap | **Tidak wajib berurutan.** Alur kerja nyata di lapangan tidak baku (dikonfirmasi Elvan) — Supervisor bisa mulai tahap manapun kapan saja, sistem tidak nge-gate tahap B karena tahap A belum selesai. |
| 4 | Siapa update status tahap | **Supervisor/Mandor (level 3)** untuk SEMUA tahap (bukan dipisah fab vs instalasi) — sudah punya akses pipeline+RAB, tidak menambah level akses baru. Diperluas nanti kalau ada bukti kesulitan koordinasi, bukan didesain dari awal. |
| 5 | Skill karyawan — sumbernya | **Gabungan role-default + manual.** Skill "standar" (misal las biasa, potong) otomatis nempel sesuai jabatan (tukang = semua skill standar, kenek = hanya skill kategori potong/haluskan/cat). Skill "khusus" (misal las stainless — tidak semua tukang bisa) **harus dicentang manual per-karyawan**, karena skill unik ini beda-beda per individu, tidak bisa ditebak dari jabatan. |
| 6 | Skill karyawan — daftar sumbernya | **Reuse `rab_skill`** (tabel yang sudah ada, dipakai untuk tarif upah RAB) sebagai satu-satunya daftar nama skill — dikonfirmasi Elvan itu daftar yang sama, tidak bikin daftar skill baru. |
| 7 | Level rekomendasi PIC | **Full hitung: jumlah orang + durasi**, bukan cuma filter skill. Sistem baca `rab_jenis_kerja` (produktivitas + tim default, DIBACA saja, tidak diubah) untuk menyarankan jumlah tukang/kenek sesuai target durasi yang diminta Supervisor. |
| 8 | Sifat rekomendasi PIC | **Saran, bukan gembok.** Karyawan yang skill-nya tidak cocok tetap muncul di daftar (ditandai "skill gak cocok"), tetap bisa dipilih manual — keputusan akhir di tangan Supervisor untuk situasi darurat. |
| 9 | Kapasitas Tim — bentuk | Halaman `/kapasitas-tim` (dashboard) **+ notif Telegram event-driven** (bukan cron terjadwal) — persis pola izin/nego yang sudah ada: begitu ada tahap baru/berubah tanggal yang membuat demand skill melebihi supply, notif langsung terkirim saat itu juga. Tidak notif ulang saat defisit reda (anti-spam). |
| 10 | Evaluasi Produktivitas — approval | **Owner approve manual**, TIDAK full-otomatis update `rab_jenis_kerja`. Sistem cuma menyodorkan usulan (standar lama vs usulan baru vs jumlah sample) di halaman `/evaluasi-produktivitas` (Owner-only) — 1 sample aneh (misal proyek yang kena hujan terus) tidak boleh langsung menggeser harga jual semua RAB berikutnya. |
| 11 | Evaluasi Produktivitas — ambang sample | Minimal **3 tahap selesai** per jenis kerja sebelum usulan muncul — konsisten dengan pola kalibrasi manual yang sudah dipakai Elvan selama ini ("ambil dari 3-5 project, rata-ratakan"), SWE cuma mengotomatiskan pengumpulan datanya. |
| 12 | Karyawan nganggur — gaji saat diliburkan | **Tidak digaji** hari itu (status absen baru), tapi **tetap dapat uang makan penuh** kalau karyawan itu punya jatah uang makan — reuse kolom `users.uang_makan` dan pola perhitungan yang sudah ada di `KerjaHariLiburService` (izin/cuti tetap dapat UM penuh). |
| 13 | Karyawan nganggur — beda dari jadwal libur | Status "diliburkan karena nganggur" ini **keputusan perusahaan** (gak ada kerjaan buat dia), beda konsep dari `jadwal_libur`/`hari_libur_default` yang itu jatah libur milik karyawan sendiri. Tidak boleh tercampur. |
| 14 | Karyawan nganggur — ambang flag pengurangan tim | **14 hari kerja berturut-turut** nganggur/diliburkan baru memicu flag + 1x notif Telegram ke Owner. Sekali per kejadian (tidak diulang tiap hari). Ini CUMA laporan/sinyal — tidak ada alur approval formal di sistem, keputusan pengurangan karyawan 100% manual di luar sistem (sensitif, terkait hukum ketenagakerjaan). |

---

## 3. Struktur data

### Tabel baru

**`tahap_master`** — daftar jenis tahap kerja yang Owner kelola (halaman baru `/tahap-master`, pola mirip `/addon`)
- `id`, `nama` (mis. "Potong Besi", "Las Rangka", "Cat", "Kirim ke Lokasi", "Instalasi")
- `rab_jenis_kerja_id` (FK nullable → `rab_jenis_kerja.id`) — link opsional; kalau kosong, tahap ini jadi checklist manual tanpa rekomendasi PIC otomatis
- `tipe` (enum `'fab'|'inst'|null`) — nentuin baca `produktivitas_per_hari`/`jml_tukang`/`jml_kenek` (fab) atau `produktivitas_inst`/`jml_tukang_inst`/`jml_kenek_inst` (inst) dari `rab_jenis_kerja` yang ditautkan
- `urutan` (int, default sorting UI saja — bukan gating), `is_active`

**`template_tahap`** (header) — paket tahap per jenis project
- `id`, `nama` (mis. "Kanopi Standar"), `jenis_project` (string, harus cocok persis ke nilai `Project::jenis_project` — "Kanopi Standar", "Kanopi + Dinding", "Mezzanine", "Pagar", "Tralis", "Tenda Membrane", "Awning", "Carport" — dipakai untuk auto-match pas deal), `is_active`

**`template_tahap_item`** — detail urutan tahap dalam 1 template
- `id`, `template_tahap_id` (FK), `tahap_master_id` (FK), `urutan`

**`project_tahap`** — instance tahap per project aktual (hasil generate dari template, bisa diedit per-project)
- `id`, `project_id` (FK → `projects.id`), `tahap_master_id` (FK), `nama_tahap` (snapshot nama, biar tidak berubah kalau `tahap_master` diedit belakangan)
- `urutan`, `status` (enum `'belum'|'sedang'|'selesai'`, default `'belum'`)
- `qty` (decimal nullable — luas/unit kerjaan, diisi manual pas Supervisor mulai tahap), `satuan` (string nullable, ikut `rab_jenis_kerja.satuan`)
- `tanggal_mulai_target`, `tanggal_selesai_target`, `tanggal_mulai_aktual`, `tanggal_selesai_aktual` (date, nullable — pola sama `projects.tgl_mulai_target/aktual`)
- `jumlah_tukang_disarankan`, `jumlah_kenek_disarankan` (int nullable — hasil kalkulasi awal, dipakai untuk demand forecast Kapasitas Tim SEBELUM PIC benar-benar di-assign)
- `catatan` (text nullable), `dibuat_oleh`, timestamps

**`project_tahap_pic`** (pivot — 1 tahap bisa dipegang lebih dari 1 orang)
- `id`, `project_tahap_id` (FK), `user_id` (FK), `peran` (enum `'tukang'|'kenek'`, konsisten `ProjectTim::jabatan_lapangan`), `ditambahkan_oleh`, `created_at`

**`user_skill`**
- `id`, `user_id` (FK), `rab_skill_id` (FK → `rab_skill.id`), `sumber` (enum `'default_role'|'manual'`), `created_at`

**`evaluasi_produktivitas`**
- `id`, `rab_jenis_kerja_id` (FK)
- `produktivitas_lama`, `produktivitas_usulan` (decimal — snapshot standar saat itu vs hasil hitung data nyata)
- `jml_tukang_lama`, `jml_tukang_usulan`, `jml_kenek_lama`, `jml_kenek_usulan` (int nullable — opsional usulan ubah tim default juga)
- `jumlah_sample` (int), `status` (enum `'pending'|'diterapkan'|'diabaikan'`), `direview_oleh`, `direview_pada`, `created_at`

### Tabel existing yang di-EXTEND (bukan tabel baru)

- **`rab_skill`** — tambah kolom `default_role` (enum `'tukang'|'kenek'|'tukang_kenek'|'manual'`, default `'manual'`) — Owner atur di halaman Kelola Produktivitas yang sudah ada, nentuin skill ini otomatis nempel ke siapa.
- **`absensi.status`** — tambah 1 nilai enum baru: `diliburkan_perusahaan` — reuse kolom `uang_makan_hari_ini`/`gaji_hari_ini` yang sudah ada, cukup pola perhitungan baru di `KerjaHariLiburService` (gaji 0, UM penuh jika ada).

### Tabel existing yang DIBACA SAJA (read-only, tidak berubah struktur)

- `rab_jenis_kerja` — sumber produktivitas + tim default untuk kalkulasi rekomendasi PIC.
- `rab_skill.nama`/`upah_*` — sumber nama skill (upah tidak ditampilkan ke Supervisor non-Owner).
- `projects`, `users` — relasi standar.

---

## 4. Alur kerja

**A. Tahap otomatis terbentuk saat deal**
RAB di-approve → status "deal" → `Project::create()` (kode existing, tidak berubah) → **ditempelin langsung sesudahnya**: cocokkan `jenis_project` project baru ke `template_tahap.jenis_project` yang sama → generate baris `project_tahap` dari `template_tahap_item`-nya (status semua `'belum'`). Tidak ketemu template yang cocok → project tetap kebuat, tanpa tahap otomatis (Supervisor bisa tambah manual dari `tahap_master` belakangan).

**B. Jalankan 1 tahap**
Supervisor buka project → pilih tahap → tombol **"Cari PIC"** → input manual: qty/luas kerjaan + target selesai berapa hari → sistem hitung saran jumlah orang:
- `hari_estimasi_default = qty / rab_jenis_kerja.produktivitas_per_hari` (atau `produktivitas_inst`, sesuai `tahap_master.tipe`)
- `multiplier = hari_estimasi_default / target_hari_diminta`
- `jumlah_tukang_disarankan = ceil(jml_tukang(_inst) × multiplier)`, sama untuk kenek (minimal 1 kalau hasil 0)

Daftar kandidat PIC ditampilkan: karyawan dengan `user_skill` cocok ke skill dari `rab_jenis_kerja.skill_default`, DAN belum terpakai di `project_tahap_pic` lain yang tanggalnya bentrok. **Catatan teknis:** `skill_default` sekarang kolom teks bebas (diisi manual di halaman Kelola Produktivitas, bukan relasi ke `rab_skill.id`) — Fase 2 perlu cocokkan by nama (case-insensitive) atau ketatkan jadi dropdown/FK ke `rab_skill` saat itu, biar typo gak bikin rekomendasi PIC salah/kosong. Yang skill tidak cocok tetap tampil (ditandai), tetap bisa dipilih manual. Supervisor centang siapa saja yang turun → simpan ke `project_tahap_pic`, tahap otomatis jadi `'sedang'`, `tanggal_mulai_aktual` = hari itu.

**C. Selesaikan tahap**
Tombol **"Tandai Selesai"** → `tanggal_selesai_aktual` = hari itu, status `'selesai'`. Ini memicu Bagian E (evaluasi produktivitas) kalau sample sudah cukup.

**D. Halaman kelola master (Owner)**
- `/tahap-master` — kelola daftar jenis tahap + link opsional ke `rab_jenis_kerja` + tipe fab/inst
- `/template-tahap` — susun paket tahap per jenis project
- Skill karyawan — tambahan kecil di halaman Karyawan yang sudah ada (bukan halaman baru): centang skill khusus per orang; skill standar otomatis kebaca dari jabatan lewat `rab_skill.default_role`

**E. Evaluasi Produktivitas**
Begitu tahap ditandai selesai: hitung `produktivitas_aktual = qty / durasi_aktual_hari` (durasi = selisih `tanggal_mulai_aktual`→`tanggal_selesai_aktual` + 1). Kalau jumlah tahap selesai untuk `rab_jenis_kerja` yang sama sudah ≥ 3, buat/refresh baris `evaluasi_produktivitas` (status `'pending'`, `produktivitas_usulan` = rata-rata dari sample terakhir, maksimal 5 sample terbaru — konsisten "3-5 project" dari panduan kalibrasi existing). Owner buka `/evaluasi-produktivitas`, klik **"Terapkan"** (update `rab_jenis_kerja`) atau **"Abaikan"**.

**F. Kapasitas Tim**
Halaman `/kapasitas-tim` (Owner + Supervisor): pilih rentang tanggal, tabel per-skill: **butuh** (agregat `project_tahap` yang overlap rentang — pakai `jumlah_*_disarankan` untuk tahap `'belum'`, pakai jumlah `project_tahap_pic` aktual untuk yang `'sedang'`) vs **tersedia** (`user_skill` aktif dikurangi yang sudah terpakai di tahap lain yang bentrok tanggal). Selisih minus = merah (kurang), plus = hijau (lebih).

Di halaman yang sama, daftar **karyawan nganggur** (aktif, tidak punya `project_tahap_pic` aktif hari itu) dengan 2 aksi:
1. **"Pindahkan ke tahap lain"** — tahap yang kekurangan orang & cocok skill (kebalikan dari kalkulasi Bagian B) → assign langsung
2. **"Liburkan hari ini"** — set `absensi.status = 'diliburkan_perusahaan'`, gaji 0, UM penuh (jika ada)

**Trigger event-driven** (bukan cron): setiap `project_tahap` baru dibuat/diubah tanggalnya (baik otomatis dari Bagian A, atau manual Supervisor) → hitung ulang demand-vs-supply skill terkait di rentang tanggal itu → kalau jadi defisit, kirim notif Telegram ke Owner **saat itu juga** (reuse jalur notifikasi yang sudah ada, pola sama izin/nego).

**Tracking nganggur kelamaan:** hitung hari kerja berturut-turut seorang karyawan tanpa `project_tahap_pic` aktif ATAU berstatus `diliburkan_perusahaan` (hari libur milik karyawan sendiri — `jadwal_libur`/`hari_libur_default` — TIDAK dihitung sebagai "nganggur", itu memang bukan hari kerja dia). Nembus 14 hari kerja berturut-turut → flag di halaman + 1x notif Telegram, tidak berulang.

---

## 5. Batas scope (sengaja belum dikerjakan)

- **Urutan tahap wajib** (gating tahap B sampai tahap A selesai) — alur lapangan tidak baku, tidak digembok.
- **Skill lewat ujian/tes formal** — skill cukup dicentang (default role + manual khusus), bukan sistem penilaian. Modul Ujian/KPI yang sudah ada itu ujian pengetahuan umum, beda konsep, tidak disentuh.
- **Auto-assign PIC tanpa konfirmasi manusia** — sistem cuma menyarankan, Supervisor yang memutuskan & mencentang manual.
- **Auto-update `rab_jenis_kerja` tanpa approval** — evaluasi produktivitas WAJIB di-approve Owner manual.
- **Alur pengajuan formal untuk pengurangan karyawan** — cuma flag/laporan, keputusan PHK/rotasi 100% di luar sistem.
- **Checklist "Tahap Perlindungan Lapangan"** (roadmap #3, rantai WF→scaffolding+takel) — fitur beda, belum digarap, tidak digabung ke SWE.
- **Backfill data project lama** — tidak ada, karena belum ada project nyata (masih dummy).

---

## 6. Fase implementasi (rencana pemecahan, bukan 1 plan besar)

Spec ini mendokumentasikan visi penuh, tapi eksekusinya dipecah jadi beberapa `writing-plans` terpisah, dikerjakan berurutan (masing-masing diuji sebelum lanjut):

1. **Fase 1 — Fondasi Tahap Produksi:** `tahap_master`, `template_tahap`(+item), `project_tahap`, `project_tahap_pic`, auto-generate saat deal, halaman kelola master, jalankan/selesaikan tahap (Bagian A-D, tanpa rekomendasi skill dulu — PIC pilih manual dari semua karyawan).
2. **Fase 2 — Skill & Rekomendasi PIC:** `user_skill`, kolom `rab_skill.default_role`, kalkulasi saran jumlah orang, filter kandidat di halaman "Cari PIC" (Bagian B lengkap).
3. **Fase 3 — Kapasitas Tim:** halaman `/kapasitas-tim`, kalkulasi demand-vs-supply, notif event-driven, aksi pindah/liburkan karyawan nganggur, tracking 14 hari + flag (Bagian F).
4. **Fase 4 — Evaluasi Produktivitas:** `evaluasi_produktivitas`, kalkulasi otomatis pas tahap selesai, halaman approval Owner (Bagian E).

Alasan urutan: Fase 1 harus jalan dulu biar ada data tahap nyata; Fase 2 butuh Fase 1 (butuh `project_tahap` buat nyambungin PIC); Fase 3 butuh Fase 1+2 (demand dihitung dari `project_tahap` + skill); Fase 4 butuh semuanya jalan dulu biar ada sample data aktual buat dievaluasi.
