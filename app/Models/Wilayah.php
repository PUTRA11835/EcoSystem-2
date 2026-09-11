<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Referensi wilayah administratif Indonesia (tabel `wilayah`).
 *
 * Hierarki ditentukan oleh format `kode` (dipisah titik):
 *   11              Provinsi        (Region)
 *   11.01           Kabupaten/Kota  (City)
 *   11.01.01        Kecamatan       (District)
 *   11.01.01.2001   Kelurahan/Desa  (Rural/Urban Village)
 *
 * Anak langsung sebuah node = kode berawalan "{induk}." dengan tepat satu
 * segmen lebih dalam. Provinsi = kode tanpa titik.
 */
class Wilayah extends Model
{
    protected $table = 'wilayah';
    protected $primaryKey = 'kode';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['kode', 'nama'];

    /**
     * Ambil anak langsung dari sebuah kode wilayah, terurut menurut nama.
     * $parent kosong → daftar provinsi.
     */
    public static function children(string $parent = ''): Collection
    {
        $query = static::query()->orderBy('nama');

        if ($parent === '') {
            return $query->where('kode', 'not like', '%.%')->get(['kode', 'nama']);
        }

        return $query->where('kode', 'like', $parent . '.%')
                     ->where('kode', 'not like', $parent . '.%.%')
                     ->get(['kode', 'nama']);
    }
}
