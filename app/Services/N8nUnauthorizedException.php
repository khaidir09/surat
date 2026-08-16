<?php

namespace App\Services;

use RuntimeException;

/**
 * n8n menolak kredensial server (401).
 *
 * Perbedaan penting dari dashboard lama: kode akses dulu diketik pengguna dan
 * hidup di browser, sehingga 401 berarti "Anda salah kode" dan kunciUlang()
 * melempar pengguna kembali ke gerbang (index.html:700).
 *
 * Sekarang kode itu milik server dan tidak pernah sampai ke browser (K5),
 * sehingga 401 berarti konfigurasi server yang bermasalah. Mengeluarkan
 * pengguna dari sesinya tidak akan memperbaiki apa pun, hanya membuat mereka
 * mencoba masuk berulang-ulang tanpa pernah tahu penyebabnya (E2).
 *
 * Sengaja TIDAK mewarisi N8nException. Controller menangkap N8nException
 * untuk menampilkan pesan gagal di dalam form; kalau 401 ikut tertangkap di
 * sana, ia akan tampil sebagai kesalahan pengisian biasa dan admin tidak
 * pernah tahu bahwa yang salah adalah N8N_SECRET di server.
 */
class N8nUnauthorizedException extends RuntimeException
{
}
