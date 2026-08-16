<?php

namespace Tests\Feature\Antrean;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Dukungan\MembuatDataSurat;
use Tests\TestCase;

class PutuskanTest extends TestCase
{
    use MembuatDataSurat, RefreshDatabase;

    private User $tas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tas = User::factory()->create();
        $this->actingAs($this->tas);
    }

    public function test_menyetujui_meneruskan_payload_yang_benar(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $this->post('/antrean/surat_tugas/12/putuskan', ['aksi' => 'setujui', 'nomor' => '68'])
            ->assertRedirect(route('antrean.index'))
            ->assertSessionHas('sukses');

        Http::assertSent(fn (Request $r) => $r->data() === [
            'id' => 12, 'jenis' => 'surat_tugas', 'aksi' => 'setujui', 'nomor' => '68',
        ]);

        $this->assertDatabaseHas('aktivitas', [
            'user_id' => $this->tas->id,
            'aksi' => 'setujui',
            'subjek_tipe' => 'surat_tugas',
            'subjek_id' => 12,
        ]);
    }

    public function test_menolak_tidak_memerlukan_nomor(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $this->post('/antrean/keterangan_aktif/3/putuskan', ['aksi' => 'tolak'])
            ->assertRedirect(route('antrean.index'));

        Http::assertSent(fn (Request $r) => $r->data()['aksi'] === 'tolak'
            && $r->data()['nomor'] === null);

        $this->assertDatabaseHas('aktivitas', ['aksi' => 'tolak', 'subjek_id' => 3]);
    }

    /**
     * E4: divalidasi di server, bukan hanya di klien seperti index.html:612
     * yang dapat dilewati begitu saja.
     */
    public function test_nomor_bukan_angka_ditolak_sebelum_menyentuh_n8n(): void
    {
        Http::fake();

        $this->post('/antrean/surat_tugas/12/putuskan', ['aksi' => 'setujui', 'nomor' => '68a'])
            ->assertSessionHasErrors('nomor');

        Http::assertNothingSent();
        $this->assertDatabaseCount('aktivitas', 0);
    }

    public function test_menyetujui_tanpa_nomor_ditolak(): void
    {
        Http::fake();

        $this->post('/antrean/surat_tugas/12/putuskan', ['aksi' => 'setujui', 'nomor' => ''])
            ->assertSessionHasErrors('nomor');

        Http::assertNothingSent();
    }

    public function test_aksi_di_luar_daftar_ditolak(): void
    {
        Http::fake();

        $this->post('/antrean/surat_tugas/12/putuskan', ['aksi' => 'hapus'])
            ->assertSessionHasErrors('aksi');

        Http::assertNothingSent();
    }

    /**
     * Pesan validasi harus terbaca manusia, bukan kunci terjemahan mentah.
     *
     * Pernah terjadi sungguhan: APP_LOCALE dan APP_FALLBACK_LOCALE sama-sama
     * "id" sementara direktori lang belum ada, sehingga staf hanya melihat
     * "validation.required" dan tidak tahu apa yang harus diperbaiki.
     */
    public function test_pesan_validasi_terbaca_bukan_kunci_mentah(): void
    {
        Http::fake();

        $this->post('/antrean/surat_tugas/12/putuskan', [])->assertSessionHasErrors('aksi');

        $pesan = session('errors')->first('aksi');

        $this->assertStringNotContainsString('validation.', $pesan);
        $this->assertStringContainsString('Setujui', $pesan);
    }

    /**
     * E5: nomor ganda diperingatkan, TIDAK diblokir. Buku agenda fisik adalah
     * otoritasnya; sistem yang menolak akan memaksa staf mencari jalan keluar
     * di luar sistem.
     */
    public function test_nomor_ganda_memperingatkan_lalu_dapat_dilanjutkan(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $this->buatSuratTugas(['no_surat' => '800.1.11.1/68-SMKN.1-AT/2026', 'created_at' => now()]);

        // Percobaan pertama: ditahan untuk dikonfirmasi, belum dikirim.
        $this->post('/antrean/surat_tugas/12/putuskan', ['aksi' => 'setujui', 'nomor' => '68'])
            ->assertSessionHas('konfirmasi');

        Http::assertNothingSent();
        $this->assertDatabaseCount('aktivitas', 0);

        // Percobaan kedua dengan konfirmasi: diteruskan.
        $this->post('/antrean/surat_tugas/12/putuskan', [
            'aksi' => 'setujui', 'nomor' => '68', 'abaikan_duplikat' => '1',
        ])->assertRedirect(route('antrean.index'));

        Http::assertSentCount(1);
        $this->assertDatabaseHas('aktivitas', ['aksi' => 'setujui', 'subjek_id' => 12]);
    }

    public function test_nomor_yang_belum_dipakai_langsung_diteruskan(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $this->buatSuratTugas(['no_surat' => '800.1.11.1/68-SMKN.1-AT/2026', 'created_at' => now()]);

        $this->post('/antrean/surat_tugas/12/putuskan', ['aksi' => 'setujui', 'nomor' => '69'])
            ->assertSessionMissing('konfirmasi')
            ->assertRedirect(route('antrean.index'));
    }

    /**
     * E1: aksi dibatalkan seluruhnya, tidak ada jejak, surat tetap di antrean.
     */
    public function test_n8n_gagal_membatalkan_seluruh_aksi(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->post('/antrean/surat_tugas/12/putuskan', ['aksi' => 'setujui', 'nomor' => '70'])
            ->assertSessionHasErrors('antrean')
            ->assertSessionMissing('sukses');

        $this->assertDatabaseCount('aktivitas', 0);
    }

    public function test_n8n_membalas_500_juga_membatalkan(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->post('/antrean/surat_tugas/12/putuskan', ['aksi' => 'setujui', 'nomor' => '71'])
            ->assertSessionHasErrors('antrean');

        $this->assertDatabaseCount('aktivitas', 0);
    }

    /**
     * E2: 401 berarti kredensial server, bukan pengguna. Berbeda dari
     * kunciUlang() lama, pengguna tidak dikeluarkan dari sesinya.
     */
    public function test_401_menampilkan_halaman_kredensial_tanpa_logout(): void
    {
        Http::fake(['*' => Http::response([], 401)]);

        $this->post('/antrean/surat_tugas/12/putuskan', ['aksi' => 'setujui', 'nomor' => '72'])
            ->assertStatus(503)
            ->assertSee('N8N_SECRET');

        $this->assertAuthenticatedAs($this->tas);
        $this->assertDatabaseCount('aktivitas', 0);
    }

    public function test_menandai_selesai(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $this->post('/antrean/surat_tugas/12/selesai')
            ->assertRedirect(route('antrean.index'))
            ->assertSessionHas('sukses');

        Http::assertSent(fn (Request $r) => $r->data() === ['id' => 12]);

        $this->assertDatabaseHas('aktivitas', ['aksi' => 'selesai', 'subjek_id' => 12]);
    }

    public function test_selesai_gagal_tidak_mencatat_apa_pun(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->post('/antrean/surat_tugas/12/selesai')->assertSessionHasErrors('antrean');

        $this->assertDatabaseCount('aktivitas', 0);
    }

    /** E15: URL kosong tidak dibuka sebagai tab kosong. */
    public function test_pdf_belum_tersedia_menampilkan_pesan(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->get('/antrean/surat_tugas/12/pdf')->assertSessionHasErrors('antrean');
    }

    public function test_pdf_tersedia_dialihkan(): void
    {
        Http::fake(['*' => Http::response(['url' => 'https://contoh.test/surat-12.pdf'])]);

        $this->get('/antrean/surat_tugas/12/pdf')
            ->assertRedirect('https://contoh.test/surat-12.pdf');
    }

    /**
     * §5.2: nama tabelnya "surat_keterangan", tetapi nilai yang dikenal n8n
     * adalah "keterangan_aktif". Route menolak nilai selain itu.
     */
    public function test_jenis_di_luar_kontrak_n8n_menghasilkan_404(): void
    {
        Http::fake();

        $this->post('/antrean/surat_keterangan/3/putuskan', ['aksi' => 'tolak'])
            ->assertNotFound();

        Http::assertNothingSent();
    }
}
