# Docker MariaDB Test On-Demand Implementation Plan

> **For Hermes:** Eksekusi langsung secara deterministik, tanpa Claude/subagent. Berhenti pada setiap safety gate yang gagal.

**Goal:** Menyediakan MariaDB lokal disposable untuk tes Canopi tanpa menyentuh database production dan tanpa membebani VPS saat tidak dipakai.

**Architecture:** MariaDB 10.11 berjalan sebagai container Docker sementara, hanya bind ke `127.0.0.1:3307`, dibatasi CPU/RAM, dan menyimpan data di `tmpfs` sehingga hilang saat container dihentikan. PHP CLI host memakai `pdo_mysql`. Semua credential lokal berada di `.hermes/`, mode `0600`, tidak masuk repository dan tidak pernah dicetak.

**Tech stack:** Ubuntu 24.04, PHP 8.3 CLI, `php8.3-mysql`, Docker 29, MariaDB 10.11 LTS, Laravel 13.

---

## Konteks dan fakta terkunci

- Production database berada di Niagahoster dan **tidak boleh digunakan untuk tes**.
- VPS memiliki sekitar 3,8 GiB RAM; preflight menemukan sekitar 1,4 GiB available dan tidak ada swap.
- Docker saat ini menjalankan `n8n-traefik-1` dan `n8n-n8n-1`; keduanya tidak boleh diubah, dihentikan, atau direstart.
- Port `3307` kosong; port `80/443` dipakai Traefik.
- Host hanya punya ekstensi `PDO`; `pdo_mysql` dan `pdo_sqlite` belum ada.
- `php8.3-mysql` tersedia dan dry-run hanya menambah satu paket; tidak ada host PHP-FPM/Apache/Nginx aktif.
- Repo `main` lokal tertinggal dan tmux Claude lama masih idle. Fase ini tidak mengedit worktree Claude, tidak commit, tidak push, dan tidak deploy.
- Engine production persis belum diketahui. MariaDB 10.11 dipilih sebagai baseline LTS lokal; ini bukan klaim bahwa production memakai versi yang sama.

## Batas fase ini

Fase ini hanya membangun dan membuktikan lingkungan database tes lokal. Fase ini **tidak**:

- menulis integration test bisnis baru;
- mengubah `phpunit.xml` atau workflow GitHub;
- mengubah migration agar “dipaksa hijau”;
- menjalankan SQL production;
- commit/push/deploy;
- memasang MariaDB native permanen;
- membuka database ke internet.

Jika `migrate:fresh` gagal karena migration chain tidak lengkap, catat sebagai temuan nyata dan berhenti. Perbaikan migration menjadi plan terpisah.

---

### Task 1: Safety snapshot sebelum perubahan sistem

**Objective:** Membuktikan resource, port, container, dan Git aman tepat sebelum instalasi.

**Files:** Tidak ada.

**Steps:**

1. Catat `free -h`, `df -h /`, `docker ps`, dan listener port `3307`.
2. Tutup Claude idle secara normal dengan `/exit`; abort jika proses tidak berhenti aman.
3. Abort jika memory available setelah Claude berhenti kurang dari 1,3 GiB, port 3307 terpakai, atau container n8n/Traefik tidak sehat.
4. Catat status Git; abaikan hanya `.claude/`, `.hermes/`, dan `.worktrees/` yang memang lokal.

**Expected:** Dua container lama tetap running; port 3307 kosong; disk cukup; tidak ada tracked work yang belum selesai.

---

### Task 2: Pasang driver PHP MySQL minimal

**Objective:** Membuat PHP CLI mampu membuka koneksi MariaDB.

**Files:** File konfigurasi paket OS otomatis; tidak ada file repository.

**Steps:**

1. Jalankan `apt-get install -y php8.3-mysql` saja.
2. Jangan menjalankan `apt upgrade`, `apt autoremove`, atau memasang `mariadb-server`.
3. Verifikasi `php -m` memuat `mysqli`, `mysqlnd`, `pdo_mysql`.
4. Verifikasi versi PHP tetap 8.3.x.
5. Pastikan container n8n dan Traefik tetap running.

**Rollback:** `apt-get remove php8.3-mysql` hanya jika pemasangan merusak PHP CLI; jangan autoremove dependency lain.

---

### Task 3: Buat credential dan runner lokal yang aman

**Objective:** Menyediakan satu jalur start/stop/status yang menolak koneksi selain database tes.

**Files:**

- Create local-only: `.hermes/test-db.env` mode `0600`
- Create local-only: `.hermes/scripts/canopi-test-db.sh` mode `0700`

