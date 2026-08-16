<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Dukungan\MembuatDataSurat;
use Tests\TestCase;

/**
 * Matriks §6 spesifikasi. Diuji lewat permintaan HTTP langsung, bukan lewat
 * UI — tombol yang disembunyikan di Blade tidak dianggap sebagai pengaman.
 */
class RoleTest extends TestCase
{
    use MembuatDataSurat, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Halaman antrean dan tautan PDF memanggil n8n. Tanpa fake, tes
        // matriks role akan memproses surat sungguhan.
        Http::fake(['*' => Http::response(['items' => [], 'url' => 'https://contoh.test/x.pdf'])]);
    }

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
        $id = $this->buatSuratTugas(['pdf_url' => 'https://contoh.test/x.pdf']);

        // Bukan 200: berkasnya ada di Supabase Storage, jadi Laravel
        // mengalihkan ke sana.
        $this->actingAs(User::factory()->kepsek()->create())
            ->get("/surat/surat_tugas/$id/pdf")
            ->assertRedirect('https://contoh.test/x.pdf');
    }

    /**
     * {jenis} dibatasi pada nilai yang diterima webhook n8n. Nama tabel
     * "surat_keterangan" bukan salah satunya — n8n memakai "keterangan_aktif".
     */
    public function test_jenis_di_luar_daftar_menghasilkan_404(): void
    {
        $id = $this->buatSuratKeterangan();

        // Ditolak oleh batasan route, bukan oleh controller: nama tabelnya
        // memang "surat_keterangan", tetapi bukan itu nilai yang dikenal n8n.
        $this->actingAs(User::factory()->create())
            ->get("/riwayat/surat_keterangan/$id")
            ->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->get("/riwayat/keterangan_aktif/$id")
            ->assertOk();
    }
}
