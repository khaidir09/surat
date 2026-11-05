<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Satu-satunya pintu keluar menuju n8n (AC-6).
 *
 * n8n memegang seluruh logika surat: persetujuan, penomoran, pembuatan PDF,
 * dan notifikasi ke pemohon (K1). Bentuk payload di sini menyalin persis apa
 * yang dikirim dashboard lama dan TIDAK BOLEH diubah, termasuk tidak
 * menambahkan identitas pengguna — workflow n8n tidak disentuh (K6).
 * Identitas dicatat ke app.aktivitas saja.
 */
class N8nClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $secret,
        private readonly int $timeout,
        /** @var array<string, string> */
        private readonly array $endpoint,
    ) {
    }

    /**
     * Daftar surat yang menunggu diproses.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pending(): array
    {
        $data = $this->kirim('get', $this->endpoint['pending']);

        // n8n membungkus hasilnya sebagai {"items": [...]} — terverifikasi
        // terhadap respons sungguhan 16 Agu 2026. Bentuk array telanjang juga
        // diterima karena dashboard lama menangani keduanya (index.html:519),
        // dan tidak ada yang menjamin n8n selalu memakai bentuk yang sama.
        if (array_is_list($data)) {
            return $data;
        }

        return is_array($data['items'] ?? null) ? $data['items'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function putuskan(
        int $id,
        string $jenis,
        string $aksi,
        ?string $nomor = null,
        ?string $tanggal = null,
    ): array {
        return $this->kirim('post', $this->endpoint['keputusan'], [
            'id' => $id,
            'jenis' => $jenis,
            'aksi' => $aksi,
            'nomor' => $nomor,

            // Kuncinya selalu ikut, bernilai null bila dilewati — sama seperti
            // yang dikirim dashboard lama (index.html:651-675). n8n yang
            // memutuskan artinya: null berarti tanggal hari ini.
            'tanggal' => $tanggal,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function selesai(int $id): array
    {
        return $this->kirim('post', $this->endpoint['selesai'], ['id' => $id]);
    }

    /**
     * URL PDF yang dihasilkan n8n, atau null bila belum tersedia (E15).
     */
    public function pdf(int $id): ?string
    {
        $data = $this->kirim('get', $this->endpoint['pdf'], ['id' => $id]);

        // n8n kadang membungkus balasannya dalam array satu elemen. Dashboard
        // lama sudah menangani keduanya (index.html:681-683); kalau di sini
        // hanya salah satu yang ditangani, tombol unduh akan diam tanpa
        // penjelasan pada bentuk yang lain.
        if (array_is_list($data)) {
            $data = $data[0] ?? [];
        }

        return $data['url'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function statusKepsek(string $status, ?string $estimasi = null, ?string $keterangan = null): array
    {
        // Bentuk payload menyalin index.html:722-736 apa adanya, termasuk
        // memakai string kosong alih-alih null untuk isian yang dilewati.
        return $this->kirim('post', $this->endpoint['kepsek'], [
            'status' => $status,
            'estimasi' => (string) $estimasi,
            'keterangan' => (string) $keterangan,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     */
    private function kirim(string $metode, string $jalur, array $data = []): array
    {
        $url = rtrim($this->baseUrl, '/') . $jalur;

        try {
            $respons = $this->permintaan()->{$metode}($url, $data);
        } catch (ConnectionException $e) {
            // Termasuk timeout. Pesannya sengaja tidak menyebut URL atau
            // header apa pun agar kredensial tidak bocor ke halaman error.
            Log::warning('n8n tidak dapat dihubungi', ['jalur' => $jalur, 'sebab' => $e->getMessage()]);

            throw new N8nException('Layanan surat (n8n) tidak dapat dihubungi. Coba lagi beberapa saat.', 0, $e);
        }

        $this->pastikanBerhasil($respons, $jalur);

        return $respons->json() ?? [];
    }

    private function permintaan(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->acceptJson()
            ->withHeaders(array_filter(['x-dashboard-secret' => $this->secret]));
    }

    private function pastikanBerhasil(Response $respons, string $jalur): void
    {
        if ($respons->successful()) {
            return;
        }

        Log::warning('n8n membalas dengan status tak terduga', [
            'jalur' => $jalur,
            'status' => $respons->status(),
        ]);

        if ($respons->status() === 401) {
            throw new N8nUnauthorizedException(
                'Layanan surat menolak kredensial server. Hubungi admin untuk memeriksa N8N_SECRET.'
            );
        }

        throw new N8nException(
            "Layanan surat membalas dengan kesalahan (HTTP {$respons->status()}). Aksi dibatalkan."
        );
    }
}
