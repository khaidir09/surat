<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * public.pegawai — CRUD penuh oleh Laravel (K3).
 *
 * Seluruh kolom selain id dan created_at nullable, termasuk nama (E11).
 * Tampilan tidak boleh mengandaikan satu pun terisi.
 */
class Pegawai extends Model
{
    use SoftDeletes;

    protected $table = 'pegawai';

    protected $guarded = ['id'];

    /** Punya created_at bawaan n8n, tetapi tidak punya updated_at. */
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'aktif' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function namaTampil(): string
    {
        return filled($this->nama) ? $this->nama : "(tanpa nama, id {$this->id})";
    }

    public function scopeCari(Builder $kueri, ?string $kata): Builder
    {
        if (blank($kata)) {
            return $kueri;
        }

        $pola = '%' . $kata . '%';

        return $kueri->where(fn (Builder $q) => $q
            ->where('nama', 'ilike', $pola)
            ->orWhere('nip', 'ilike', $pola)
            ->orWhere('jabatan', 'ilike', $pola));
    }
}
