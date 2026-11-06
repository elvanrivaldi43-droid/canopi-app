# Penugasan Harian (Rencana Malam Mandor + Konfirmasi Telegram + Laporan Sore) — Rancangan

Tanggal: 6 Nov 2026 · Status: **DRAFT, menunggu persetujuan Elvan** · Ukuran: proyek
Proyek 1 dari 2. Proyek 2 = `2026-11-06-survei-manual-vs-aktual-design.md`, dibangun di atas ini.

## 1. Tujuan dan alasan

Elvan mengubah urutan roadmap: **SWE → Keuangan → RAB terakhir**. Untuk membandingkan perkiraan
surveyor dengan kenyataan (proyek 2), sistem butuh data kerja nyata yang akurat: **siapa kerja di
project/tahap mana, berapa lama (penuh/setengah hari)**. Tombol Mulai/Selesai tahap SWE Fase 1
tidak cukup: project kecil (potong+las+finishing 1 hari, 1 tukang) terhitung 3 hari, tahap paralel
oleh orang yang sama terhitung dobel, dan hasilnya bergantung disiplin menekan tombol.

Solusi: **setiap malam mandor merencanakan pekerjaan besok** per orang; tiap orang
**mengonfirmasi kehadiran lewat Telegram**; **sore tiap orang melaporkan kerja nyatanya**.
Laporan sore = sumber kebenaran data kerja. Sekaligus menjadi fondasi SWE Fase 3 (kapasitas tim).

## 2. Siapa yang ikut

| Orang | Ikut penugasan | Kode absen |
|---|---|---|
| Tukang & kenek (level 5) | Ya, ditugaskan mandor | Hanya bila menjawab **Hadir** sebelum 06:00 |
| Driver (level 6) | Ya, ditugaskan mandor | Sama seperti tukang |
| Mandor (level 3) | Ya, menugaskan dirinya sendiri | Dianggap otomatis Hadir (keputusan Claude) |
| Admin, marketing, toko (level 2/4/7) | **Tidak** | Normal seperti sekarang, tak berubah |
| Owner (level 1) | Tidak (cadangan penugas) | — |

## 3. Alur harian

