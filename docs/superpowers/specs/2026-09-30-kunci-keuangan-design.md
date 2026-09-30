# Kunci Keuangan (ketik ulang password) — Design

Tanggal: 30 September 2026 · Disetujui Elvan (brainstorming: opsi A, 5 menit, Owner bebas)

## Masalah

Aplikasi selalu dalam keadaan login. HP karyawan yang tertinggal/dipinjam membuka
slip gaji, kasbon, dan tarif harian pemiliknya tanpa hambatan apa pun.

## Keputusan Elvan (jangan didebat ulang)

- Ketik ulang **password login** (bukan PIN) — fitur konfirmasi password bawaan Laravel sudah ada.
- Terbuka **5 menit** sejak password diketik (bukan sejak aktivitas terakhir).
- **Owner (level 1) bebas**: tak pernah diminta password, tak ada yang disamarkan.
- **Gaji orang lain: hanya Owner** — audit 30 Sep: aturan ini SUDAH berlaku di semua halaman
  (blok gaji karyawan/show/edit/create `bolehFinansial` owner-only; rekap, penggajian,
  kasbon, KPI index, setting-gaji level:1). Tak ada perubahan untuk ini.
- Karyawan lupa password → Owner reset di menu Karyawan (sudah ada). Admin bisa reset level 3-7.

## Mekanisme

- `App\Services\KunciKeuangan::terbuka(?int $level, ?int $konfirmasiAt, ?int $now = null): bool`
  — murni. True bila level 1, atau `now - konfirmasiAt` ∈ [0, 300]. Tanpa konfirmasi = false.
  Batas: `KunciKeuangan::DETIK = 300`.
- Middleware `keuangan[:mode]` (alias di `bootstrap/app.php`, kelas `App\Http\Middleware\KeuanganTerkunci`):
  - mode `kunci` (default): belum terbuka → `redirect()->guest(route('password.confirm'))`
    (simpan halaman tujuan; setelah benar kembali ke sana lewat `redirect()->intended`).
  - mode `samar`: tak memblokir; halaman tetap dibuka, angka uang disamarkan di view.
  - Kedua mode menambah header `Cache-Control: no-store, private` (tombol Back tak boleh
    memunculkan halaman beruang dari cache setelah terkunci).
- Blade `@keuanganTerbuka ... @else ... @endkeuanganTerbuka` (`Blade::if` di `AppServiceProvider`)
  membaca user + `session('auth.password_confirmed_at')` lewat `KunciKeuangan`.
- Penanda samar: partial `partials/rp-samar.blade.php` = `Rp •••` + tautan "Lihat" ke
  `GET /buka-keuangan?kembali=<path>` (route `keuangan.buka`): validasi `kembali` (harus diawali
  satu `/`, bukan `//`, tanpa skema), simpan sebagai url.intended, redirect ke `password.confirm`.
- `POST /confirm-password` diberi `throttle:6,1` (tanpa ini password bisa ditebak-tebak dari HP curian).
- Halaman `auth/confirm-password.blade.php` diterjemahkan ke Indonesia + catatan
  "Lupa password? Minta reset ke Owner."

## Cakupan

**Dikunci penuh** (`keuangan`): `penggajian.slip-saya`, `penggajian.slip`, `kasbon.karyawan.index`,
`kasbon.karyawan.surat`, `absensi.rekap-bulanan`, `kpi.detail`, `kpi.ujian.hasil`.
(Route owner-only tidak perlu — Owner selalu lolos.)

**Disamarkan** (`keuangan:samar` + `@keuanganTerbuka`):
- Dashboard admin/supervisor/marketing/teknisi/driver/toko: Gaji/Hari, Uang Makan/Hari.
- Profil: Estimasi Gaji, Gaji Harian, Uang Makan, No. Rekening.
- Absensi (`absensi.index`, halaman absen harian): Total Uang Makan, Potongan Telat,
  rincian potongan per hari, badge `-Rp..rb` (saat samar badge menampilkan label status).

**Sengaja tidak termasuk:** pesan Telegram bernominal (masuk chat pribadi karyawan itu sendiri),
pesan JSON sesaat setelah absen ("potongan Rp X" — dilihat orangnya tepat saat absen), log bensin
(biaya operasional), seluruh RAB/penawaran/harga (kerja sehari-hari; modal sudah owner-only).
Konstanta JS `gajiBulanan`/`totalCicilanAktif` di `kasbon/saya` dibiarkan: halamannya sudah
dikunci penuh, angka itu hanya ada di halaman yang sudah lolos password.

## Risiko yang diterima

- Timer 5 menit dihitung sejak password diketik → saat dipakai terus, karyawan diminta ulang tiap 5 menit.
- Owner bebas → HP Owner yang tertinggal tanpa kunci layar membuka semua gaji (keputusan Elvan).
- Safari iOS bisa memulihkan halaman dari bfcache walau `no-store` [Menebak] — tak ditambal.
- Karyawan yang login berbulan-bulan lewat "ingat saya" mungkin lupa password → minta reset ke Owner.
  Umumkan ke karyawan sebelum fitur live.

## Pengujian

`tests/keamanan/test_kunci_keuangan.php` (manifest, tanpa DB): logika `terbuka` (Owner, belum
konfirmasi, tepat 300 dtk, 301 dtk, konfirmasi di masa depan, null), validasi `kembali`, dan
cek struktural via `artisan route:list --json` bahwa 7 route kunci berbeban middleware `keuangan`
dan 7 route samar berbeban `keuangan:samar`.

## Pemasangan

Tanpa SQL. Push biasa. Urutan commit aman (middleware & alias lebih dulu, baru dipakai).
