<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi sebuah grup route pada role tertentu (§6 spesifikasi).
 *
 * Penegakan terjadi di sini, bukan dengan menyembunyikan tombol di Blade.
 * Menyembunyikan tombol hanya merapikan tampilan; menembak route langsung
 * lewat curl harus tetap ditolak.
 */
class PastikanRole
{
    public function handle(Request $request, Closure $next, string ...$role): Response
    {
        $user = $request->user();

        if (! $user || ! $user->punyaRole(...$role)) {
            abort(403, 'Halaman ini tidak tersedia untuk role Anda.');
        }

        return $next($request);
    }
}
