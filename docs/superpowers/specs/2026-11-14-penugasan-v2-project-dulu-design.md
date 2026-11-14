# Penugasan Harian v2 — "Project Dulu, Baru Orang" — Rancangan (DRAFT)

> **STATUS: DRAFT, BELUM DISETUJUI, BELUM ADA KODE.** Ditulis 14 Nov 2026 atas permintaan Elvan.
> **Syarat mulai dikerjakan:** uji kode pagi 15 Nov (nuyy Hadir -> dapat kode; Tukang las
> Tidak Hadir -> tak dapat kode & tercatat `tidak_hadir`, bukan alpha) LOLOS, dan
> go-live v1 (crontab + pengumuman) sudah stabil. Spec v1:
> `2026-11-06-penugasan-harian-design.md` (tetap berlaku; v2 hanya mengganti LAYAR menyusun rencana).

## 1. Masalah dengan layar sekarang (kata Elvan, 14 Nov)

Layar v1 = daftar orang, tiap orang punya dropdown tugas. Dropdown berisi tahap-tahap dari
semua project dalam satu daftar panjang berjudul abu-abu. Elvan: tak ada gambaran project mana
yang mendesak, tak ada deadline/status, mandor harus menebak. Konfirmasi Telegram-nya sendiri
sudah bagus dan TIDAK diubah.

## 2. Alur yang diinginkan (keputusan Elvan — terkunci)

1. Mandor/Owner membuka "Rencana Besok" -> **daftar SEMUA project aktif** tersusun lengkap dengan
   status prioritas dan deadline.
2. Urutan: **yang memesan duluan dikerjakan duluan**; project **express bisa di-PIN** supaya naik
   ke atas.
3. Project mendesak: **mandor wajib sadar (aware)**. Kalau tidak sadar -> ada konsekuensi
   untuk mandor.
4. Mandor memilih project -> tampil **langkah-langkah (tahap) pengerjaan + status tiap tahap**.
5. Per tahap, mandor memilih **pekerja yang cocok skill-nya** untuk memenuhi kebutuhan tahap itu.
6. Konfirmasi Hadir/Tidak Hadir ke karyawan lewat Telegram — **persis seperti sekarang**.

### 2b. Keputusan Elvan tambahan (14 Nov malam — terkunci)
- **Input tahap MANUAL harus bisa** (project manual tak punya tahap): admin/mandor mengetik tahap,
  **target selesai** dan **lama waktu (hari)** per tahap. Cukup isi dua dari tiga
  (mulai/selesai/lama), yang ketiga dihitung. Ini menggantikan "otomatis dari template" sebagai
  syarat minimum (template tetap boleh jadi pintasan).
- **Empat prioritas disetujui** (Normal / Prioritas / Urgent / Ditahan) dengan pin oleh
  Owner + Admin Operasional saja (usulan 3.1 diterima).
- **Konsekuensi mandor tahap pertama = TANPA uang** (eskalasi ke Owner + catatan riwayat,
  usulan 3.3 butir 1 diterima). Konsekuensi uang tetap ditunda sampai Elvan memutuskan sendiri.
- **Jawaban Elvan atas pertanyaan 4-9 (14 Nov malam, terkunci kecuali dicatat lain):**
  pengecek bahan & alat = **Admin Operasional** (mandor tak boleh mencentang untuk dirinya
  sendiri); daftar periksa **baku per tahap di Master Tahap, bisa ditambah per project**;
  **Ditahan per TAHAP** (lencana "Ditahan" di project); deadline `tgl_selesai_target` wajib
  diisi saat membuat project manual; kewajiban Urgent berpindah ke pengganti saat mandor tak masuk
  (Elvan menjawab "ya" — butuh fitur backup Sarip/Sahrul, belum ada). **Bahan tiba pagi hari:
  Elvan bilang "seharusnya bisa dibuka" — keputusan MASIH perlu pertimbangan, lihat 3.7.**
- **Gerbang Kesiapan (bagian 3.6): sebelum tahap dikerjakan WAJIB ada yang mengecek bahan DAN
  alat. Belum siap -> berstatus Ditahan, ALASAN WAJIB diisi.**

## 3. Usulan Claude (BUKAN keputusan; Elvan setujui/ubah)

### 3.1 Prioritas = kolom BARU di project, terpisah dari status siklus hidup
Status yang ada (persiapan/pengerjaan/selesai/garansi/dibatalkan/menunggu_dp) menjawab
"project ada di fase apa". Prioritas menjawab "seberapa cepat harus dikerjakan". Jangan dicampur.

| Prioritas | Arti | Perilaku |
|---|---|---|
| **Normal** (default) | Antrean biasa | Urut tanggal pesan (FIFO) |
| **Prioritas** | Di-pin Owner/Admin: express atau customer penting | Naik di atas semua Normal; antar-Prioritas urut tanggal pesan |
| **Urgent** | Deadline mepet / komplain / garansi darurat | Di atas semua; **wajib di-acknowledge mandor** (lihat 3.3) |
| **Ditahan** | Menunggu DP / material / keputusan customer | Turun ke bawah, abu-abu, tak bisa diberi tugas sampai dibuka |

