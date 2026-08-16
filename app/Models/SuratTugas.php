<?php

namespace App\Models;

use App\Models\Concerns\HanyaBaca;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * public.surat_tugas — milik n8n, dibaca saja (§5.5).
 */
class SuratTugas extends Model
{
    use HanyaBaca;

    protected $table = 'surat_tugas';

    protected $guarded = ['*'];

    /**
     * Tabel ini hanya punya created_at; tidak ada updated_at yang bisa
     * dipelihara Eloquent.
     */
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'tanggal_surat' => 'date',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'created_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Postgres tidak menjamin urutan baris tanpa ORDER BY, dan junction-nya
     * tidak punya kolom urutan (E14). Diurutkan berdasarkan pegawai_id agar
     * daftar petugas setidaknya konsisten di setiap pemuatan halaman —
     * bukan urutan yang bermakna, hanya urutan yang tidak berubah-ubah.
     */
    public function petugas(): BelongsToMany
    {
        return $this->belongsToMany(
            Pegawai::class,
            'surat_tugas_petugas',
            'surat_tugas_id',
            'pegawai_id'
        )->orderBy('surat_tugas_petugas.pegawai_id');
    }
}
