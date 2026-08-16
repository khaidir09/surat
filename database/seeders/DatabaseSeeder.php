<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Membuat akun TAS pertama — satu-satunya jalan masuk, karena tidak ada
     * registrasi mandiri dan hanya role tas yang boleh membuat akun lain.
     *
     * Kata sandi sengaja tidak pernah ditulis di dalam berkas ini. Isi lewat
     * SEED_TAS_EMAIL dan SEED_TAS_PASSWORD di .env, atau jawab pertanyaan yang
     * muncul saat perintah dijalankan dari terminal.
     */
    public function run(): void
    {
        $email = env('SEED_TAS_EMAIL') ?: $this->tanya('Email akun TAS pertama');
        $sandi = env('SEED_TAS_PASSWORD') ?: $this->tanya('Kata sandi (minimal 8 karakter)', rahasia: true);

        if (blank($email) || strlen((string) $sandi) < 8) {
            $this->command?->error('Email wajib diisi dan kata sandi minimal 8 karakter. Seeder dibatalkan.');

            return;
        }

        $pengguna = User::updateOrCreate(
            ['email' => $email],
            [
                'nama' => 'Staf TAS',
                'password' => $sandi,
                'role' => User::ROLE_TAS,
                'aktif' => true,
            ]
        );

        $this->command?->info("Akun TAS siap: {$pengguna->email}. Ubah namanya lewat halaman Kelola pengguna.");
    }

    private function tanya(string $pertanyaan, bool $rahasia = false): ?string
    {
        if (! $this->command) {
            return null;
        }

        return $rahasia
            ? $this->command->secret($pertanyaan)
            : $this->command->ask($pertanyaan);
    }
}
