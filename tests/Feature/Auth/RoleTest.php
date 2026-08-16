<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Matriks §6 spesifikasi. Diuji lewat permintaan HTTP langsung, bukan lewat
 * UI — tombol yang disembunyikan di Blade tidak dianggap sebagai pengaman.
 */
class RoleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function jalurKhususTas(): array
    {
        return [
            'antrean' => ['/antrean'],
            'pegawai' => ['/pegawai'],
            'siswa' => ['/siswa'],
            'pengguna' => ['/pengguna'],
            'tambah agenda' => ['/agenda/create'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function jalurSemuaRole(): array
    {
        return [
            'riwayat' => ['/riwayat'],
            'agenda' => ['/agenda'],
            'status kepsek' => ['/status-kepsek'],
        ];
    }

    #[DataProvider('jalurKhususTas')]
    public function test_kepsek_ditolak_pada_jalur_khusus_tas(string $jalur): void
    {
        $this->actingAs(User::factory()->kepsek()->create())
            ->get($jalur)
            ->assertForbidden();
    }

    #[DataProvider('jalurKhususTas')]
    public function test_tas_diterima_pada_jalur_khusus_tas(string $jalur): void
    {
        $this->actingAs(User::factory()->create())
            ->get($jalur)
            ->assertOk();
    }

    #[DataProvider('jalurSemuaRole')]
    public function test_kedua_role_dapat_membuka_jalur_bersama(string $jalur): void
    {
        $this->actingAs(User::factory()->kepsek()->create())->get($jalur)->assertOk();
        $this->actingAs(User::factory()->create())->get($jalur)->assertOk();
    }

    /**
     * Menembak endpoint tulis secara langsung — tanpa pernah melihat tombolnya —
     * tetap harus ditolak.
     */
    public function test_kepsek_tidak_dapat_memutuskan_surat_lewat_post_langsung(): void
    {
        $this->actingAs(User::factory()->kepsek()->create())
            ->post('/antrean/surat_tugas/1/putuskan', ['aksi' => 'setujui', 'nomor' => '421'])
            ->assertForbidden();
    }

    public function test_kepsek_tidak_dapat_membuat_akun_lewat_post_langsung(): void
    {
        $this->actingAs(User::factory()->kepsek()->create())
            ->post('/pengguna', [
                'nama' => 'Penyusup',
                'email' => 'penyusup@example.test',
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
                'role' => User::ROLE_TAS,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'penyusup@example.test']);
    }

    public function test_kepsek_boleh_mengunduh_pdf(): void
    {
        $this->actingAs(User::factory()->kepsek()->create())
            ->get('/antrean/surat_tugas/1/pdf')
            ->assertOk();
    }

    /**
     * {jenis} dibatasi pada nilai yang diterima webhook n8n. Nama tabel
     * "surat_keterangan" bukan salah satunya — n8n memakai "keterangan_aktif".
     */
    public function test_jenis_di_luar_daftar_menghasilkan_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/riwayat/surat_keterangan/1')
            ->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->get('/riwayat/keterangan_aktif/1')
            ->assertOk();
    }
}