Garansi/perbaikan tidak butuh status prioritas sendiri: ditandai lewat status siklus hidup
`garansi` yang sudah ada, dan Owner menaikkannya ke Urgent bila perlu.
Siapa boleh mengubah prioritas: **Owner dan Admin Operasional** saja (bukan mandor — kalau
mandor boleh menurunkan prioritas, tak ada yang mengawasi mandor).

### 3.2 Urutan & "deadline"
- Urutan dasar: Urgent > Prioritas > Normal > Ditahan; di dalam kelompok = tanggal project
  dibuat (pesan duluan di atas).
- Deadline = `projects.tgl_selesai_target` (SUDAH ada). Layar menampilkan "sisa N hari" dan
  menandai merah bila lewat. **Syarat:** field itu harus benar-benar diisi — project manual
  (`/projects/create`) kini boleh kosong; v2 perlu mewajibkan atau memberi peringatan.

### 3.3 "Mandor harus aware" — BAGIAN PALING SENSITIF, perlu keputusan Elvan
Definisi usulan: project Urgent muncul sebagai kartu merah di puncak layar mandor + notifikasi
Telegram ke Mandor Bertugas saat project dijadikan Urgent. Mandor menekan **"Saya sudah lihat"**
(dicatat waktu & siapa). "Aware" ≠ "bertindak": tombol itu saja bisa ditekan asal-asalan.
Karena itu usulanku untuk konsekuensi, **berurutan dari ringan ke berat**:

1. **(Direkomendasikan jadi awal)** Tanpa uang: kalau belum di-acknowledge dalam N jam,
   notifikasi ke Owner + tercatat di riwayat mandor. Kalau sampai rencana malam disimpan
   TANPA project Urgent mendapat tugas satu pun, Owner diberi tahu otomatis.
2. Konsekuensi uang (denda/potongan) — **hanya kalau Elvan memutuskan**, dan hanya untuk
   kelalaian yang bisa dibuktikan (mis. project Urgent tak diberi tugas dua hari berturut-turut
   padahal pekerja tersedia), BUKAN sekadar "tidak menekan tombol". Alasan: potongan gaji atas
   dasar tombol mudah dimanipulasi dan terasa tak adil; aturan uang di project ini selalu
   dijaga mentok Rp 0 & dapat dikoreksi Owner. Kalau dipilih, ia mengikuti pola denda checkpoint
   yang sudah ada dan wajib punya jalur Koreksi Owner.
3. Bila mandor sedang tak masuk (izin/cuti), kewajiban pindah ke pengganti (lihat memory
   `project-backup-mandor-sarip-sahrul`) — jangan menghukum orang yang sedang libur.

### 3.4 Pilih tahap lalu pekerja sesuai skill
Fondasi SUDAH ada dan dipakai ulang: tahap produksi per project (`project_tahap`), skill karyawan
dan badge cocok/sibuk/tidak-cocok + hitung saran jumlah tukang/kenek (SWE Fase 2,
`ProjectController::saranPic`). v2 hanya memindahkan pemakaiannya ke alur "project -> tahap ->
pekerja". **Tabel `penugasan_harian`, `konfirmasi_hadir`, cron, dan konfirmasi Telegram tidak
berubah.**

### 3.5 Urutan tahap
Usulan: tahap boleh diberi pekerja di hari yang sama dengan tahap sebelumnya **hanya bila**
mandor sengaja memilih (peringatan lembut, bukan larangan) — karena potong+las project kecil
kadang memang sehari.

### 3.6 Gerbang Kesiapan (bahan + alat) — ATURAN BARU Elvan
Sebelum sebuah tahap boleh diberi pekerja, seseorang mengecek: **semua bahan** untuk tahap itu
ada, dan **semua alat** ada. Belum lengkap -> tahap tak bisa ditugaskan, statusnya **Ditahan**,
dan **alasan wajib diisi** (pilihan alasan + catatan: mis. besi belum datang, mesin rusak,
menunggu DP, menunggu keputusan customer).

Usulan bentuk (sederhana dulu, Elvan koreksi):
- Tiap tahap punya **daftar periksa** (item bahan + item alat). Sumber item: diketik manual;
  nanti bisa diisi otomatis dari template tahap / daftar material RAB bila project punya RAB.
  Fakta: project manual tak punya RAB, jadi item manual WAJIB bisa; **belum ada data stok/
  inventori alat di sistem** — jangan berjanji "cek otomatis ke stok", ini centang manual
  oleh manusia.
- Setiap item dicentang oleh pengecek (dicatat siapa & kapan). Semua tercentang -> tahap
  "Siap" -> baru muncul di pilihan penugasan.
