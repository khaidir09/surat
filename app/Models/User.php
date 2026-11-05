<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /** Staf Tata Administrasi Sekolah — memproses surat dan data master. */
    public const ROLE_TAS = 'tas';

    /** Kepala sekolah — hanya melihat, kecuali status kehadirannya sendiri. */
    public const ROLE_KEPSEK = 'kepsek';

    /**
     * Seluruh role yang dikenal, beserta labelnya untuk tampilan.
     *
     * @var array<string, string>
     */
    public const ROLE = [
        self::ROLE_TAS => 'Staf TAS',
        self::ROLE_KEPSEK => 'Kepala Sekolah',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nama',
        'email',
        'password',
        'role',
        'aktif',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'aktif' => 'boolean',
        ];
    }

    public function punyaRole(string ...$role): bool
    {
        return in_array($this->role, $role, true);
    }

    public function adalahTas(): bool
    {
        return $this->punyaRole(self::ROLE_TAS);
    }

    public function labelRole(): string
    {
        return self::ROLE[$this->role] ?? $this->role;
    }

    /**
     * Halaman awal setiap role. Kepsek tidak punya akses ke antrean
     * persetujuan, jadi tidak boleh didaratkan di sana.
     *
     * Kepsek mendarat di statistik, bukan riwayat: riwayat adalah daftar
     * terpaginasi 25 baris — alat pencarian, bukan alat pemantauan. TAS tetap
     * mendarat di antrean, karena itulah pekerjaan hariannya; mendaratkannya
     * di ringkasan akan menambah satu klik pada alur yang dipakai puluhan
     * kali sehari (K-S11 pada docs/specs/statistik-surat.md).
     */
    public function rutaBeranda(): string
    {
        return $this->adalahTas() ? route('antrean.index') : route('statistik.index');
    }
}
