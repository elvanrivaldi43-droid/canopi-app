# MEMORI PROYEK — Denah Interaktif RAB (sesi 14–16 Juli 2026)

> Catatan kerja hidup. Fokus: fitur **Denah Interaktif di RAB Opsi** (spec: `docs/superpowers/specs/2026-07-14-denah-interaktif-rab-design.md`).
> Semua sudah **di-push & deploy ke production** (`main`), kecuali yang ditandai.

## STATUS BUG / TUGAS

| # | Hal | Status | Catatan |
|---|-----|--------|---------|
| 1 | Tahap 1A backend (stok per-material, jalur `tipe:denah`, `stokMap()`, reproduksi PA-DUTA) | ✅ SELESAI | 5 task, tes standalone hijau |
| 2 | Tahap 1B DenahEditor + integrasi RAB opsi | ✅ SELESAI | subagent-driven, review opus clean |
| 3 | `buildPenawaran()` cabang denah (PDF penawaran) | ✅ SELESAI | tampil ukuran+frame/support/tiang+atap |
| 4 | Iterasi editor: tiang tak di sudut, Lebar/Panjang resize, label support S1..Sn, target-ketuk lebar, hapus tombol Kanopi + Besi Tambahan, denah jadi default, indikator autosave | ✅ SELESAI | dari tes browser Elvan |
| 5 | Ortho-snap (seret sudut auto-lurus vertikal/horizontal) | ✅ SELESAI | bikin lekukan lebih mudah |
| 6 | Cache-bust `denah-editor.js` (`?v=filemtime`) | ✅ SELESAI | cegah browser pakai JS lama |
| 7 | **Bug TDZ `let _hitungTimer` → `var`** (akar harga-0 + autosave-mati + jarak-kosong) | ✅ SELESAI & DIKONFIRMASI ELVAN | denah-default bikin editor saat load → `onChange`→`jadwalkanHitung` pakai `_hitungTimer` sebelum deklarasi `let` → TDZ throw → konstruksi editor gagal (members tak sampai mesin) + IIFE load abort (jarak profil tak ke-load). `var` ter-hoist = aman. Terverifikasi jsdom + Elvan (harga Rp14,8jt keluar, rincian besi ada, autosave jalan, jarak & jenis-kerja terisi) |
| 8 | **WF masih terhitung 6m (bukan 12m)** | ✅ SELESAI & DIKONFIRMASI (16 Juli) — **ternyata bukan bug** | Dugaan lama (nama tak cocok) GUGUR — SQL cek: `WF 200 12m` aktif=1, `panjang_batang_cm=1200`, persis sama nama dropdown. Diverifikasi lewat log debug sementara (`Log::error` — `Log::info` kefilter krn `LOG_LEVEL=error` production, pelajaran baru): `stok_wf:1200` terbaca benar. "2 btg" yang tampil di UI itu **matematis minimal**, bukan bug: support 700cm + tiang 300cm + 300cm = total 1300cm, > 1 batang 12m (1200cm), jadi butuh 2 batang apa pun susunannya. Dibanding stok lama 600cm yang bakal jadi 3 batang, ini pembuktian stok 1200 sudah kepakai. Log debug sudah dibersihkan dari kode. |
| 9 | **Freehand susah bikin bentuk PA-DUTA** | ✅ SELESAI & DIKONFIRMASI ELVAN (16 Juli) di production | Fitur **Gabungan Kotak**: tombol "+ Tambah Kotak" di DenahEditor — ketuk sisi lurus, geser kotak (ukuran diketik) buat nempel keluar (nambah/sayap) atau ke dalam (lekukan), arah otomatis dari posisi drag (tanpa toggle). 1 algoritma sama (`DenahConv.combineBox`, tanda `depth` yang beda). Spec: `docs/superpowers/specs/2026-07-16-gabungan-kotak-design.md`. Plan+ledger: `docs/superpowers/plans/2026-07-16-gabungan-kotak-implementation.md`. Dibangun subagent-driven, final review opus (2 pass) READY TO MERGE — 2 bug penting kepegat & diperbaiki sebelum nyampe Elvan (fokus input hilang tiap ketik; divergensi validasi offset preview-vs-apply). Sisi miring TETAP lewat drag manual yang sudah ada (tak diubah). **Dites langsung Elvan di `/rab-opsi` production — jalan normal semua** (tambah/lekukan/Terapkan/Undo/blok lain tak terganggu). |
| 10 | **6 permintaan update UI DenahEditor (16 Juli), dipecah 3 kelompok** | 🟡 **Kelompok A & B SELESAI, DI-PUSH ke production (17 Juli)** — A dikonfirmasi Elvan, **B BELUM dikonfirmasi Elvan** (baru deploy) — C belum dikerjakan | Dipecah: **A** = zoom+ukuran+ortho-support (poin 2+5 dari 6 permintaan asli, +layout ribbon+fullscreen yang muncul pas brainstorming). **B** = drag-pindah-besi+snap-tengah (poin 3+4 asli). **C** = saran-kotak-2-arah (poin 6 asli, `DenahConv.saranKotak` 1D → 2D independen horizontal/vertikal). **Kelompok A: 10 iterasi (16-17 Juli), dikonfirmasi Elvan lewat 5 foto HP asli** — detail lengkap di riwayat sebelumnya, spec: `docs/superpowers/specs/2026-07-16-denah-ui-kelompok-a-design.md`. **Kelompok B (17 Juli): 6 task via subagent-driven-development, code review tiap task + final whole-branch review (opus), READY TO MERGE**, spec: `docs/superpowers/specs/2026-07-17-denah-ui-kelompok-b-design.md`, plan+ledger: `docs/superpowers/plans/2026-07-17-denah-ui-kelompok-b-implementation.md`, `.superpowers/sdd/progress.md`. **Yang jadi**: drag-pindah **Tiang** (tap tanpa gerak = tetap hapus spt biasa, tekan-geser = pindah posisi), drag-pindah **Support manual** garis utuh (A+B geser bareng, beda dari drag-per-ujung yang sudah ada), drag-pindah **Kotak support** (dari Gabungan Kotak) utuh (semua sudutnya geser bareng) — ketiganya pakai **1 mesin snap generik** (`DenahConv.findAlignSnap`+`collectAlignCandidates`): soft-snap opsional ke tiang/support lain ATAU ke titik tengah sisi frame terkini (otomatis ikut kalau sisi berubah panjang), ditemani garis bantu putus-putus, bisa dilewatin (bukan snap paksa). **2 bug ketemu & diperbaiki lewat review sebelum sampai Elvan**: (1) untuk drag garis-utuh/kotak-utuh, cek "sudah pas align-snap jangan di-grid-snap lagi" awalnya salah titik (endpoint, bukan titik tengah/centroid hasil rekomputasi) — DIPERBAIKI 2x (titik yang benar, lalu ketahuan `===` floating-point tak reliable ~40% kasus nyata, **fix akhir: cek KEHADIRAN guide, bukan bandingkan nilai** — pelajaran penting kalau nanti bikin drag jenis baru yang posisinya hasil rekomputasi/rata-rata, BUKAN nilai tersimpan langsung); (2) hit-area kotak-support sempat tak digating ke mode Bentuk, diam-diam blokir tap "Ganti Besi"/"+ Sudut" di mode lain. Item dari Kelompok A yang masih ditunda (magnifier drag, input panjang ketik support manual, `.de-matmenu` iOS Safari) tetap belum dikerjakan, sama seperti sebelumnya. |

