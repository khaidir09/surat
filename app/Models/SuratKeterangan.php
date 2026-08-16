<?php

namespace App\Models;

use App\Models\Concerns\HanyaBaca;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * public.surat_keterangan — milik n8n, dibaca saja (§5.5).
 *
 * Perhatikan: pada webhook n8n jenisnya bernama "keterangan_aktif", bukan
 * "surat_keterangan" seperti tabel ini (§5.2).
 */
class SuratKeterangan extends Model
{
    use HanyaBaca;

    protected $table = 'surat_keterangan';

    protected $guarded = ['*'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * siswa_id nullable dan tanpa foreign key, jadi relasi ini bisa kosong
     * maupun menunjuk baris yang sudah tidak ada (E9).
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}