**Environment file:**

- `MARIADB_ROOT_PASSWORD`: acak kuat, tidak dicetak
- `MARIADB_DATABASE=canopi_test`
- `MARIADB_USER=canopi_test`
- `MARIADB_PASSWORD`: acak kuat, tidak dicetak

**Runner commands:**

- `start`: preflight resource/port/name, pull image bila belum ada, lalu start container
- `status`: health, binding port, dan resource container
- `env`: mengekspor mapping Laravel tanpa mencetak password
- `stop`: stop container dan memastikan container hilang

**Hard guards:**

- nama container harus `canopi-mariadb-test`;
- image dikunci `mariadb:10.11`;
- bind harus `127.0.0.1:3307:3306`;
- database harus persis `canopi_test`;
- user harus persis `canopi_test`;
- tolak `DB_HOST` selain `127.0.0.1`;
- tolak port selain `3307`;
- jangan membaca atau menyalin credential production `.env`.

**Container limits:**

- `--rm`
- `--memory=384m` dan `--memory-swap=384m`
- `--cpus=0.35`
- `--tmpfs /var/lib/mysql:rw,noexec,nosuid,size=512m`
- MariaDB tuning: `--innodb-buffer-pool-size=128M`, `--max-connections=10`, `--performance-schema=OFF`, `--skip-name-resolve`
- healthcheck `mariadb-admin ping`
- tidak memakai volume production atau network n8n

---

### Task 4: Start container dan buktikan isolasi

**Objective:** Membuktikan MariaDB sehat dan hanya dapat diakses lokal.

**Steps:**

1. Jalankan runner `start`.
2. Tunggu health status `healthy` dengan timeout terbatas; jangan blind sleep tanpa healthcheck.
3. Periksa `docker inspect`:
   - memory 384 MiB;
   - CPU quota sesuai 0,35 CPU;
   - port hanya `127.0.0.1:3307`;
   - filesystem DB memakai tmpfs;
   - restart policy tidak membuat container permanen.
4. Pastikan port 3306/3307 tidak terbuka pada interface publik.
5. Pastikan n8n dan Traefik tetap running.

**Expected:** Container test healthy; tidak ada listener `0.0.0.0:3307` atau `[::]:3307`.

---

### Task 5: PDO smoke test dengan data palsu

**Objective:** Membuktikan PHP host benar-benar dapat bertransaksi melalui `pdo_mysql`.

**Steps:**

1. Jalankan PHP dengan environment eksplisit dari runner.
2. Sebelum koneksi, assert:
   - `APP_ENV=testing`;
   - host `127.0.0.1`;
   - port `3307`;
   - database `canopi_test`.
3. Buka PDO dengan exception mode.
4. Buat tabel smoke sementara, insert satu baris palsu, read-back, rollback/drop.
5. Tampilkan hanya status PASS dan versi server; jangan tampilkan DSN lengkap/password.
6. Verifikasi production URL tidak pernah muncul di command, env output, atau log.

**Expected:** `PASS: PDO MariaDB disposable` dan tabel smoke dibersihkan.

---

### Task 6: Uji migration chain Laravel secara nyata

**Objective:** Mengetahui apakah 26 migration repository mampu membangun database kosong tanpa production.

**Environment Laravel:**

- `APP_ENV=testing`
- `DB_CONNECTION=mysql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=3307`
- `DB_DATABASE=canopi_test`
- `DB_USERNAME=canopi_test`
- password dari file lokal
- `CACHE_STORE=array`
- `SESSION_DRIVER=array`
- `QUEUE_CONNECTION=sync`
- `MAIL_MAILER=array`

**Steps:**

1. Jalankan safety assertion exact database sebelum perintah destruktif.
2. Jalankan `php artisan migrate:fresh --force` hanya pada `canopi_test`.
3. Jalankan `php artisan migrate:status`.
4. Catat migration pertama yang gagal beserta error nyata jika ada.
5. **Jangan memperbaiki migration pada fase ini.** Kegagalan migration chain adalah hasil audit, bukan alasan menambal cepat.
6. Jika seluruh migration sukses, jalankan `php artisan test` sebagai baseline dan label hasilnya terpisah dari 45 standalone tests.

**Expected:** Salah satu dari dua hasil sah:

- PASS: database kosong berhasil dibangun dan baseline Laravel tercatat; atau
- BLOCKED: migration chain repository tidak lengkap, dengan nama migration/error yang dapat direproduksi.

---

### Task 7: Stop, cleanup, dan bukti tidak ada dampak

**Objective:** Memastikan database tes benar-benar on-demand dan VPS kembali ke kondisi awal.

