<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function form(): View
    {
        return view('auth.login');
    }

    public function masuk(Request $request): RedirectResponse
    {
        $kredensial = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Akun nonaktif ditolak di sini juga, bukan hanya oleh
        // PastikanAkunAktif, supaya sesi tidak sempat terbentuk.
        $kredensial['aktif'] = true;

        if (! Auth::attempt($kredensial, $request->boolean('ingat'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah, atau akun tidak aktif.',
            ]);
        }

        $request->session()->regenerate();

        // intended() mengembalikan pengguna ke halaman yang tadi dituju saat
        // sesinya kedaluwarsa (E16); rutaBeranda() menjadi tujuan default
        // karena beranda tiap role berbeda.
        return redirect()->intended($request->user()->rutaBeranda());
    }

    public function keluar(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
