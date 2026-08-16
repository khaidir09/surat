<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menonaktifkan akun tidak boleh hanya berlaku saat login berikutnya.
 *
 * Staf yang aksesnya dicabut sementara sesinya masih hidup akan tetap bisa
 * menyetujui surat sampai sesi kedaluwarsa (120 menit). Middleware ini
 * memutusnya pada permintaan berikutnya.
 */
class PastikanAkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->aktif) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Akun Anda dinonaktifkan. Hubungi staf TAS.']);
        }

        return $next($request);
    }
}
