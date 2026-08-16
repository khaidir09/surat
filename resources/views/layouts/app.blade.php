<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul', 'Dashboard') — {{ config('app.name') }}</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-latar text-tinta antialiased">

@php
    $pengguna = auth()->user();

    // Menu disaring per role hanya untuk kerapian tampilan. Penegakan yang
    // sebenarnya ada di middleware role pada routes/web.php — menghapus item
    // dari daftar ini tidak menutup aksesnya.
    $menu = array_filter([
        ['antrean.index', 'Antrean', $pengguna->adalahTas()],
        ['riwayat.index', 'Riwayat', true],
        ['agenda.index', 'Agenda', true],
        ['pegawai.index', 'Pegawai', $pengguna->adalahTas()],
        ['siswa.index', 'Siswa', $pengguna->adalahTas()],
        ['status-kepsek.form', 'Status Kepsek', true],
        ['pengguna.index', 'Pengguna', $pengguna->adalahTas()],
    ], fn ($item) => $item[2]);
@endphp

<header class="border-b border-garis bg-permukaan">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3">
        <a href="{{ route('beranda') }}" class="font-semibold text-pine">
            {{ config('app.name') }}
        </a>

        <nav class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
            @foreach ($menu as [$rute, $label, $tampil])
                <a href="{{ route($rute) }}"
                   class="rounded px-2 py-1 {{ request()->routeIs(Str::before($rute, '.') . '.*') ? 'bg-pine-lembut font-medium text-pine' : 'text-redup hover:text-tinta' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="ms-auto flex items-center gap-3 text-sm">
            @csrf
            <span class="text-redup">
                {{ $pengguna->nama }} <span class="text-xs">({{ $pengguna->labelRole() }})</span>
            </span>
            <button type="submit" class="rounded border border-garis px-3 py-1 hover:bg-latar">Keluar</button>
        </form>
    </div>
</header>

<main class="mx-auto max-w-6xl px-4 py-6">
    <h1 class="mb-5 text-xl font-semibold">@yield('judul')</h1>

    @if (session('sukses'))
        <p class="mb-4 rounded-lg border border-setuju/30 bg-setuju/10 px-4 py-3 text-sm text-setuju">
            {{ session('sukses') }}
        </p>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-tolak/30 bg-tolak/10 px-4 py-3 text-sm text-tolak">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('isi')
</main>

</body>
</html>
