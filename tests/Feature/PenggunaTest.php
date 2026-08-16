<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenggunaTest extends TestCase
{
    use RefreshDatabase;

    private function tas(): User
    {
        return User::factory()->create();
    }

    public function test_tas_dapat_membuat_akun_kepsek(): void
    {
        $this->actingAs($this->tas())
            ->post('/pengguna', [
                'nama' => 'Kepala Sekolah',
                'email' => 'kepsek@example.test',
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
                'role' => User::ROLE_KEPSEK,
            ])
            ->assertRedirect(route('pengguna.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'kepsek@example.test',
            'role' => User::ROLE_KEPSEK,
            'aktif' => true,
        ]);
    }

    public function test_email_ganda_ditolak(): void
    {
        $ada = User::factory()->create();

        $this->actingAs($this->tas())
            ->post('/pengguna', [
                'nama' => 'Kembar',
                'email' => $ada->email,
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
                'role' => User::ROLE_TAS,
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_role_di_luar_daftar_ditolak(): void
    {
        $this->actingAs($this->tas())
            ->post('/pengguna', [
                'nama' => 'Aneh',
                'email' => 'aneh@example.test',
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
                'role' => 'admin',
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_kata_sandi_kosong_saat_mengubah_berarti_tidak_diganti(): void
    {
        $tas = $this->tas();
        $target = User::factory()->kepsek()->create(['password' => 'lamalama']);
        $sebelum = $target->password;

        $this->actingAs($tas)
            ->put("/pengguna/{$target->id}", [
                'nama' => 'Nama Baru',
                'email' => $target->email,
                'password' => '',
                'password_confirmation' => '',
                'role' => User::ROLE_KEPSEK,
                'aktif' => '1',
            ])
            ->assertRedirect(route('pengguna.index'));

        $target->refresh();

        $this->assertSame('Nama Baru', $target->nama);
        $this->assertSame($sebelum, $target->password);
    }

    public function test_tidak_dapat_menonaktifkan_akun_sendiri(): void
    {
        $tas = $this->tas();

        $this->actingAs($tas)
            ->delete("/pengguna/{$tas->id}")
            ->assertSessionHasErrors('pengguna');

        $this->assertTrue($tas->fresh()->aktif);
    }

    /**
     * Kalau akun TAS aktif terakhir dinonaktifkan, tidak ada lagi yang dapat
     * membuat akun — sistem terkunci untuk selamanya.
     */
    public function test_tas_aktif_terakhir_tidak_dapat_dinonaktifkan(): void
    {
        $satu = $this->tas();
        $dua = $this->tas();

        // Selama masih ada dua, menonaktifkan salah satu boleh.
        $this->actingAs($satu)->delete("/pengguna/{$dua->id}")->assertRedirect();
        $this->assertFalse($dua->fresh()->aktif);

        // Menyisakan nol harus ditolak — di sini lewat penurunan role.
        $this->actingAs($satu)
            ->put("/pengguna/{$satu->id}", [
                'nama' => $satu->nama,
                'email' => $satu->email,
                'password' => '',
                'password_confirmation' => '',
                'role' => User::ROLE_KEPSEK,
                'aktif' => '1',
            ])
            ->assertSessionHasErrors('pengguna');

        $this->assertTrue($satu->fresh()->adalahTas());
    }

    /**
     * AC-1: kredensial server tidak boleh bocor ke halaman mana pun.
     */
    public function test_secret_n8n_tidak_muncul_di_halaman(): void
    {
        config(['n8n.secret' => 'kode-rahasia-server']);

        $halaman = ['/pengguna', '/riwayat', '/agenda', '/status-kepsek', '/antrean'];

        foreach ($halaman as $jalur) {
            $this->actingAs($this->tas())
                ->get($jalur)
                ->assertOk()
                ->assertDontSee('kode-rahasia-server');
        }
    }
}
