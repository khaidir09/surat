<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Dukungan\MembuatDataSurat;
use Tests\TestCase;

class SuratPdfTest extends TestCase
{
    use MembuatDataSurat, RefreshDatabase;

    private function denganSupabase(): void
    {
        config([
            'supabase.url' => 'https://proyek.supabase.co',
            'supabase.service_key' => 'kunci-service-role',
            'supabase.bucket_surat' => 'surat',
            'supabase.ttl_url' => 300,
        ]);
    }

    private function tanpaSupabase(): void
    {
        config(['supabase.url' => null, 'supabase.service_key' => null]);
    }

    public function test_menandatangani_objek_lalu_mengalihkan(): void
    {
        $this->denganSupabase();

        Http::fake(['*/storage/v1/object/sign/*' => Http::response([
            'signedURL' => '/object/sign/surat/SK-3.pdf?token=abc',
        ])]);

        $id = $this->buatSuratKeterangan(['pdf_url' => 'SK-3.pdf']);

        $this->actingAs(User::factory()->create())
            ->get("/surat/keterangan_aktif/$id/pdf")
            ->assertRedirect('https://proyek.supabase.co/storage/v1/object/sign/surat/SK-3.pdf?token=abc');

        Http::assertSent(fn (Request $r) => $r->hasHeader('apikey', 'kunci-service-role')
            && $r->data() === ['expiresIn' => 300]);
    }

    /**
     * Cacat yang ditemukan 16 Agu 2026: kolom pdf_url berisi nama objek
     * ("SK-3.pdf"), bukan URL. Blade menautkannya apa adanya, sehingga
     * peramban meminta /riwayat/SK-3.pdf dan menerima 404.
     */
    public function test_halaman_riwayat_tidak_menautkan_pdf_url_mentah(): void
    {
        $this->denganSupabase();

        $id = $this->buatSuratKeterangan(['pdf_url' => 'SK-9.pdf', 'siswa_id' => $this->buatSiswa()]);

        $halaman = $this->actingAs(User::factory()->create())->get('/riwayat')->assertOk();

        $halaman->assertSee("/surat/keterangan_aktif/$id/pdf", escape: false);
        $halaman->assertDontSee('href="SK-9.pdf"', escape: false);
    }

    /** E15: berkas belum ada → pesan, bukan tautan yang membuka tab kosong. */
    public function test_pdf_belum_dibuat(): void
    {
        $this->denganSupabase();

        $id = $this->buatSuratTugas(['pdf_url' => null]);

        $this->actingAs(User::factory()->create())
            ->get("/surat/surat_tugas/$id/pdf")
            ->assertSessionHasErrors('pdf');
    }

    public function test_objek_hilang_di_penyimpanan(): void
    {
        $this->denganSupabase();

        Http::fake(['*' => Http::response(['error' => 'not_found'], 404)]);

        $id = $this->buatSuratTugas(['pdf_url' => 'ST-99.pdf']);

        $this->actingAs(User::factory()->create())
            ->get("/surat/surat_tugas/$id/pdf")
            ->assertSessionHasErrors('pdf');
    }

    /** Baris lama boleh saja menyimpan URL utuh alih-alih nama objek. */
    public function test_pdf_url_berupa_url_utuh_dialihkan_apa_adanya(): void
    {
        $this->denganSupabase();

        Http::fake();

        $id = $this->buatSuratTugas(['pdf_url' => 'https://contoh.test/lama.pdf']);

        $this->actingAs(User::factory()->create())
            ->get("/surat/surat_tugas/$id/pdf")
            ->assertRedirect('https://contoh.test/lama.pdf');

        // Tidak perlu ditandatangani ulang.
        Http::assertNothingSent();
    }

    /**
     * Selama SUPABASE_SERVICE_KEY belum diisi, surat tugas tetap dapat
     * diunduh lewat webhook n8n.
     */
    public function test_cadangan_lewat_n8n_saat_supabase_belum_dikonfigurasi(): void
    {
        $this->tanpaSupabase();

        Http::fake(['*surat-pdf*' => Http::response(['url' => 'https://n8n.test/ST-13.pdf'])]);

        $id = $this->buatSuratTugas(['pdf_url' => 'ST-13.pdf']);

        $this->actingAs(User::factory()->create())
            ->get("/surat/surat_tugas/$id/pdf")
            ->assertRedirect('https://n8n.test/ST-13.pdf');
    }

    /**
     * Webhook n8n membalas null untuk surat keterangan — di dashboard lama
     * tombol unduh memang tidak pernah ada pada kartu surat keterangan.
     * Pesannya harus menuntun admin, bukan sekadar "gagal".
     */
    public function test_tanpa_supabase_surat_keterangan_memberi_pesan_yang_menuntun(): void
    {
        $this->tanpaSupabase();

        Http::fake(['*surat-pdf*' => Http::response([])]);

        $id = $this->buatSuratKeterangan(['pdf_url' => 'SK-3.pdf']);

        $this->actingAs(User::factory()->create())
            ->get("/surat/keterangan_aktif/$id/pdf")
            ->assertSessionHasErrors('pdf');

        $this->assertStringContainsString('SUPABASE_SERVICE_KEY', session('errors')->first('pdf'));
    }

    public function test_kepsek_boleh_mengunduh(): void
    {
        $this->denganSupabase();

        Http::fake(['*' => Http::response(['signedURL' => '/object/sign/surat/ST-1.pdf?token=z'])]);

        $id = $this->buatSuratTugas(['pdf_url' => 'ST-1.pdf']);

        $this->actingAs(User::factory()->kepsek()->create())
            ->get("/surat/surat_tugas/$id/pdf")
            ->assertRedirect('https://proyek.supabase.co/storage/v1/object/sign/surat/ST-1.pdf?token=z');
    }

    public function test_surat_tidak_ada_menghasilkan_404(): void
    {
        $this->denganSupabase();

        Http::fake();

        $this->actingAs(User::factory()->create())
            ->get('/surat/surat_tugas/999999/pdf')
            ->assertNotFound();
    }

    /** Kunci service_role tidak boleh sampai ke browser. */
    public function test_kunci_service_role_tidak_muncul_di_halaman(): void
    {
        $this->denganSupabase();

        $this->buatSuratKeterangan(['pdf_url' => 'SK-3.pdf', 'siswa_id' => $this->buatSiswa()]);

        $this->actingAs(User::factory()->create())
            ->get('/riwayat')
            ->assertOk()
            ->assertDontSee('kunci-service-role');
    }
}
