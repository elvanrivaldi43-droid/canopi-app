# Deterministic Guardrail — Fondasi Tahap 1

> **Workflow:** Direct/Single-session Claude + Deterministic Guardrail. Satu worktree, satu writer, satu sesi Claude, tanpa continuation otomatis.

**Goal:** Mengurangi konteks awal Claude dan menyediakan satu perintah verifikasi lokal yang benar-benar menjalankan seluruh tes standalone Canopi sebelum CI/deploy disentuh.

**Architecture:** Tahap 1 hanya membangun fondasi lokal. Riwayat panjang `CLAUDE.md` dipindahkan tanpa dihapus ke arsip yang tidak otomatis dimuat. Runner deterministik menginventaris dan menjalankan tes PHP/Node yang saat ini tidak terjangkau `php artisan test`, lalu menjalankan pemeriksaan syntax, route, Blade, diff, staged artifact, credential/debug, dan memberikan exit code tegas. GitHub Actions, MariaDB test, preview server, dan feature flag sengaja ditunda ke tahap terpisah.

**Tech Stack:** Bash/PHP 8.3, Node.js 22, Git, Laravel 13, existing standalone tests.

---

## Current Context / Evidence

- Branch `main` sama dengan `origin/main`; untracked hanya `.claude/`, `.hermes/`, `.worktrees/`.
- Satu sesi Claude lama di tmux `canopi` idle; tidak boleh dipakai bersamaan dengan sesi implementasi baru tanpa keputusan eksplisit.
- `CLAUDE.md` sekitar 710 baris / 99 KB dan otomatis masuk ke setiap sesi Claude.
- `.github/workflows/deploy.yml` langsung FTP setelah checkout; belum ada verification gate.
- Terdapat 38 berkas PHP di `tests/` dan 5 berkas Node `.mjs`.
- `phpunit.xml` hanya menunjuk `tests/Unit` dan `tests/Feature`; tes bisnis aktual berada di folder lain sebagai script standalone. `composer test` tidak dapat dianggap sebagai bukti seluruh tes bisnis berjalan.
- PHP CLI hanya memiliki `PDO`, tanpa `pdo_mysql`/`pdo_sqlite`; belum ada binary/database MySQL lokal.
- Tahap ini tidak menginstal package, tidak menyentuh production, tidak mengubah GitHub Actions, dan tidak commit/push tanpa gate Bos.

## Budget Claude Tahap 1

- Model: Sonnet/moderat.
- Effort: medium.
- Maksimal: 10 turns.
- Jumlah sesi: satu sesi implementasi.
- Writer: Claude saja selama sesi aktif; Hermes read-only.
- Tidak ada independent Claude reviewer (perubahan tooling lokal, bukan business logic).
- Stop: max turns, context >=60%, scope bertambah, warning usage, atau temuan butuh keputusan bisnis.
- Tidak ada `--continue` otomatis.

---

### Task 1: Arsipkan Riwayat CLAUDE.md Tanpa Kehilangan Catatan

**Objective:** Mengurangi context otomatis, dengan mempertahankan seluruh catatan lama secara byte-readable di Git.

**Files:**
- Modify: `CLAUDE.md`
- Create: `docs/history/CLAUDE_STATUS_ARCHIVE_2026-08-15.md`
- Create: `tests/guardrail/test_claude_context.php`

**Requirements:**
1. Arsip berisi seluruh bagian status/history yang dipindahkan, termasuk catatan 11–15 Agustus dan resume lama.
2. `CLAUDE.md` tetap memuat utuh:
   - identitas dan environment production;
   - preferensi komunikasi/kerja;
   - deploy workflow + pelajaran insiden;
   - arsitektur RAB dan aturan bisnis;
   - level akses;
   - catatan teknis yang masih aktif;
   - roadmap aktif;
   - status/resume terbaru dan pointer ke arsip.
3. Jangan meringkas keputusan bisnis dengan cara yang mengubah makna.
4. Tes harus membuktikan marker kritis ada di `CLAUDE.md`, marker histori ada di arsip, dan tidak ada credential yang ikut dipindahkan/ditambahkan.

**TDD:**
1. Tulis `tests/guardrail/test_claude_context.php` yang gagal karena arsip belum ada dan ukuran/context file belum dirampingkan.
2. Jalankan tes dan buktikan RED karena alasan tersebut.
3. Buat arsip dan slim `CLAUDE.md` minimal.
4. Jalankan tes sampai GREEN.
5. Hermes kemudian membandingkan marker lama vs arsip secara mekanis.

**Acceptance:**
- Tidak ada catatan lama yang dihapus dari repository; hanya dipindahkan.
- `CLAUDE.md` jauh lebih ringkas dan tetap self-contained untuk aturan aktif.
- Diff hanya menyentuh tiga file di atas pada task ini.

---

### Task 2: Buat Manifest Tes Deterministik

**Objective:** Mendefinisikan secara eksplisit tes mana yang wajib berjalan, sehingga file utilitas seperti `preview_server.php` tidak dianggap tes dan tes baru tidak diam-diam terlewat.

**Files:**
- Create: `tests/guardrail/manifest.json`
- Create: `tests/guardrail/test_manifest.php`

**Requirements:**
1. Manifest mencakup semua `tests/**/test_*.php` dan `tests/**/*.mjs` yang benar-benar merupakan tes.
2. Manifest mengecualikan helper/server manual secara eksplisit.
3. Tes guardrail gagal bila:
   - file di manifest hilang;
   - ada `test_*.php`/`.mjs` baru yang belum didaftarkan;
   - path keluar dari `tests/`;
   - entri ganda.
