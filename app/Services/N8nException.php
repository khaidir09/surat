<?php

namespace App\Services;

use RuntimeException;

/**
 * n8n tidak dapat dihubungi, atau membalas dengan status di luar 2xx.
 *
 * Aksi yang sedang berjalan harus dibatalkan seluruhnya (E1): tidak ada baris
 * app.aktivitas yang ditulis, dan surat tetap berada di tempatnya semula.
 * Menampilkan keberhasilan palsu jauh lebih berbahaya daripada menampilkan
 * kegagalan — staf akan mengira surat sudah diproses padahal pemohon tidak
 * pernah menerima notifikasi apa pun.
 */
class N8nException extends RuntimeException
{
}
