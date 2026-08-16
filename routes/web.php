<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BelumTersediaController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\RiwayatController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\StatusKepsekController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Kerangka route mengikuti §7 docs/specs/dashboard-laravel-surat.md.
|
| Yang sudah terisi pada langkah 2: autentikasi dan kelola pengguna. Sisanya
| diarahkan ke BelumTersediaController — alamat dan middleware role-nya sudah
| final, hanya isinya yang menyusul.
|------------------------------------------------------------------------------
*/

$belum = BelumTersediaController::class;

// Nilai {jenis} mengikuti yang diterima webhook n8n, bukan nama tabel:
// surat keterangan dikirim sebagai "keterangan_aktif" (lihat config/n8n.php).
$jenis = array_values(config('n8n.jenis'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'form'])->name('login');

    // Dibatasi supaya kode akses tidak bisa ditebak dengan percobaan beruntun.
    Route::post('/login', [LoginController::class, 'masuk'])->middleware('throttle:5,1');
});

Route::post('/logout', [LoginController::class, 'keluar'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'aktif'])->group(function () use ($belum, $jenis) {

    Route::get('/', fn () => redirect(request()->user()->rutaBeranda()))->name('beranda');

    // --- Antrean persetujuan (langkah 6) --------------------------------------
    Route::middleware('role:tas')->group(function () use ($belum, $jenis) {
        Route::get('/antrean', $belum)->name('antrean.index');

        Route::post('/antrean/{jenis}/{id}/putuskan', $belum)
            ->whereIn('jenis', $jenis)->whereNumber('id')->name('antrean.putuskan');

        Route::post('/antrean/{jenis}/{id}/selesai', $belum)
            ->whereIn('jenis', $jenis)->whereNumber('id')->name('antrean.selesai');
    });

    // Kepsek boleh mengunduh PDF, hanya tidak boleh memutuskan (§6).
    Route::get('/antrean/{jenis}/{id}/pdf', $belum)
        ->whereIn('jenis', $jenis)->whereNumber('id')->name('antrean.pdf');

    // --- Riwayat surat (langkah 3, selesai) -----------------------------------
    Route::get('/riwayat', [RiwayatController::class, 'index'])->name('riwayat.index');
    Route::get('/riwayat/{jenis}/{id}', [RiwayatController::class, 'show'])
        ->whereIn('jenis', $jenis)->whereNumber('id')->name('riwayat.show');

    // --- Data master (langkah 4, selesai) -------------------------------------
    // Kepsek boleh melihat agenda, tidak boleh mengubahnya (§6).
    Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');

    Route::middleware('role:tas')->group(function () {
        Route::resource('agenda', AgendaController::class)->except(['index', 'show']);
        Route::resource('pegawai', PegawaiController::class)->except('show')->parameters(['pegawai' => 'pegawai']);
        Route::resource('siswa', SiswaController::class)->except('show')->parameters(['siswa' => 'siswa']);
    });

    // --- Status kepala sekolah (langkah 5, selesai) ---------------------------
    // Kedua role boleh memperbarui (§6): kepsek mengabarkan sendiri, TAS
    // memperbarui atas nama beliau saat sedang tidak memegang perangkat.
    Route::get('/status-kepsek', [StatusKepsekController::class, 'form'])->name('status-kepsek.form');
    Route::post('/status-kepsek', [StatusKepsekController::class, 'store'])->name('status-kepsek.store');

    // --- Kelola pengguna (langkah 2, selesai) ---------------------------------
    Route::middleware('role:tas')->group(function () {
        Route::get('/pengguna', [PenggunaController::class, 'index'])->name('pengguna.index');
        Route::get('/pengguna/create', [PenggunaController::class, 'create'])->name('pengguna.create');
        Route::post('/pengguna', [PenggunaController::class, 'store'])->name('pengguna.store');
        Route::get('/pengguna/{pengguna}/edit', [PenggunaController::class, 'edit'])->name('pengguna.edit');
        Route::put('/pengguna/{pengguna}', [PenggunaController::class, 'update'])->name('pengguna.update');
        Route::delete('/pengguna/{pengguna}', [PenggunaController::class, 'destroy'])->name('pengguna.destroy');
    });
});
