<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/pengguna')->assertRedirect('/login');
    }

    public function test_kredensial_benar_membuat_sesi(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'rahasia123',
        ])->assertRedirect(route('antrean.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_kepsek_mendarat_di_statistik_bukan_antrean(): void
    {
        $user = User::factory()->kepsek()->create(['password' => 'rahasia123']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'rahasia123',
        ])->assertRedirect(route('statistik.index'));
    }

    public function test_kata_sandi_salah_ditolak(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'salah',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_akun_nonaktif_tidak_dapat_masuk(): void
    {
        $user = User::factory()->nonaktif()->create(['password' => 'rahasia123']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'rahasia123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * Menonaktifkan akun harus berlaku seketika, bukan menunggu sesi berakhir.
     */
    public function test_sesi_yang_sedang_berjalan_diputus_saat_akun_dinonaktifkan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/pengguna')->assertOk();

        $user->update(['aktif' => false]);

        $this->get('/pengguna')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_keluar_menghapus_sesi(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /**
     * Setelah sesi kedaluwarsa di tengah pekerjaan, pengguna harus kembali ke
     * halaman yang tadi dituju, bukan ke beranda (E16).
     */
    public function test_kembali_ke_halaman_yang_dituju_setelah_masuk(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->get('/pengguna')->assertRedirect('/login');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'rahasia123',
        ])->assertRedirect('/pengguna');
    }
}
