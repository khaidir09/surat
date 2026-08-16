<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Membuat URL bertanda tangan untuk berkas di bucket privat Supabase Storage.
 *
 * n8n menyimpan PDF surat di sana dan hanya mencatat nama objeknya di kolom
 * pdf_url. Webhook /surat-pdf memang dapat menandatanganinya, tetapi hanya
 * untuk surat tugas — surat keterangan dibalas null. Karena workflow n8n
 * tidak diubah (K6), Laravel menandatanganinya sendiri agar kedua jenis surat
 * dapat diunduh.
 */
class SupabaseStorage
{
    public function __construct(
        private readonly ?string $url,
        private readonly ?string $serviceKey,
        private readonly string $bucket,
        private readonly int $ttl,
    ) {
    }

    public function terkonfigurasi(): bool
    {
        return filled($this->url) && filled($this->serviceKey);
    }

    /**
     * @throws SupabaseException
     */
    public function urlBertandaTangan(string $objek, ?int $ttl = null): string
    {
        if (! $this->terkonfigurasi()) {
            throw new SupabaseException(
                'Penyimpanan berkas belum dikonfigurasi. Admin perlu mengisi SUPABASE_URL dan SUPABASE_SERVICE_KEY.'
            );
        }

        $dasar = rtrim($this->url, '/') . '/storage/v1';
        $objek = ltrim($objek, '/');

        try {
            $respons = Http::timeout(15)
                ->withHeaders([
                    'apikey' => $this->serviceKey,
                    'Authorization' => 'Bearer ' . $this->serviceKey,
                ])
                ->post("$dasar/object/sign/{$this->bucket}/$objek", [
                    'expiresIn' => $ttl ?? $this->ttl,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Supabase Storage tidak dapat dihubungi', ['objek' => $objek]);

            throw new SupabaseException('Penyimpanan berkas tidak dapat dihubungi. Coba lagi beberapa saat.', 0, $e);
        }

        if (! $respons->successful()) {
            Log::warning('Supabase Storage menolak permintaan tanda tangan', [
                'objek' => $objek,
                'status' => $respons->status(),
                'kode' => $respons->json('code'),
            ]);

            throw new SupabaseException($this->jelaskanKegagalan($respons->status(), $respons->json() ?: [], $objek));
        }

        // Balasannya berbentuk {"signedURL": "/object/sign/surat/SK-3.pdf?token=..."}
        // — jalur relatif terhadap /storage/v1, bukan URL utuh.
        $jalur = $respons->json('signedURL');

        if (blank($jalur)) {
            throw new SupabaseException("Penyimpanan berkas tidak mengembalikan tautan untuk $objek.");
        }

        return $dasar . '/' . ltrim($jalur, '/');
    }

    /**
     * Menerjemahkan kegagalan menjadi kalimat yang menunjuk perbaikannya.
     *
     * Supabase membungkus 404 di dalam balasan HTTP 400
     * ({"statusCode":"404","code":"NoSuchKey"}), sehingga status HTTP saja
     * menyesatkan. Lebih menyesatkan lagi: kunci yang tidak berwenang membaca
     * bucket menerima jawaban "Object not found" yang sama persis dengan
     * berkas yang memang tidak ada.
     *
     * @param  array<string, mixed>  $isi
     */
    private function jelaskanKegagalan(int $status, array $isi, string $objek): string
    {
        $tidakDitemukan = ($isi['statusCode'] ?? null) === '404'
            || ($isi['code'] ?? null) === 'NoSuchKey'
            || $status === 404;

        if ($tidakDitemukan) {
            return "Berkas $objek tidak ditemukan di penyimpanan." . $this->peringatanKunci();
        }

        if (in_array($status, [401, 403], true)) {
            return 'Penyimpanan berkas menolak kredensial server.' . $this->peringatanKunci();
        }

        return "Penyimpanan berkas membalas kesalahan (HTTP $status)." . $this->peringatanKunci();
    }

    /**
     * Kunci Supabase gaya lama berupa JWT yang menyebut perannya sendiri.
     * Diperiksa hanya ketika sesuatu sudah gagal — bukan pada setiap
     * permintaan — supaya jalur normal tidak menanggung biayanya.
     */
    private function peringatanKunci(): string
    {
        $bagian = explode('.', (string) $this->serviceKey);

        if (count($bagian) !== 3) {
            return '';
        }

        $muatan = json_decode(base64_decode(strtr($bagian[1], '-_', '+/')) ?: '{}', true);
        $peran = $muatan['role'] ?? null;

        if ($peran && $peran !== 'service_role') {
            return " Kunci yang terpasang berperan \"$peran\", bukan service_role;"
                . ' kunci itu tidak berwenang membaca bucket privat, sehingga berkas yang ada pun'
                . ' dilaporkan tidak ditemukan. Admin perlu mengganti SUPABASE_SERVICE_KEY.';
        }

        return '';
    }
}
