<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StatusKepsekTest extends TestCase
{
    use RefreshDatabase;

    public function test_kedua_role_dapat_memperbarui(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        foreach ([User::factory()->create(), User::factory()->kepsek()->create()] as $pengguna) {
            $this->actingAs($pengguna)
                ->post('/status-kepsek', ['status' => 'Rapat'])
                ->assertRedirect(route('status-kepsek.form'))
                ->assertSessionHas('sukses');
        }

        Http::assertSentCount(2);
    }

    public function test_status_di_luar_daftar_ditolak(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->post('/status-kepsek', ['status' => 'Sedang Ngopi'])
            ->assertSessionHasErrors('status');

        // Ditolak sebelum menyentuh n8n sama sekali.
        Http::assertNothingSent();
    }

    public function test_menampilkan_status_terkini_dari_database(): void
    {
        DB::table('status_kepsek')->insert([
            'id' => 1,
            'status' => 'Dinas Luar',
            'estimasi_kembali' => 'pukul 14.00',
            'keterangan' => 'Rapat di dinas pendidikan',
            'diperbarui_pada' => now(),
            'diperbarui_oleh' => 'TAS',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/status-kepsek')
            ->assertOk()
            ->assertSee('Dinas Luar')
            ->assertSee('pukul 14.00')
            ->assertSee('Rapat di dinas pendidikan');
    }

    /** Tabel baris tunggal ini bisa masih kosong pada pemasangan baru. */
    public function test_halaman_tetap_terbuka_saat_status_belum_pernah_diisi(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/status-kepsek')
            ->assertOk()
            ->assertSee('Belum pernah diisi');
    }

    /**
     * E1: kegagalan n8n tidak boleh meninggalkan jejak yang menyatakan
     * sesuatu telah terjadi.
     */
    public function test_kegagalan_n8n_tidak_menyisakan_jejak_dan_tidak_memalsukan_sukses(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->actingAs(User::factory()->create())
            ->post('/status-kepsek', ['status' => 'Cuti'])
            ->assertSessionHasErrors('status')
            ->assertSessionMissing('sukses');

        $this->assertDatabaseCount('aktivitas', 0);
    }

    /**
     * E2: 401 berarti kredensial server, bukan pengguna. Pengguna tidak
     * dikeluarkan dari sesinya — inilah perbedaannya dari kunciUlang() lama.
     */
    public function test_401_menampilkan_halaman_kredensial_tanpa_mengeluarkan_pengguna(): void
    {
        Http::fake(['*' => Http::response([], 401)]);

        $pengguna = User::factory()->create();

        $this->actingAs($pengguna)
            ->post('/status-kepsek', ['status' => 'Cuti'])
            ->assertStatus(503)
            ->assertSee('N8N_SECRET');

        $this->assertAuthenticatedAs($pengguna);
        $this->assertDatabaseCount('aktivitas', 0);
    }

    public function test_keberhasilan_dicatat_di_aktivitas(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $pengguna = User::factory()->create();

        $this->actingAs($pengguna)->post('/status-kepsek', [
            'status' => 'Tidak di tempat',
            'estimasi' => 'pukul 15.00',
        ]);

        $this->assertDatabaseHas('aktivitas', [
            'user_id' => $pengguna->id,
            'aksi' => 'status_kepsek',
            'subjek_tipe' => 'status_kepsek',
        ]);
    }

    /**
     * AC-1: kredensial server tidak boleh sampai ke browser.
     */
    public function test_secret_tidak_muncul_di_halaman(): void
    {
        config(['n8n.secret' => 'rahasia-sekali']);

        DB::table('status_kepsek')->insert([
            'id' => 1,
            'status' => 'Di sekolah',
            'diperbarui_pada' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/status-kepsek')
            ->assertOk()
            ->assertDontSee('rahasia-sekali');
    }
}
