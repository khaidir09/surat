<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * public.agenda — CRUD penuh oleh Laravel (K3).
 */
class Agenda extends Model
{
    use SoftDeletes;

    protected $table = 'agenda';

    protected $guarded = ['id'];

    /** created_at dan updated_at baru ada sejak §5.3. */
    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Kegiatan dianggap jatuh pada sebuah bulan bila rentangnya bersinggungan
     * dengan bulan itu — bukan hanya bila dimulai di dalamnya. Kegiatan yang
     * melintasi pergantian bulan harus muncul di kedua bulan.
     */
    public function scopePadaBulan(Builder $kueri, int $tahun, int $bulan): Builder
    {
        $awal = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = date('Y-m-t', strtotime($awal));

        return $kueri->where('tanggal_mulai', '<=', $akhir)
            ->where('tanggal_selesai', '>=', $awal);
    }

    public function scopeCari(Builder $kueri, ?string $kata): Builder
    {
        if (blank($kata)) {
            return $kueri;
        }

        $pola = '%' . $kata . '%';

        return $kueri->where(fn (Builder $q) => $q
            ->where('judul', 'ilike', $pola)
            ->orWhere('kategori', 'ilike', $pola)
            ->orWhere('tempat', 'ilike', $pola));
    }
}