## FILE YANG DIUBAH (sesi ini, semua di `main`, sudah di-push)

- `public/js/denah-editor.js` (BARU) — `DenahConv` (geometri murni denah→members, tes `node tests/rangka/test_konverter.mjs`) + kelas `DenahEditor` (SVG editor per-blok, classic-script `globalThis`, TANPA ESM export).
- `resources/views/rab-opsi/index.blade.php` — tipe blok `denah`, mount editor, `bacaBlok`/`isiBlok`/`tambahBlok` denah, `buildPenawaran` denah, indikator autosave, cache-bust, **fix TDZ `var _hitungTimer`**.
- `app/Services/CuttingService.php` — `potong($pieces, $stock=null)`.
- `app/Services/RangkaDesignService.php` — `hitung(..., array $stok=[])`.
- `app/Http/Controllers/CuttingController.php` — `stokMap()` + cabang `tipe:'denah'` di `hitungSatuBlok`.
- `tests/rangka/test_{stok,stok_material,denah_blok,paduta}.php`, `test_konverter.mjs` — tes.
- `tests/rangka/denah_prototype.html` — prototype UX (referensi).
- `docs/superpowers/plans/2026-07-14-denah-rab-tahap1a-engine.md`, `2026-07-15-denah-rab-tahap1b-editor.md` — plan.
- `CLAUDE.md` — RESUME POINT.
- **SQL (dijalankan Elvan di phpMyAdmin):** kolom `master_material.panjang_batang_cm` (default 600) + set WF = 1200.