4. Klasifikasikan minimal `php`, `node`, `requires_db`, dan `manual` agar runner masa depan dapat memilih dengan aman.

**TDD:**
1. Tulis tes manifest dan buktikan RED karena manifest belum ada.
2. Buat manifest minimal dari inventory aktual.
3. Jalankan sampai GREEN.

---

### Task 3: Buat Runner `scripts/canopi-check`

**Objective:** Menyediakan satu command dengan exit code non-zero pada kegagalan apa pun.

**Files:**
- Create: `scripts/canopi-check`
- Create: `tests/guardrail/test_canopi_check.php`
- Modify: `composer.json` (tambahkan alias saja jika diperlukan)

**Modes:**
- `./scripts/canopi-check --fast`: guardrail tests, changed-PHP syntax, changed-file safety, dan tes kelompok relevan dari manifest.
- `./scripts/canopi-check --full`: seluruh PHP standalone, seluruh Node, syntax source/test yang relevan, route load, Blade compile, `git diff --check`, scan staged artifact/secret/debug.
- `./scripts/canopi-check --list`: hanya tampilkan inventory yang akan dijalankan, tanpa eksekusi tes.

**Safety checks wajib:**
- `.env`, `.hermes/`, credential, cache, upload/test artifact tidak boleh staged.
- Added lines tidak boleh mengandung token/password hardcoded, `dd(`, `dump(`, atau temporary debug marker tanpa allowlist.
- Perintah berhenti saat tes pertama gagal, tetapi menampilkan nama tes dan command yang gagal.
- Tidak mengakses network, Telegram, R2, production DB, atau endpoint mutasi.
- Tidak melakukan commit, push, install, atau file edit.

**TDD:**
1. Tes `--list` RED karena runner belum ada.
2. Implementasikan `--list` sampai GREEN.
3. Tambah fixture kegagalan dan buktikan runner meneruskan exit code non-zero.
4. Implementasikan `--fast` sampai GREEN.
5. Implementasikan `--full` sampai seluruh baseline hijau.

**Acceptance:**
- Satu command menghasilkan ringkasan PASS/FAIL yang ringkas.
- Jumlah tes yang dijalankan cocok dengan manifest.
- Tidak ada tes yang hijau palsu karena tidak dieksekusi.
- Runner bekerja dari repo utama dan worktree tanpa memuat class dari branch lain secara salah.

---

### Task 4: Verifikasi Hermes Setelah Claude Exit

**Objective:** Membuktikan perubahan deterministic guardrail benar tanpa reviewer AI tambahan.

**Hermes commands/checks:**
1. `git status --short --branch` dan pastikan hanya scope Tahap 1 berubah.
2. Jalankan tes guardrail satu per satu.
3. Jalankan `./scripts/canopi-check --list` dan cocokkan dengan filesystem.
4. Jalankan `./scripts/canopi-check --fast`.
5. Jalankan `./scripts/canopi-check --full` tepat satu kali.
6. Jalankan `git diff --check`.
7. Audit diff `CLAUDE.md` vs arsip dan marker keputusan kritis.
8. Pastikan `.hermes/` tidak staged.
9. Laporkan hasil; minta gate commit/push terpisah.

**No automatic continuation:** Bila Claude belum selesai pada turn ke-10, Hermes membaca state dan kembali ke Bos. Jangan menjalankan sesi kedua.

---

## Tahap Berikutnya (Bukan Scope Tahap 1)

### Tahap 2 — Database Tes Lokal
- Persetujuan system change terpisah.
- Install driver `pdo_mysql` dan MariaDB lokal.
- Buat `canopi_test` dengan credential lokal khusus tes.
- Tambah `.env.testing` aman dan tidak di-commit bila berisi secret.
- Ubah sebagian guardrail statis menjadi integration test nyata untuk migration, authorization, payroll, dan atomic unique index.

### Tahap 3 — GitHub Verification Sebelum FTP
- Tambah job `verify` di `.github/workflows/deploy.yml`.
- Deploy job memakai `needs: verify` dan tidak jalan bila guardrail gagal.
- Gunakan MySQL service disposable bila integration tests sudah siap.
- Independent read-only review wajib karena perubahan ini menyentuh jalur deploy.

### Tahap 4 — Preview VPS / E2E
- Serve worktree ke port preview dengan DB test.
- Buat akun dummy Owner/Admin/Mandor/Karyawan.
- Browser E2E read/write hanya terhadap DB test.
- Tambah feature flag hanya untuk fitur berisiko tinggi; jangan bangun framework flag generik tanpa kebutuhan.

---

## Risks and Mitigations

- **Catatan CLAUDE hilang:** arsip dulu, tes marker, compare sebelum commit.
- **Runner hijau palsu:** manifest eksplisit + tes inventory drift + exit-code fixtures.
- **Tes melakukan side effect eksternal:** tandai manifest; tahap 1 hanya jalankan tes yang terbukti offline.
- **CI langsung memblokir deploy:** CI tidak disentuh pada Tahap 1.
- **Scope membesar:** DB, package install, CI, preview ditunda ke gate terpisah.
- **Agent berulang:** hard cap satu sesi/10 turns; max-turns adalah stop condition.

## Decision Needed

Setujui atau revisi **Tahap 1 saja**. Jika disetujui, sebelum Claude dijalankan Hermes akan membuat worktree baru dari `origin/main`, memastikan sesi lama tidak menjadi writer, lalu meminta izin final dengan budget: Sonnet medium, maksimal 10 turns, satu sesi, tanpa continuation.