**Steps:**

1. Jalankan runner `stop`.
2. Verifikasi container `canopi-mariadb-test` tidak ada.
3. Verifikasi listener `127.0.0.1:3307` hilang.
4. Verifikasi n8n dan Traefik tetap running.
5. Verifikasi production `/login` HTTP 200 dan `/` HTTP 302.
6. Verifikasi Git tidak memiliki tracked changes dari fase infrastruktur.
7. Verifikasi `.hermes/test-db.env` mode `0600` dan script lokal tidak tracked.
8. Laporkan disk image MariaDB yang tersisa; jangan hapus image otomatis karena akan dipakai pada tes berikutnya.

---

## Acceptance criteria

- [ ] `pdo_mysql` tersedia di PHP CLI dan PHP tetap 8.3.
- [ ] MariaDB test hanya bind `127.0.0.1:3307`.
- [ ] Container dibatasi 384 MiB dan 0,35 CPU.
- [ ] Data DB berada di tmpfs dan hilang setelah stop.
- [ ] Credential lokal mode `0600`, tidak dicetak, tidak masuk Git.
- [ ] PDO smoke transaction dengan data palsu PASS.
- [ ] `migrate:fresh` menghasilkan bukti nyata PASS atau blocker migration yang spesifik.
- [ ] Tidak ada koneksi/SQL ke production.
- [ ] n8n dan Traefik tetap running.
- [ ] Production smoke tetap normal.
- [ ] Tidak ada commit, push, atau deploy.

## Risiko dan mitigasi

1. **Tekanan RAM/OOM:** Claude idle ditutup lebih dulu; container dibatasi 384 MiB, hanya start saat tes, dan abort jika available memory kurang dari 1,3 GiB.
2. **Port terbuka publik:** binding dikunci ke `127.0.0.1`; inspect dan `ss` menjadi acceptance gate.
3. **Salah database saat `migrate:fresh`:** runner menolak host/port/nama selain exact test target sebelum menjalankan Artisan.
4. **Credential bocor:** generated lokal, mode ketat, tidak dicetak, tidak memakai argument command, dan berada di `.hermes/`.
5. **Gangguan n8n:** tidak memakai network/port n8n; container resource-limited; cek health sebelum dan sesudah.
6. **Migration chain tidak mencerminkan production:** laporkan sebagai gap dan buat plan perbaikan terpisah; jangan menyalin schema/data production.
7. **Perbedaan engine:** MariaDB 10.11 adalah baseline LTS; versi production perlu diverifikasi terpisah jika sintaks tertentu berbeda.

## Gate persetujuan

Persetujuan berikut mengizinkan hanya Task 1–7 di atas: pemasangan `php8.3-mysql`, pull/start/stop container MariaDB test, pembuatan file lokal `.hermes/`, PDO smoke, dan `migrate:fresh` pada database exact `canopi_test`.

Persetujuan ini **tidak** mencakup perubahan repository, commit, push, deploy, SQL production, atau perbaikan migration/business logic.

---

## Hasil eksekusi 15 Agustus 2026

- `php8.3-mysql` terpasang; `pdo_mysql`, `mysqli`, dan `mysqlnd` tersedia.
- `php8.3-xml` disetujui terpisah dan terpasang setelah Artisan terbukti membutuhkan `DOMDocument`.
- Runner lokal tersedia di `.hermes/scripts/canopi-test-db.sh`; credential lokal `.hermes/test-db.env` mode `0600` dan tidak tracked.
- MariaDB `10.11.18` berhasil start dengan bind `127.0.0.1:3307`, RAM 384 MiB, CPU 0,35, tmpfs, dan restart policy `no`.
- PDO smoke data palsu PASS.
- Lifecycle start/stop PASS; race removal asynchronous pada `docker stop --rm` ditemukan dan runner lokal diperbaiki dengan bounded wait 10 detik.
- `migrate:fresh` menjalankan 15 migration lalu berhenti secara deterministik pada `2026_08_11_000001_add_hari_libur_default_to_users_table`: migration memakai `after('tanggal_bergabung')`, sedangkan kolom `users.tanggal_bergabung` belum dibuat oleh migration chain repository.
- `migrate:status` renderer juga membuktikan dependency CLI `mbstring` belum tersedia; status applied diverifikasi langsung dari tabel `migrations` tanpa memasang paket tambahan: 15 migration applied.
- Container test sudah stopped/removed, port 3307 tertutup, n8n health 200, production `/login` 200 dan `/` 302.
- Tidak ada SQL production, tracked repository change, commit, push, atau deploy.