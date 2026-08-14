# Migrasi Penyimpanan Media ke Cloudflare R2

**Tanggal:** 14 Agustus 2026
**Status:** Disetujui, siap ditulis jadi plan implementasi

## Ringkasan

Pindahkan penyimpanan foto & video dari 2 tempat yang sekarang dipakai (disk lokal server Niagahoster + Cloudinary) ke satu tempat: Cloudflare R2. Sekalian menambah fitur baru — upload video di profil lokasi survei (belum ada sama sekali sekarang).

## Latar Belakang

Sebelum migrasi ini, foto tersebar di 2 tempat dengan pola berbeda:

- **Foto absen** (masuk / lapor-progress / kembali-kerja): upload base64 dari HP → lewat server Laravel → ditulis ke disk lokal shared hosting (`storage/app/public/absensi/...`). Tidak ada retensi — numpuk terus tiap hari × 14 karyawan × 2-3 checkpoint.
- **Foto profil lokasi survei** (maks 8 foto per lokasi): upload langsung dari browser ke Cloudinary, URL disimpan sebagai JSON di kolom `lokasi_foto`.

Roadmap lama (`CLAUDE.md` #4 "Sesi Media R2") sudah menandai ini sebagai pekerjaan yang perlu dilakukan sebelum modul volume-besar berikutnya (absensi v2/portal customer) — tapi belum pernah dirancang sampai sesi ini.

## Cakupan

**Termasuk:**
1. Migrasi foto absen (masuk/lapor-progress/kembali-kerja) dari disk lokal → R2.
2. Migrasi foto profil lokasi survei dari Cloudinary → R2.
3. Fitur baru: upload video profil lokasi survei (1-3 menit), disimpan di R2.

**Tidak termasuk (sengaja ditunda):**
- Migrasi foto/video LAMA yang sudah ada di disk lokal/Cloudinary — tetap di tempat asal. Foto absen lama biarkan expired natural (dibersihkan manual pakai `foto-absen-bersih.php` yang sudah dibuat), foto lokasi lama tetap di Cloudinary selamanya (dokumentasi permanen, tidak masalah kalau nyebar 2 tempat untuk data lama).
- Fitur tampilan riwayat foto absen 7-hari-terakhir untuk karyawan sendiri (diusulkan Elvan saat brainstorming) — akan jadi spec terpisah setelah migrasi ini selesai, supaya diff migrasi ini tetap fokus.
- Setup akun Cloudflare + bucket R2 itu sendiri — dilakukan manual oleh Elvan (non-teknis), dipandu terpisah saat eksekusi. Bukan bagian dari kode.

## Keputusan Kunci

### 1. Tidak ada dependency Composer baru

Cek cPanel Niagahoster (menu Software): tidak ada Terminal atau fitur Composer, hanya WordPress Manager, PHP PEAR Packages, Perl Modules, Optimize Website, Application Manager, Softaculous Installer, Select PHP Version. `vendor/` juga dikecualikan dari auto-deploy FTP (`deploy.yml`), jadi tidak ada cara pasang package Composer baru di production tanpa akses yang tidak tersedia.

**Keputusan:** `App\Services\R2Service` ditulis pakai `curl_init` murni + tanda tangan AWS SigV4 manual (R2 kompatibel S3, presigned URL & auth-nya pakai skema yang sama). Ini konsisten dengan pola yang sudah dipakai di project ini (`Http::` facade tidak jalan di shared hosting → pakai `curl_init`, dicatat di `CLAUDE.md`).

### 2. Data lama tidak ikut dimigrasi

Hanya upload BARU (setelah fitur ini aktif) yang masuk R2. Menyederhanakan implementasi — tidak perlu proses migrasi data besar yang berisiko di tengah hosting yang sudah terbatas resource-nya.

### 3. Retensi

- **Foto absen: 60 hari**, ditegakkan lewat **R2 Object Lifecycle Rule** (fitur bawaan dashboard Cloudflare — centang aturan "hapus otomatis setelah 60 hari", tanpa kode/cron). Ini menggantikan script manual `public/foto-absen-bersih.php` yang dibuat sesi ini — script itu jadi tidak relevan lagi untuk foto BARU begitu lifecycle rule aktif, dan bisa dihapus dari server setelah dipastikan lifecycle rule berjalan. Foto lama yang masih di disk lokal tetap perlu dibersihkan manual pakai script itu (di luar cakupan R2).
- **Foto & video lokasi survei: permanen**, tidak ada lifecycle rule — ini dokumentasi bisnis (bukti kondisi lokasi), bukan bukti harian yang kadaluarsa.

### 4. Pola upload berbeda per jenis media (BUKAN satu pola seragam secara teknis)

Awalnya roadmap menyebut "1 cara upload seragam semua modul" — tapi setelah ditelusuri, kebutuhan teknisnya beda:

- **Foto absen** (kecil, base64, alur SUDAH bekerja & sudah melalui banyak perbaikan 14 Agustus): tetap upload base64 ke Laravel seperti sekarang, **hanya baris penyimpanannya yang diganti** — dari `Storage::disk('public')->put(...)` jadi `R2Service::put(...)`. Tidak ada perubahan di sisi HP karyawan sama sekali.
- **Foto & video profil lokasi survei** (video bisa 30-100MB): **wajib** upload langsung dari browser ke R2 (presigned URL) — kalau dikirim lewat PHP server dulu, berisiko kena limit upload shared hosting yang biasanya kecil (default cPanel, belum pernah dinaikkan di project ini).

"Seragam" yang sebenarnya dipertahankan: SEMUA media akhirnya tersimpan di R2 (satu tempat, satu dashboard, satu kebijakan retensi per kategori) — bukan cara HTTP-nya yang seragam, tapi TEMPAT nyimpennya.

**Alasan tidak menyeragamkan ke presigned-URL untuk foto absen juga:** kode foto absen sudah stabil dan baru saja melalui banyak perbaikan (redesain checkpoint 14 Agustus + rentetan bug `.catch()`). Menulis ulang alurnya demi keseragaman teknis semata melanggar prinsip "jangan ubah kode yang sudah terbukti jalan tanpa alasan fungsional".

### 5. Video pakai kamera native, bukan recorder custom

`<input type="file" accept="video/*" capture="environment">` — membuka aplikasi kamera bawaan HP untuk merekam video. **Bukan** `MediaRecorder`/`getUserMedia` custom seperti yang dipakai untuk foto. Alasan: video 1-3 menit lewat custom recorder JS jauh lebih rawan gagal (terutama iOS Safari — project ini sudah 2 kali kena masalah nyata `position:fixed`/custom-JS di Safari, lihat catatan DenahEditor 16 Juli & modal Libur Nasional 13 Agustus di `CLAUDE.md`), sementara input native sudah teruji jutaan device.

### 6. Bucket R2 diaktifkan mode publik

Supaya URL hasil upload bisa langsung dipakai sebagai `src` gambar/video tanpa perlu bikin presigned-GET tiap kali halaman dibuka. Level keterbukaan ini SAMA dengan Cloudinary yang dipakai sekarang (URL Cloudinary juga publik/bisa ditebak) — bukan kemunduran keamanan.

## Arsitektur

```
App\Services\R2Service
├── put(string $key, string $binaryData): string        // upload langsung dari server (dipakai foto absen)
│                                                          // → return URL publik
└── presignPutUrl(string $key, string $contentType): array // izin upload sementara
                                                             // → return ['url' => ..., 'publicUrl' => ...]
```

Kredensial R2 (`R2_ACCESS_KEY`, `R2_SECRET_KEY`, `R2_BUCKET`, `R2_ENDPOINT`, `R2_PUBLIC_URL`) disimpan di `.env` production, dibaca lewat `getenv()` (konsisten dengan pola token lain di project ini — lebih andal dari `env()` di shared hosting per catatan `CLAUDE.md`).

## Alur Data per Jenis Media

**Foto absen (masuk/lapor-progress/kembali-kerja):**
```
HP karyawan --base64--> Laravel (unchanged) --R2Service::put()--> R2
                                                                    ↓
                                            kolom foto_masuk/foto_siang_1/foto_pulang
                                            diisi URL publik R2 (bukan path lokal lagi)
```
Catatan: foto-foto ini sekarang TIDAK ditampilkan di layar manapun (dicek — murni arsip bukti). Migrasi ini tidak menyentuh UI apapun untuk bagian ini.

**Foto profil lokasi survei:**
```
HP surveyor -> Laravel: "minta izin upload" -> R2Service::presignPutUrl() (URL berlaku 15 menit)
HP surveyor --PUT langsung--> R2 (pakai URL sementara dari atas)
HP surveyor -> Laravel: "sudah selesai, ini URL-nya" -> disimpan ke kolom lokasi_foto (JSON array, format sama persis kayak sekarang)
```

**Video profil lokasi survei (baru):**
Alur sama persis dengan foto lokasi di atas, bedanya:
- Capture pakai `<input type="file" accept="video/*" capture="environment">`, bukan canvas snapshot.
- Disimpan ke kolom BARU `lokasi_video` (JSON array, pola sama dengan `lokasi_foto`).
- Validasi ukuran file dicek di browser SEBELUM upload dimulai — batas maksimal **200MB** (longgar dari kisaran normal 30-100MB untuk video 1-3 menit, sekadar jaga-jaga dari file yang kelewat besar/gagal terkompresi HP), guard sederhana, hindari buang kuota surveyor untuk upload yang pasti gagal.

## Perubahan Skema Database

Satu kolom baru saja, di tabel `pipeline_leads` (tempat `lokasi_foto` berada, dicek langsung dari `LokasiController`):
```sql
ALTER TABLE pipeline_leads ADD COLUMN IF NOT EXISTS lokasi_video TEXT NULL AFTER lokasi_foto;
```

Tidak ada perubahan skema untuk tabel `absensi` — kolom `foto_masuk`/`foto_siang_1`/`foto_pulang` sudah `VARCHAR(255)`, cukup panjang untuk menyimpan URL publik R2 (~60-90 karakter).

## Error Handling

1. **Upload gagal di tengah jalan** (sinyal lokasi survei lemah): file foto/video yang sudah direkam TETAP tersimpan di memori HP (tidak hilang), tombol "Coba upload lagi" muncul — tidak perlu rekam ulang dari nol.
2. **Video kegedean/format tidak didukung**: divalidasi di browser sebelum upload dimulai.
3. **R2Service gagal** (kredensial salah, network, dsb): `Log::error()` dengan pesan jelas (pola sama seperti `TelegramService`) — supaya kalau ada masalah, diagnosa langsung dari log, bukan tebak-tebak seperti insiden kolom hilang 14 Agustus.

## Testing

- **Test standalone murni** (tanpa jaringan) untuk perhitungan tanda tangan AWS SigV4 di `R2Service` — bagian paling rawan salah karena murni matematika string/hash, harus benar sebelum pernah dicoba ke R2 asli. Pola sama seperti `tests/telegram/test_telegram_service.php`.
- **Smoke test manual sekali** saat setup: upload file kecil ke bucket, baca lagi, hapus — dijalankan sekali untuk memastikan kredensial & bucket sudah benar sebelum dipasang ke fitur asli.

## Risiko & Mitigasi

| Risiko | Mitigasi |
|---|---|
| Elvan belum pernah setup Cloudflare — bisa salah langkah (mirip insiden ekstensi browser di SQL 13 Agustus) | Panduan langkah-demi-langkah non-teknis disiapkan terpisah saat eksekusi, direview bareng sebelum lanjut ke langkah berikutnya |
| SigV4 hand-rolled (bukan SDK resmi) — potensi salah implementasi tanda tangan | Test standalone dulu (lihat bagian Testing) sebelum dipakai fitur asli |
| Bucket R2 publik — URL bisa ditebak siapa saja yang tahu pola nama file | Sama levelnya dengan Cloudinary sekarang, bukan kemunduran; tidak ada data sangat sensitif (bukan dokumen finansial/pribadi) |
| Upload video gagal di lokasi sinyal lemah, karyawan/surveyor frustrasi | File tidak hilang saat gagal, tombol retry tanpa rekam ulang (lihat Error Handling) |
