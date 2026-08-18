<?php

namespace Tests\Unit;

use App\Services\N8nClient;
use App\Services\N8nException;
use App\Services\N8nUnauthorizedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Memakai Tests\TestCase, bukan PHPUnit\Framework\TestCase, karena Http::fake
 * membutuhkan container aplikasi. Tidak ada permintaan sungguhan yang keluar:
 * pengujian tidak boleh memicu pembuatan surat atau notifikasi ke nomor asli.
 */
class N8nClientTest extends TestCase
{
    private function klien(): N8nClient
    {
        return new N8nClient(
            baseUrl: 'https://contoh.test/webhook',
            secret: 'kode-rahasia',
            timeout: 15,
            endpoint: config('n8n.endpoints'),
        );
    }

    public function test_menempelkan_header_secret(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->klien()->pending();

        Http::assertSent(fn (Request $r) => $r->hasHeader('x-dashboard-secret', 'kode-rahasia'));
    }

    public function test_payload_keputusan_sesuai_kontrak(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $this->klien()->putuskan(12, 'keterangan_aktif', 'setujui', '67');

        // Bentuk ini mengikuti dashboard lama apa adanya (index.html:665-676),
        // termasuk kunci tanggal yang tetap dikirim bernilai null. Tidak ada
        // field identitas pengguna, meski itu akan berguna.
        Http::assertSent(fn (Request $r) => $r->url() === 'https://contoh.test/webhook/surat-keputusan'
            && $r->method() === 'POST'
            && $r->data() === [
                'id' => 12, 'jenis' => 'keterangan_aktif', 'aksi' => 'setujui',
                'nomor' => '67', 'tanggal' => null,
            ]);
    }

    public function test_tanggal_ikut_terkirim_apa_adanya(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $this->klien()->putuskan(12, 'surat_tugas', 'setujui', '67', '2026-03-04');

        // Diteruskan tanpa diformat ulang: n8n yang merakit
        // new Date(tgl + 'T00:00:00'), jadi Y-m-d harus sampai utuh.
        Http::assertSent(fn (Request $r) => $r->data()['tanggal'] === '2026-03-04');
    }

    public function test_payload_status_kepsek_sesuai_dashboard_lama(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->klien()->statusKepsek('Dinas Luar', null, null);

        // index.html mengirim string kosong untuk isian yang dilewati,
        // bukan null. Ditiru apa adanya agar n8n tidak menerima bentuk baru.
        Http::assertSent(fn (Request $r) => $r->data() === [
            'status' => 'Dinas Luar',
            'estimasi' => '',
            'keterangan' => '',
        ]);
    }

    public function test_401_menghasilkan_pengecualian_kredensial(): void
    {
        Http::fake(['*' => Http::response(['pesan' => 'nope'], 401)]);

        $this->expectException(N8nUnauthorizedException::class);

        $this->klien()->pending();
    }

    /**
     * Penting: 401 tidak boleh ikut tertangkap oleh penanganan kegagalan biasa,
     * karena penyebab dan penyelesaiannya berbeda sama sekali (E2).
     */
    public function test_pengecualian_kredensial_bukan_turunan_n8n_exception(): void
    {
        $this->assertFalse(
            is_subclass_of(N8nUnauthorizedException::class, N8nException::class),
            'N8nUnauthorizedException tidak boleh tertangkap oleh catch (N8nException).'
        );
    }

    public function test_status_selain_2xx_menghasilkan_n8n_exception(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->expectException(N8nException::class);

        $this->klien()->selesai(3);
    }

    public function test_gagal_koneksi_menghasilkan_n8n_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(N8nException::class);

        $this->klien()->pending();
    }

    /**
     * Pesan kesalahan tidak boleh membocorkan kredensial ke halaman error.
     */
    public function test_pesan_kesalahan_tidak_memuat_secret(): void
    {
        Http::fake(fn () => throw new ConnectionException('gagal ke https://contoh.test'));

        try {
            $this->klien()->pending();
            $this->fail('Seharusnya melempar N8nException.');
        } catch (N8nException $e) {
            $this->assertStringNotContainsString('kode-rahasia', $e->getMessage());
        }
    }

    /**
     * Bentuk sebenarnya, terverifikasi terhadap respons n8n 16 Agu 2026.
     */
    public function test_pending_membuka_bungkus_items(): void
    {
        Http::fake(['*' => Http::response(['items' => [
            ['id' => 3, 'jenis' => 'keterangan_aktif', 'status' => 'menunggu'],
        ]])]);

        $hasil = $this->klien()->pending();

        $this->assertCount(1, $hasil);
        $this->assertSame(3, $hasil[0]['id']);
    }

    /**
     * Dashboard lama menerima kedua bentuk (index.html:519) dan tidak ada
     * yang menjamin n8n selalu membalas dengan bentuk yang sama.
     */
    public function test_pending_juga_menerima_array_telanjang(): void
    {
        Http::fake(['*' => Http::response([
            ['id' => 7, 'jenis' => 'surat_tugas', 'status' => 'menunggu'],
        ])]);

        $this->assertSame(7, $this->klien()->pending()[0]['id']);
    }

    public function test_pending_kosong_menghasilkan_array_kosong(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->assertSame([], $this->klien()->pending());

        Http::fake(['*' => Http::response(['items' => []])]);

        $this->assertSame([], $this->klien()->pending());
    }

    public function test_pdf_mengembalikan_null_bila_url_belum_ada(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->assertNull($this->klien()->pdf(9));
    }

    public function test_pdf_mengembalikan_url(): void
    {
        Http::fake(['*' => Http::response(['url' => 'https://contoh.test/surat.pdf'])]);

        $this->assertSame('https://contoh.test/surat.pdf', $this->klien()->pdf(9));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'surat-pdf?id=9'));
    }
}