**Malam — mandor menyusun rencana besok** (halaman web "Rencana Besok"):
- Per orang: project + tahap + **Penuh / Setengah hari**; setengah hari boleh dibagi ke 2 tugas.
- Halaman menampilkan tiap project aktif + tahap yang sedang/berikutnya ("Project A: sedang las,
  tinggal finishing & pasang").
- Terisi otomatis dari rencana hari ini ("sama seperti hari ini"); mandor mengubah yang beda.
- Mandor memilih orang yang sudah ia pastikan sendiri bisa masuk.

**Pengingat ke mandor** (bila rencana belum disimpan), nada makin keras:
| Jam WIB | Pesan |
|---|---|
| 19:00 | Biasa: "Isi rencana besok" + link |
| 20:30 | Tegas: "Rencana besok BELUM diisi" |
| 21:00 | Mendesak: "Batas 22:00, setelah itu diambil alih Owner" |
| 22:00 | Masih kosong/belum lengkap → **notif Owner**; Owner yang menugaskan (halaman sama) |

**Konfirmasi ke tiap orang yang ditugaskan** (Telegram, tombol **Hadir / Tidak Hadir**):
- Terkirim otomatis saat rencana disimpan: "Besok kamu di Project A, las, penuh. Bersedia hadir?"
- Pengingat ke yang belum menjawab: **21:30**, **05:00** ("batas jam 06:00"), **05:45**
  ("15 menit lagi, tanpa jawaban tidak dapat kode absen"). Tidak ada pesan 22:00–05:00.
  Yang sudah menjawab tidak diingatkan lagi.
- Rencana diubah setelah konfirmasi terkirim → hanya orang yang tugasnya berubah ditanya ulang.

**Arti jawaban (keputusan Elvan):**
| Kondisi sampai 06:00 | Akibat |
|---|---|
| **Hadir** | Dapat kode absen 06:30 seperti biasa |
| **Tidak Hadir** | **Libur tanpa gaji** hari itu. Tidak dapat kode. Mandor + Owner langsung diberi tahu (cari pengganti). Tanpa batas jumlah untuk sekarang |
| **Tidak menjawab** | Tidak dapat kode → tak bisa absen → cron alpha mencatat **alpha**. Jalan keluar: Koreksi Owner |
| Tak ada di rencana sama sekali (lupa dimasukkan) | **Tetap dapat kode normal** (keputusan Claude: lupa mandor tak boleh menghanguskan gaji orang) |
| **Rencana tidak dibuat sama sekali** (mandor & Owner lupa) | **Kode dikirim normal ke semua** (disetujui Elvan) |

**Sore — laporan kerja tiap orang** (di form absen pulang, dari HP masing-masing):
- Terisi otomatis dari rencana: project, tahap, penuh/setengah. Sesuai → cukup tekan setuju;
  berubah → ganti sendiri.
- Per tugas: **Selesai / Lanjut besok** + catatan + foto opsional.
- **Tidak memblokir absen pulang** (kalau memblokir, gagal isi = tercatat lupa pulang = setengah
  hari dibayar). Yang tak melapor: rencana dipakai sebagai data, ditandai "tanpa laporan".

**Tahap selesai**: tahap berstatus selesai saat ada laporan "Selesai" untuk tahap itu
(mandor/Owner bisa mengoreksi). Tahap mulai = hari pertama ada laporan/rencana di tahap itu.
Tombol Mulai/Selesai tahap SWE Fase 1 **digantikan**.

## 4. Data kerja yang dihasilkan

- Satu orang di satu tanggal = **maksimal 1 orang-hari**, dibagi sesuai penugasan
  (Penuh = 1, Setengah = 0,5). Peran (tukang/kenek/driver/mandor) ikut tercatat.
- Inilah data "aktual" untuk proyek 2 (Survei vs Aktual) dan untuk SWE Fase 3 (kapasitas).
- Perkiraan surveyor di proyek 2 memakai satuan **setengah hari** yang sama (pilihan ½, 1, 1½, …).

## 5. Keputusan Elvan (terkunci)

1. Rencana disusun **malam hari** oleh mandor; bukan pagi; absen tidak dikunci menunggu mandor.
2. Pengingat mandor 3 kali dengan urgensi berbeda (19:00, 20:30, 21:00); 22:00 lempar ke Owner,
   Owner yang menugaskan.
3. Tiap orang yang ditugaskan dikonfirmasi lewat Telegram, diingatkan berulang; batas jawab 06:00.
4. Tidak menjawab sampai 06:00 = tidak dapat kode absen.
5. Tidak Hadir = **libur tanpa gaji**.
6. Yang ikut: tukang, kenek, driver, mandor. Orang kantor (admin, marketing, toko) tidak berubah.
7. Laporan sore oleh tiap orang sendiri.
8. Rencana tidak dibuat sama sekali → kode dikirim normal ke semua.

## 6. Keputusan Claude (cek)

- Pengisian rencana lewat **halaman web** (link dari Telegram), bukan form di chat — bot
  sekarang hanya bisa kirim; jawaban yang diterima bot cukup 2 tombol Hadir/Tidak Hadir.
- Mandor otomatis Hadir. Orang yang tak ada di rencana tetap dapat kode.
- Jadwal pengingat tukang 21:30 / 05:00 / 05:45, sunyi 22:00–05:00.
- Laporan sore di form absen pulang dan tidak memblokir.
- ~~Tidak Hadir sebagai override libur~~ — diganti status `tidak_hadir` (lihat 9-C).
  Owner diberi tahu tiap Tidak Hadir; rekap per bulan tersedia.

## 7. Batasan dan yang tidak dibangun

- Tidak mengubah absensi orang kantor, aturan telat, pulang cepat, denda checkpoint.
- Tidak membangun halaman kapasitas tim (Fase 3) — hanya datanya.
- Tidak ada batas jumlah Tidak Hadir (sementara).
- **Keamanan webhook**: `TelegramWebhookController` sekarang TIDAK memverifikasi asal request.
  Begitu webhook menerima tombol Hadir/Tidak Hadir (yang menentukan kode absen/gaji), wajib cek
  header `X-Telegram-Bot-Api-Secret-Token` (secret di `.env`, didaftarkan ulang via `setWebhook`)
  dan mencocokkan `from.id` dengan `users.telegram_chat_id`.
- Cron baru lewat crontab VPS dengan pola `CronAuth` + `/root/.canopi-cron-key`, idempotent
  (pesan tak terkirim ganda), dengan mode `&cek=1`. Jam crontab dalam UTC (WIB = UTC+7).
- `cron-kode-absen.php` (23:30 UTC = 06:30 WIB) diubah: lewati orang yang ditugaskan tapi tidak
  menjawab Hadir; perilaku lain tetap.
- Skema baru lewat SQL idempotent manual; test baru masuk `tests/guardrail/manifest.json`;
  tanpa emoji di blade; tanpa `confirm()`; stage per-nama.

## 8. Diserahkan ke builder

Nama tabel/kolom, tata letak halaman Rencana Besok, teks pesan Telegram, cara memindahkan
"Hitung Saran" PIC (SWE Fase 2) ke halaman rencana, urutan task.

## 9. Pertanyaan terbuka

**Dijawab Elvan 6 Nov (terkunci):**
- A. Tidak ada orang lapangan bergaji bulanan → "tanpa gaji" berlaku untuk semua yang ikut.
- B. Ada 2 mandor, bergantian (satu cadangan, tugas utamanya survei) → **satu "Mandor Bertugas"
  pada satu waktu**. Owner punya tombol **ganti Mandor Bertugas**; pengingat, halaman rencana, dan
  kewajiban menyusun rencana hanya untuk Mandor Bertugas. Mandor lain diperlakukan seperti
  orang lapangan biasa (bisa ditugaskan, ikut konfirmasi).
- C. Tidak Hadir **tampil terpisah** sebagai status "Tidak Hadir" (bukan libur biasa): menurunkan
  persen kehadiran, terlihat di kalender/rekap/statistik, gaji & uang makan Rp 0.
  **Menggantikan** keputusan bagian 6 soal override libur: dicatat sebagai baris `absensi`
  berstatus baru `tidak_hadir` (kolom status kemungkinan ENUM → ALTER lewat SQL manual dulu);
  `cron-alpha.php` dan `cron-kode-absen.php` wajib melewati status ini; status ini juga
  dikenali halaman absensi/rekap/slip yang menampilkan status.

**Keputusan Claude dari builder-check (cek):**
- Izin/sakit/cuti yang sudah disetujui untuk besok: orang itu tidak bisa ditugaskan (tampil
  "izin" di halaman rencana), tidak ditanya. Bila izin/sakit disetujui untuk tanggal yang sudah
  dijawab Tidak Hadir, **izin yang berlaku** (aturan izin yang ada tak diubah).
- Mode Luar Kota: tetap ikut penugasan seperti biasa.
- Jawaban boleh diubah sendiri lewat tombol yang sama sampai 06:00 (Tidak Hadir → Hadir
  menghapus catatan libur). Setelah 06:00: hanya Owner lewat Koreksi — **bukan** lewat aktivasi
  Kerja Hari Libur (yang memicu upah ekstra).
- "Rencana dibuat" = mandor menekan Simpan minimal sekali. Pada 22:00 Owner menerima daftar
  orang lapangan aktif yang belum ada di rencana (dan tak izin/libur). Yang tetap tak ada di
  rencana sampai pagi → kode normal.
- Hari libur pribadi besok: ditandai di halaman rencana. Menugaskan orang yang libur = memakai
  alur **Kerja Hari Libur yang sudah ada** (aturan upahnya tak diubah). Besok libur nasional:
  tak ada pengingat/rencana.
- Mandor bisa menandai dirinya Tidak Hadir di halaman rencana → Owner diberi tahu.
- Tombol Mulai/Selesai tahap **tidak dihapus**: tetap ada untuk mandor/Owner sebagai koreksi,
  tanggal lama tetap dipakai. Data kerja utama dari laporan sore. Proyek 2 hanya berlaku untuk
  project yang punya perkiraan survei (project baru), jadi tidak mencampur data lama.
  "Hitung Saran" PIC ikut tersedia di halaman rencana.
- Koreksi laporan "Selesai" yang salah: mandor/Owner; membuka kembali tahap yang tertutup.
