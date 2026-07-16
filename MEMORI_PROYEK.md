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
| 10 | **6 permintaan update UI DenahEditor (16 Juli, BELUM DIBAHAS TUNTAS — brainstorming baru mulai, terhenti di tahap "pecah scope atau tidak")** | 🔴 BRAINSTORMING BELUM SELESAI | Raw request dari Elvan, urut asli: **(1)** mau ada zoom in/zoom out di kanvas denah — tampilan di HP kurang besar/kurang leluasa (saat ini `SC` auto-fit ke viewBox 560x400, tak ada zoom/pan sama sekali). **(2)** titik sudut (`<circle vh>`, r=10) kebesaran dibanding garis (frame stroke-width=4) — mau garis+label (F1/F2 dst+angka cm) yang besar/jelas, titik sudut dibikin kecil (hit-area transparan r=24 buat tap tetap besar, cuma dot yang keliatan dikecilin). **(3)** mode "Ganti besi" sekarang harus diaktifkan dulu (klik tombol mode `besi` di toolbar) baru bisa tap batang. Elvan mau LANGSUNG bisa: tekan batang manapun kapan saja (tanpa pindah mode) langsung muncul opsi ganti/hapus besi. DAN kalau ditekan-lalu-digeser (bukan cuma tap), besi/batang itu SENDIRI bisa dipindah posisinya (drag-to-reposition, kemungkinan besar utk support manual/besi tambahan, bukan frame poligon utama — belum dikonfirmasi ke Elvan). **(4)** pas mindah besi (poin 3), mau ada bantuan "cari as tengah" (snap ke sumbu tengah/centerline) pas drag. **(5)** bikin support manual (2-klik titik A→B) susah dibikin LURUS, hasil tarikan sering bengkok — ortho-snap yang sudah ada (poin drag vertex bentuk) TIDAK diterapkan ke drag titik-ujung support manual (`drag.type==='sup'`). **(6)** saran "kotak support" (`DenahConv.saranKotak(lebar,target)`) cuma 1 dimensi & sering nyisa (contoh Elvan: lebar 730, target 100 → nyisa ~30cm janggal). Elvan mau hasil bagi genap/rapi TANPA SISA di KEDUA arah (lebar & panjang) — boleh beda angka per arah (misal 700×730 jadi kotak 100×81, asal masing2 pas tanpa sisa, JANGAN dipaksa persegi/sama). Ini perlu `S.kotak` jadi 2 nilai terpisah (horizontal vs vertikal), bukan cuma perbaikan pembulatan. **Progres brainstorming:** baru sampai tahap tanya-Elvan "pecah 3 kelompok (A:zoom+ukuran+ortho-support / B:drag-pindah-besi+snap-tengah / C:saran-kotak-2-arah) vs 1 spec besar sekaligus" — user reject pertanyaan itu utk minta dicatat dulu, **BELUM DIJAWAB**. |

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

1. **#10 (6 update UI DenahEditor):** lanjut brainstorming dari titik terhenti — tanya Elvan: pecah 3 kelompok (A/B/C, lihat tabel #10) atau 1 spec besar sekaligus. Kalau pecah: A dulu (paling cepat/aman), lalu B, lalu C. **LANJUT DARI SINI.**
2. Foto **bar #12** cutting list PA-DUTA (opsional) → tutup validasi 4x8=9.

## CATATAN PENTING
- Deploy = `git push` → GitHub Actions FTP ke Niagahoster (±1-2 menit). `main` = production.
- Aset JS statik WAJIB cache-bust (`?v=filemtime`) — browser agresif nyimpen JS lama (pelajaran sesi ini).
- Bug JS di blade tak ketahuan `node --check` (cuma sintaks) — pakai **jsdom** untuk uji runtime konstruksi/onChange (terbukti ampuh nemu TDZ).
- **`LOG_LEVEL=error` di production** → `Log::info()` kefilter, nggak nyampe `laravel.log`. Debug log sementara pakai `Log::error()` biar pasti kebaca (16 Juli, kasus WF #8).
