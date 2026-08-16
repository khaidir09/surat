<?php

namespace App\Models;

use App\Models\Concerns\HanyaBaca;
use Illuminate\Database\Eloquent\Model;

/**
 * public.status_kepsek — baris tunggal, dibaca saja (§5.5).
 *
 * Perubahan status dikirim ke webhook n8n, bukan ditulis langsung ke tabel
 * ini. n8n yang memperbarui barisnya sekaligus menyiarkan perubahannya ke
 * kanal lain. Menulis langsung dari sini akan membuat tabel berubah tanpa
 * siapa pun diberi tahu.
 */
class StatusKepsek extends Model
{
    use HanyaBaca;

    protected $table = 'status_kepsek';

    protected $guarded = ['*'];

    public $timestamps = false;

    /**
     * Pilihan yang sama persis dengan index.html:405-409. Nilainya dikirim
     * apa adanya ke n8n, jadi penulisannya tidak boleh diubah.
     *
     * @var array<int, string>
     */
    public const PILIHAN = [
        'Di sekolah',
        'Dinas Luar',
        'Rapat',
        'Tidak di tempat',
        'Cuti',
    ];

    protected function casts(): array
    {
        return [
            'diperbarui_pada' => 'datetime',
        ];
    }

    /**
     * Tabel ini dirancang berisi satu baris (id default 1), tetapi tidak ada
     * constraint yang memaksanya. Diambil yang terbaru agar tetap masuk akal
     * bila suatu saat berisi lebih dari satu.
     */
    public static function terkini(): ?self
    {
        return static::query()->orderByDesc('diperbarui_pada')->first();
    }
}