## LANGKAH SELANJUTNYA (urut)

1. **#10 Kelompok B: SUDAH DEPLOY, BELUM DITES Elvan** (`/rab-opsi`, HP asli) — drag tiang/support-garis/kotak-support + snap-tengah. **Elvan pilih lanjut ke Kelompok C dulu tanpa tes B** (17 Juli) — risiko: kalau nanti ada bug, lebih susah pastikan asalnya dari B atau C krn dua-duanya belum divalidasi terpisah. Tetap MENUNGGU TES kapan pun Elvan sempat.
2. **#10 Kelompok C (saran-kotak-2-arah):** SEDANG DIKERJAKAN — brainstorming dimulai 17 Juli (lanjut dari sini kalau sesi terputus).
3. Item tunda dari Kelompok A (opsional, kalau Elvan minta): magnifier drag, input panjang support manual, fix `.de-matmenu` iOS Safari kalau ada laporan nyata.
4. Foto **bar #12** cutting list PA-DUTA (opsional) → tutup validasi 4x8=9.

## CATATAN PENTING
- Deploy = `git push` → GitHub Actions FTP ke Niagahoster (±1-2 menit). `main` = production.
- Aset JS statik WAJIB cache-bust (`?v=filemtime`) — browser agresif nyimpen JS lama (pelajaran sesi ini).
- Bug JS di blade tak ketahuan `node --check` (cuma sintaks) — pakai **jsdom** untuk uji runtime konstruksi/onChange (terbukti ampuh nemu TDZ).
- **`LOG_LEVEL=error` di production** → `Log::info()` kefilter, nggak nyampe `laravel.log`. Debug log sementara pakai `Log::error()` biar pasti kebaca (16 Juli, kasus WF #8).
- **`position:fixed` RUSAK di Safari iOS** kalau elemen bersarang di dalam kontainer `overflow-y:auto` + `-webkit-overflow-scrolling:touch` (app pakai ini di `.page-content`, `layouts/app.blade.php`) — elemen ikut ke-scroll bareng kontainer, bukan nempel viewport beneran. Solusi: reparent elemen ke `document.body` selama butuh fixed-fullscreen, kembalikan posisi asli setelah selesai (lihat `_wireFullscreen()` di `denah-editor.js`). Berlaku juga buat popup/modal lain yang dibuat di masa depan — CEK dulu apa bersarang di `.page-content` sebelum pakai `position:fixed` polos.
- **Screenshot dari Elvan (upload-inbox `/root/inbox/`) sangat efektif buat diagnosis** — beberapa bug UI (fullscreen rusak, ribbon ketutup tombol, popup kepotong tepi layar) baru ketemu akar masalah PASTINYA setelah lihat foto langsung, bukan dari deskripsi teks saja. Selalu cek `/root/inbox/` kalau Elvan bilang "aku kirim gambar/foto".
- **Kalau perbaiki bug "snap/lurus hilang saat dilepas jari" (grid-snap override ortho-snap), cek SEMUA cabang `drag.type` yang mirip** — sempat kelewat 1 cabang (`vert`) padahal sudah diperbaiki di cabang lain (`sup`) dengan pola identik, ketahuan cuma karena Elvan tes ulang & lapor lagi.
