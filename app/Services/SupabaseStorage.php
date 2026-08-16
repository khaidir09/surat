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

        if ($respons->status() === 404) {
            // Dibedakan dari kegagalan lain: berkasnya memang belum ada,
            // dan tidak ada gunanya menyuruh staf mencoba lagi.
            throw new SupabaseException("Berkas $objek tidak ditemukan di penyimpanan.");
        }

        if (! $respons->successful()) {
            Log::warning('Supabase Storage menolak permintaan tanda tangan', [
                'objek' => $objek,
                'status' => $respons->status(),
            ]);

            throw new SupabaseException("Penyimpanan berkas membalas kesalahan (HTTP {$respons->status()}).");
        }

        // Balasannya berbentuk {"signedURL": "/object/sign/surat/SK-3.pdf?token=..."}
        // — jalur relatif terhadap /storage/v1, bukan URL utuh.
        $jalur = $respons->json('signedURL');

        if (blank($jalur)) {
            throw new SupabaseException("Penyimpanan berkas tidak mengembalikan tautan untuk $objek.");
        }

        return $dasar . '/' . ltrim($jalur, '/');
    }
}
