<?php

namespace App\Services;

use RuntimeException;

/**
 * Berkas tidak dapat diambil dari Supabase Storage.
 *
 * Selalu ditampilkan sebagai pesan kepada pengguna, tidak pernah dibiarkan
 * menjadi tautan yang membuka tab kosong (E15).
 */
class SupabaseException extends RuntimeException
{
}