- **Tahap Ditahan** menyimpan alasan + siapa yang menahan + kapan; dibuka lagi (alasan teratasi)
  lewat aksi eksplisit, tercatat.
- Gerbang dipasang di **malam penugasan** (bukan pagi), karena pekerja sudah dikonfirmasi malam
  itu. Konsekuensinya: bahan yang baru tiba pagi tak bisa dipakai hari itu — keputusan
  yang perlu Elvan sadari (lihat pertanyaan 8).
- **Ditahan di level TAHAP, bukan seluruh project** (usulanku): potong bisa jalan sementara
  finishing menunggu powder coating. Project menampilkan lencana "Ditahan" bila ada tahap
  ditahan dan tak ada tahap lain yang bisa dikerjakan. Elvan menyebut "status project" —
  dikonfirmasi di pertanyaan 9.

### 3.7 Bahan tiba pagi hari — usulan Claude, menunggu pertimbangan Elvan
Masalahnya: pekerja dikonfirmasi malam, sebelum 06:00, dan kode absen terkirim 06:30. Kalau
tahap baru "Siap" pukul 08:00, orang yang seharusnya mengerjakannya sudah terlanjur tak
ditugaskan, atau sudah ditugaskan ke hal lain. Usulan jalan tengah, **penugasan bersyarat**:
1. Malam hari, mandor BOLEH menugaskan pekerja ke tahap yang masih **Ditahan**, ditandai
   "bersyarat: menunggu kesiapan". Pekerja tetap dikonfirmasi seperti biasa.
2. Pagi hari, Admin Ops mencentang daftar periksa sampai batas jam yang disepakati (mis. 07:00).
   Siap -> penugasan jalan. Tak siap -> mandor diberi tahu untuk memindahkan orang ke tahap lain
   hari itu (kode absen tetap terkirim; yang bersyarat tak boleh membuat orang dicap alpha).
3. Aturan "tidak menjawab = tak dapat kode" TIDAK berubah.
Kelemahan: lebih rumit dan menambah satu keadaan baru; bila Elvan lebih suka sederhana,
alternatifnya gerbang tegas malam hari saja (bahan tiba pagi = baru dipakai besok). Putuskan
sebelum Fase C2 dibangun.

## 4. CELAH YANG HARUS DITUTUP SEBELUM v2 BERGUNA (terbukti 6 Nov)

Tahap produksi hanya terbentuk otomatis saat **RAB disetujui** (`RabController::approve`).
Project manual tanpa lead (jalur kerja Elvan sejak 6 Nov) **tidak punya tahap sama sekali**,
sehingga layar "pilih project -> muncul langkah" akan kosong untuk project itu. Perlu salah satu:
(a) project manual otomatis dibuatkan tahap dari template sesuai `jenis_project`, atau
(b) tombol "Tambah tahap / terapkan template" di halaman project. Rekomendasi: (a)+(b).
Tanpa ini v2 tak bisa dipakai untuk project manual — **ini syarat nomor satu.**

## 5. Usulan bertahap (jangan sekaligus)

| Fase | Isi | Menyentuh uang? |
|---|---|---|
| A | Input tahap manual (nama, urutan, target selesai, lama hari) — tutup celah bagian 4 | Tidak |
| B | Kolom prioritas + pin + tampilan daftar project berurut & deadline (read-only dulu) | Tidak |
| C | Alur baru layar: project -> tahap -> pekerja sesuai skill (konfirmasi Telegram tetap) | Tidak |
| C2 | Gerbang Kesiapan bahan+alat + status Ditahan beralasan (3.6) | Tidak |
| D | Acknowledge Urgent + notifikasi + eskalasi ke Owner (konsekuensi tanpa uang) | Tidak |
| E | Konsekuensi uang, **bila dan hanya bila** Elvan putuskan | **Ya** |

Layar v1 dipertahankan sampai Fase C terbukti, supaya go-live tak tertunda.

## 6. Pertanyaan terbuka untuk Elvan

Sudah dijawab 14 Nov: prioritas (4 jenis), pin oleh Owner+Admin Ops, konsekuensi mandor tanpa
uang dulu, pengecek = Admin Ops, daftar periksa baku per tahap, Ditahan per tahap, deadline
project manual wajib, kewajiban Urgent pindah ke pengganti.

**Masih terbuka:**
- **Bahan tiba pagi hari** — bagian 3.7: penugasan bersyarat (usulan) atau gerbang tegas malam saja.
- Batas jam pagi Admin Ops harus sudah mencentang (mis. 07:00?) bila penugasan bersyarat dipilih.
- Kewajiban Urgent bagi pengganti mandor tak bisa dikerjakan sampai Sarip terdaftar di app.

## 7. Di luar lingkup v2
Perubahan konfirmasi Telegram; kapasitas tim (SWE Fase 3); evaluasi produktivitas (Fase 4);
Survei vs Aktual (spec 2026-11-06 kedua) — tetap di antreannya sendiri.
