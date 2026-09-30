{{-- Angka uang disamarkan (Kunci Keuangan). Tombol Lihat -> ketik ulang password -> kembali ke halaman ini.
     Opsional: ['tanpaRp' => true] untuk data non-rupiah (mis. nomor rekening). --}}
<span>@unless($tanpaRp ?? false)Rp @endunless&bull;&bull;&bull;</span> <a href="{{ route('keuangan.buka', ['kembali' => request()->getRequestUri()]) }}" style="font-size:11px;font-weight:600;color:#3B82F6;text-decoration:none;white-space:nowrap;">Lihat</a>
