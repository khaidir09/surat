<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * app.aktivitas — jejak audit. Ditulis, tidak pernah diubah (§5.5).
 */
class Aktivitas extends Model
{
    protected $table = 'aktivitas';

    /**
     * Hanya punya created_at; sebuah jejak yang bisa diperbarui bukan jejak.
     */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'aksi',
        'subjek_tipe',
        'subjek_id',
        'detail',
        'ip',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'detail' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    public static function catat(string $aksi, string $subjekTipe, ?int $subjekId = null, array $detail = []): self
    {
        return static::create([
            'user_id' => Auth::id(),
            'aksi' => $aksi,
            'subjek_tipe' => $subjekTipe,
            'subjek_id' => $subjekId,
            'detail' => $detail ?: null,
            'ip' => Request::ip(),
            'created_at' => now(),
        ]);
    }
}
