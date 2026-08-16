<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * public.siswa — CRUD penuh oleh Laravel (K3). n8n hanya membacanya.
 */
class Siswa extends Model
{
    use SoftDeletes;

    protected $table = 'siswa';

    protected $guarded = ['id'];

    /**
     * Tabel ini tidak punya created_at maupun updated_at, dan §5.3 tidak
     * menambahkannya. Jejak perubahan ada di app.aktivitas.
     */
    public $timestamps = false;

    /**
     * tanggal_lahir sengaja tidak di-cast ke date: kolomnya bertipe text dan
     * isinya format bebas hasil ketikan lewat chatbot (E12, K8). Mem-parse-nya
     * akan melempar pengecualian pada data yang selama ini dianggap sah.
     */
    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function kelasJurusan(): string
    {
        return trim(implode(' ', array_filter([$this->kelas, $this->jurusan]))) ?: '—';
    }

    public function scopeCari(Builder $kueri, ?string $kata): Builder
    {
        if (blank($kata)) {
            return $kueri;
        }

        $pola = '%' . $kata . '%';

        return $kueri->where(fn (Builder $q) => $q
            ->where('nama', 'ilike', $pola)
            ->orWhere('nis_nisn', 'ilike', $pola)
            ->orWhere('nama_orang_tua', 'ilike', $pola));
    }

    /**
     * @return array<int, string>
     */
    public static function daftarKelas(): array
    {
        return static::query()->whereNotNull('kelas')->distinct()
            ->orderBy('kelas')->pluck('kelas')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function daftarJurusan(): array
    {
        return static::query()->whereNotNull('jurusan')->distinct()
            ->orderBy('jurusan')->pluck('jurusan')->all();
    }
}
