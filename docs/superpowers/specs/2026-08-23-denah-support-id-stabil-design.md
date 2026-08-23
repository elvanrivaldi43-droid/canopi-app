# Denah Support ID Stabil — Desain

Tanggal: 2026-08-23 (disetujui Elvan lewat sesi brainstorming 22-23 Agustus malam)
Status: SPEC DISETUJUI KONSEP — menunggu review tertulis Elvan sebelum plan implementasi
File utama yang terdampak: `public/js/denah-editor.js` (+ test `tests/rangka/*.mjs`,
pendaftaran di `tests/guardrail/manifest.json`)

## 1. Masalah (akar, bukan gejala)

Garis support otomatis (grid/spacing) TIDAK punya identitas stabil:

- ID-nya turunan nomor posisi (`Sh_<garis>_<potongan>` / `Sv_...`) yang berubah tiap
  spacing/bentuk berubah → flag "dikecualikan" dan pilihan besi per-garis bisa nyasar
  ke garis lain (perilaku aneh yang sudah didokumentasi di CLAUDE.md utang #8).
- Garis digenerate ulang tiap render → tidak bisa di-list, di-Fokus, atau dikelola
  seperti frame (F1..Fn) / tiang (T1..Tn).
- Sentuh = langsung bisa geser, dan geser garis grid otomatis "naik kelas" jadi entri
  manual → insiden 22 Agustus: jari pertama pinch-zoom menyeret garis & melahirkan
  support manual siluman (S2), grid jadi bolong-bolong, pemulihan susah.

Serangkaian tambalan 22 Agustus (rollback pinch, tombol "Pulihkan yang dihapus")
menutup gejala, bukan akar. Spec ini menggantinya dengan model identitas + interaksi baru.

## 2. Keputusan desain (semua sudah dipilih Elvan eksplisit)

### 2.1 Dua fase: pratinjau → terkunci (opsi C)

- **Fase pratinjau** (default, sama seperti sekarang): input spacing per-sumbu
  (cm/kolom) menghasilkan garis hidup yang berubah mengikuti bentuk & angka spacing.
  Belum ada identitas, belum ada edit per-garis.
- **Fase terkunci:** susunan dibekukan jadi daftar entri S1..Sn dengan ID permanen.
- **Momen kunci = otomatis (opsi b):** terjadi saat pertama kali user memakai fitur
  per-garis. Pintu masuknya dua (dua-duanya hanya di tab Support): (1) menyalakan
  toggle move di quickbar, atau (2) membuka header panel "Support (n) — kelola per
  garis" yang di fase pratinjau tampil sebagai satu baris ajakan (panel isinya baru
  terbentuk setelah kunci). Saat terkunci muncul info kecil satu kali: "Susunan
  dikunci". Bisa di-Undo (kunci = mutasi model biasa yang lewat `pushUndo`).
- Setelah terkunci, input spacing disembunyikan/diganti tombol **"Susun Ulang"**:
  konfirmasi eksplisit + peringatan "editan per-garis akan di-reset", bisa di-Undo.
  Tidak ada regenerate diam-diam.

### 2.2 Identitas & geometri entri terkunci

- **Garis grid terkunci menyimpan JALUR, bukan ujung:** `{axis:'h'|'v', pos:<cm>}`.
  Ujung dihitung tiap render dari perpotongan jalur dengan polygon frame saat itu
  (scanX/scanY yang sudah ada). Konsekuensi yang disengaja:
  - Frame digeser/ditambah kotak → ujung support otomatis memanjang/memendek/terbelah.
  - Satu jalur yang terpotong coakan = beberapa potongan, semuanya tetap milik satu
    ID (panjang dijumlah untuk hitungan besi).
  - Area frame BARU yang tidak dilewati jalur lama = kosong; sistem tidak menambah
    garis sendiri. Solusi user: support manual atau "Susun Ulang".
- **Support manual tetap menyimpan ujung nyata** `{a:{x,y}, b:{x,y}}` (perilaku
  sekarang). Tidak ikut memanjang saat frame berubah — penyesuaian lewat tarik ujung.
- **Penomoran:** ditetapkan SEKALI saat kunci — horizontal atas→bawah dulu, lalu
  vertikal kiri→kanan — dan tidak pernah berubah setelahnya. Entri baru (manual atau
  hasil Susun Ulang) melanjutkan nomor. Panel boleh menampilkan urut posisi; nama tetap.
- Pilihan besi per-garis (`matOverride`) di fase terkunci di-key ke ID stabil ini.
  Override milik garis grid pra-kunci dimigrasikan saat kunci terjadi.

### 2.3 Interaksi: toggle move + sorot + pindah angka

- **Toggle move (ikon) di quickbar, default MATI.** Mati = kanvas murni lihat/zoom/pan;
  mustahil menggeser apa pun. Nyala = elemen milik TAB AKTIF saja yang bisa disorot;
  domain lain tetap terkunci (di tab Support, frame & tiang mustahil tergeser, dst).
- **Sorot dulu, aksi kemudian:** tap toleran di kanvas (threshold ~24px, garis
  terdekat menang; tap lagi di tempat sama = pindah ke kandidat terdekat berikutnya)
  ATAU tombol Fokus di panel. Garis tersorot menyala + baris panelnya ter-highlight
  (sinkron dua arah). Salah sorot tanpa akibat — sorot tidak menggeser apa pun.
