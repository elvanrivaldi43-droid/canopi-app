<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RabKatalog extends Model {
    protected $table = 'rab_katalog';
    public $timestamps = false;
    protected $fillable = [
        'produk_kode','judul','deskripsi','foto_url','sumber_foto',
        'atap_kode','konstruksi_label','addon_default',
        'kisaran_harga_min','kisaran_harga_max','tipe_lokasi','tag','urutan','is_active'
    ];

    protected $casts = ['addon_default' => 'array'];

    public function produk() {
        return $this->belongsTo(RabProduk::class, 'produk_kode', 'kode');
    }
    public function atap() {
        return $this->belongsTo(RabAtap::class, 'atap_kode', 'kode');
    }

    public function kisaranHargaFormatted(): string {
        $min = 'Rp ' . number_format($this->kisaran_harga_min / 1000000, 0) . ' jt';
        $max = 'Rp ' . number_format($this->kisaran_harga_max / 1000000, 0) . ' jt';
        return "$min – $max";
    }

    public static function byProduk(string $produkKode): \Illuminate\Database\Eloquent\Collection {
        return self::where('produk_kode', $produkKode)
            ->where('is_active', 1)
            ->orderBy('urutan')
            ->get();
    }
}
