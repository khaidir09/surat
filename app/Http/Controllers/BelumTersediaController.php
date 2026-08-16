<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Penampung sementara untuk route yang kerangkanya sudah berdiri di §7
 * spesifikasi tetapi isinya baru dikerjakan pada langkah berikutnya.
 *
 * Route-nya sengaja didaftarkan sekarang, bukan nanti, karena matriks role
 * §6 hanya bisa diuji kalau alamatnya benar-benar ada — route yang belum
 * terdaftar mengembalikan 404, sehingga tes tidak bisa membedakan
 * "ditolak dengan benar" dari "belum ditulis".
 */
class BelumTersediaController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('belum-tersedia', [
            'jalur' => '/' . ltrim($request->path(), '/'),
        ]);
    }
}
