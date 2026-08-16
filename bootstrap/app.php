<?php

use App\Http\Middleware\PastikanAkunAktif;
use App\Http\Middleware\PastikanRole;
use App\Services\N8nUnauthorizedException;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => PastikanRole::class,
            'aktif' => PastikanAkunAktif::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 401 dari n8n berarti kredensial SERVER bermasalah, bukan pengguna
        // (E2). Pengguna tidak dikeluarkan dari sesinya — itu tidak akan
        // memperbaiki apa pun dan hanya menyembunyikan penyebab sebenarnya.
        $exceptions->render(function (N8nUnauthorizedException $e, Request $request) {
            return response()->view('galat.n8n-kredensial', ['pesan' => $e->getMessage()], 503);
        });
    })->create();