- **Pindah = ketik angka, TANPA tekan-tahan:** form arah + cm RELATIF dari posisi
  sekarang, lalu Terapkan. Arah difilter: garis horizontal cuma ↑/↓, vertikal cuma
  ←/→, manual 4 arah. Bisa di-Undo.
- **Tarik ujung titik:** hanya untuk support manual, hanya saat tersorot.
  Ujung garis grid tidak bisa ditarik (otomatis dari frame — lihat 2.2).
- **Menu tekan-tahan untuk support DIHAPUS** — semua aksi lewat panel saat tersorot.
  (Menu tekan-tahan tiang tidak disentuh.)
- **Kursor/crosshair: DIBUANG** (keputusan eksplisit) — fungsi presisi ditutupi
  panel Fokus + tap toleran + tap-ganti-kandidat.

### 2.4 Panel daftar (bukan dropdown — keputusan eksplisit)

Daftar gulir yang bisa dilipat di tab Support (pola panel "Daftar Support Manual"
yang sudah ada, di-upgrade):

```
Support (12)   [toggle Semua] [lipat/buka]
[ceklis] S1 · datar · 149cm dari atas   [Fokus]
[ceklis] S2 · datar · 298cm dari atas   [Fokus]
[  -   ] S3 · ... (nonaktif = abu-abu)
[ceklis] S4 · tegak · 100cm dari kiri   [Fokus]
--- baris tersorot melebar: ---
Pindah: [arah][cm][Terapkan] · [Ganti besi] · [Hapus*]
```

- Ceklis per baris = aktif/nonaktif. **Garis grid tidak punya hapus permanen** —
  nonaktif (reversibel) saja; hilang dari gambar & hitungan, tetap di daftar.
- **Support manual punya Hapus beneran** (bisa di-Undo).
- Toggle "Semua" = aktifkan/nonaktifkan semua sekaligus.
- Info posisi di tiap baris ("datar · 149cm dari atas") supaya identifikasi tanpa tap.
- Dilipat secara default; daftar panjang di-scroll (strip ribbon sudah max-height 45vh).

### 2.5 Kompatibilitas & migrasi

- Model lama (spacing + `removed{}` + `supportsManual`) terbuka sebagai FASE
  PRATINJAU — nol migrasi paksa, perilaku persis sekarang sampai user menguncinya.
- Saat kunci: garis hasil spacing → entri jalur; `removed{}` posisi yang cocok →
  entri lahir langsung nonaktif; `supportsManual` → entri manual (ujung nyata);
  `matOverride` grid dimigrasikan ke ID baru; `removed{}` lama dikosongkan.
- `buildMembers` fase pratinjau TIDAK berubah (harness ekuivalensi 25.200 variasi
  per-sumbu harus tetap lolos). Fase terkunci = cabang baru di `buildMembers`.
- Tombol "Pulihkan yang dihapus" (tambalan 22 Ags) tetap bekerja untuk model
  pratinjau; DIHAPUS dari UI fase terkunci (terserap ceklis + toggle Semua).
- Autosave: model terkunci menambah key baru (mis. `supportsLocked[]`, `lockSeq`);
  server hanya menyimpan JSON — tidak ada perubahan backend/DB.

## 3. Batasan yang disengaja (bukan bug — jangan salah lapor)

1. Area frame baru di luar jalur lama tidak otomatis dapat garis baru (lihat 2.2).
2. Support manual tidak memanjang otomatis saat frame berubah.
3. Sorot di garis dempet (<~jarak jari) bisa kena tetangganya dulu — by design
   ditutup lewat tap-ganti-kandidat + panel Fokus; tidak dianggap bug.
4. "Susun Ulang" me-reset editan per-garis — selalu lewat konfirmasi.

## 4. Ruang lingkup bertahap (disetujui)

- **Tahap ini:** model baru HANYA untuk Support. Interaksi Frame & Tiang tidak
  disentuh (baru divalidasi 21-22 Ags). Toggle move di tab Rangka/Tiang belum
  melakukan apa-apa terhadap frame/tiang pada tahap ini (boleh disembunyikan di
  tab selain Support).
- **Tahap berikutnya (terpisah, kalau pola terbukti):** perluasan pola
  toggle+sorot+angka ke Frame (sekalian backlog "drag sudut & sisi rangka") & Tiang.

## 5. Testing

- Test murni (node, `tests/rangka/*.mjs`, daftarkan di `tests/guardrail/manifest.json`
  DI COMMIT YANG SAMA):
  - Kunci: penomoran H-dulu-lalu-V, migrasi removed/matOverride/manual.
  - Jalur: ujung mengikuti polygon (memanjang saat frame melebar; terbelah di coakan;
    jumlah panjang potongan benar), nonaktif mengeluarkan dari hitungan.
  - Pindah angka: relatif, arah terfilter per sumbu, Undo mengembalikan.
  - Ekuivalensi fase pratinjau: `buildMembers` model lama tak berubah hasil.
- Validasi manual Elvan di HP (checklist di plan): kunci otomatis, sorot/tap-ganti,
  pindah angka, ceklis & toggle semua, pinch aman dengan toggle mati, denah lama
  masih kebuka.

## 6. Di luar lingkup

- Perubahan backend/DB/deploy.
- Perluasan ke Frame/Tiang (tahap berikutnya).
- Auto-skip support dempet sisi frame (keputusan 22 Ags: biarkan, user nonaktifkan
  sendiri).
