# Kunci Keuangan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (dipilih Elvan: langsung + 1 pemeriksa akhir). Steps use checkbox (`- [ ]`) syntax.

**Goal:** Data uang milik karyawan sendiri (slip, kasbon, tarif, rekening) hanya terlihat setelah mengetik ulang password, berlaku 5 menit; Owner bebas.

**Architecture:** Logika waktu/level di satu kelas murni `KunciKeuangan`; satu middleware `keuangan[:kunci|samar]` (redirect ke konfirmasi password bawaan Laravel + header no-store); satu `Blade::if` untuk menyamarkan angka di halaman yang dipakai sehari-hari.

**Tech Stack:** Laravel 13, PHP 8.3, Blade. Tanpa SQL, tanpa dependency baru.

**Spec:** `docs/superpowers/specs/2026-09-30-kunci-keuangan-design.md`

## Global Constraints

- Timeout tetap **300 detik** (keputusan Elvan). Owner = level 1 selalu lolos.
- Tanpa emoji dan tanpa karakter non-ASCII di blade baru (aturan deploy): titik samar ditulis `&bull;&bull;&bull;`.
- Jangan `git add -A`; stage per nama. Test baru didaftarkan di `tests/guardrail/manifest.json` di commit yang sama.
- Form aksi payroll dilarang di `slip.blade.php`; jangan ubah isi slip selain tak perlu (kunci lewat route).

## Review Focus

1. `kembali` di `/buka-keuangan` berisi URL luar (`//evil.com`, `https://evil.com`, `/\evil`) → open redirect. Test Task 1.
2. Konfirmasi di masa depan / `null` / string → harus terkunci. Test Task 1.
3. Owner membuka halaman samar → angka harus tampil penuh tanpa tombol Lihat (Task 3 render check).
4. Route kunci yang terlewat (ada pintu belakang ke slip/kasbon orang lewat route lain) → test struktural Task 2 + audit.
5. Halaman konfirmasi sendiri tidak boleh terkunci oleh middleware (loop redirect) — route `password.confirm` tak diberi `keuangan`.

---

### Task 1: Kelas murni `KunciKeuangan` + test

**Files:** Create `app/Services/KunciKeuangan.php`, `tests/keamanan/test_kunci_keuangan.php`; Modify `tests/guardrail/manifest.json`.

**Interfaces — Produces:**
- `KunciKeuangan::DETIK = 300`
- `KunciKeuangan::terbuka(?int $level, $konfirmasiAt, ?int $now = null): bool`
- `KunciKeuangan::tujuanAman(?string $kembali): string` → path relatif aman, atau `/dashboard` bila tak valid.
- `KunciKeuangan::terbukaSaatIni(): bool` — membaca `auth()->user()->level` dan `session('auth.password_confirmed_at')`.

- [ ] Step 1: tulis test (gagal dulu) — kasus: level 1 true tanpa konfirmasi; level 5 null false; 5 dtk lalu true; tepat 300 true; 301 false; konfirmasi masa depan (now+10) false; string non-angka false; `tujuanAman` menerima `/absensi?x=1`, menolak `//evil.com`, `https://evil.com`, `/\evil.com`, `javascript:1`, `` , null → `/dashboard`.
- [ ] Step 2: jalankan → gagal (kelas tak ada).
- [ ] Step 3: implementasi.
- [ ] Step 4: jalankan → lulus. Daftarkan di manifest. Commit.

### Task 2: Middleware, alias, Blade::if, route buka, throttle, halaman konfirmasi

**Files:** Create `app/Http/Middleware/KeuanganTerkunci.php`, `resources/views/partials/rp-samar.blade.php`; Modify `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`, `routes/web.php` (route `keuangan.buka`), `routes/auth.php` (throttle), `resources/views/auth/confirm-password.blade.php`.

- [ ] Middleware `handle($request, $next, $mode = 'kunci')`: `kunci` & belum terbuka → `redirect()->guest(route('password.confirm'))`; respons (kedua mode) diberi `Cache-Control: no-store, private`.
- [ ] `Blade::if('keuanganTerbuka', fn () => \App\Services\KunciKeuangan::terbukaSaatIni());`
- [ ] Route `GET /buka-keuangan` (auth): `session(['url.intended' => url(KunciKeuangan::tujuanAman(request('kembali')))]); return redirect()->route('password.confirm');`
- [ ] `throttle:6,1` pada `POST confirm-password`.
- [ ] Partial samar + teks konfirmasi Indonesia + "Lupa password? Minta reset ke Owner."
- [ ] Verifikasi: `php -l`, `php artisan route:list --json` memuat `keuangan.buka`, Blade compile. Commit.

### Task 3: Terapkan kunci & samar

**Files:** Modify `routes/web.php`; Modify views dashboard ×6, `profil/index.blade.php`, `absensi/index.blade.php`.

- [ ] `->middleware('keuangan')` pada: `penggajian.slip-saya`, `penggajian.slip`, `kasbon.karyawan.index`, `kasbon.karyawan.surat`, `absensi.rekap-bulanan`, `kpi.detail`, `kpi.ujian.hasil`.
- [ ] `->middleware('keuangan:samar')` pada: 6 route dashboard karyawan, `profil.index`, `absensi.index`.
- [ ] View: bungkus angka dengan `@keuanganTerbuka ... @else @include('partials.rp-samar') @endkeuanganTerbuka`: dashboard (Gaji/Hari, Uang Makan/Hari), profil (estimasi gaji, gaji harian, uang makan, no. rekening), absensi (total UM, total potongan, rincian potongan, badge potongan→label status).
- [ ] Tes struktural `test_kunci_keuangan.php` (tambahan): `artisan route:list --json`, 7 route `keuangan`, 8 route `keuangan:samar` (6 dashboard + profil + absensi.index); route `password.confirm` TANPA keuangan.
- [ ] Render check: render partial samar untuk karyawan vs Owner via tinker.
- [ ] `php scripts/canopi-check --full`. Commit.

### Task 4: Pemeriksa akhir, push, verifikasi, status

- [ ] Review akhir (subagent opus) atas seluruh diff + Review Focus.
- [ ] Push, pantau deploy, update `CLAUDE.md` (Utang aktif 0d: fitur live, belum divalidasi; catatan umumkan ke karyawan; temuan cron key hardcoded).
