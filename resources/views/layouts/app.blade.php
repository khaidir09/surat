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
    // Elemen keempat adalah nama ikon di resources/views/components/ikon.blade.php.
    $menu = array_filter([
        ['antrean.index', 'Antrean', $pengguna->adalahTas(), 'antrean'],
        ['riwayat.index', 'Riwayat', true, 'riwayat'],
        ['pegawai.index', 'Pegawai', $pengguna->adalahTas(), 'pegawai'],
        ['siswa.index', 'Siswa', $pengguna->adalahTas(), 'siswa'],
        ['status-kepsek.form', 'Status Kepsek', true, 'kepsek'],
        ['agenda.index', 'Agenda', true, 'agenda'],
        ['pengguna.index', 'Pengguna', $pengguna->adalahTas(), 'pengguna'],
    ], fn ($item) => $item[2]);

    // APP_NAME berbentuk "SAPA (Sistem Asisten Pelayanan Administrasi)" —
    // terlalu panjang untuk satu baris. Kepanjangannya dipecah jadi subjudul
    // supaya sidebar tidak perlu melebar. Nama tanpa tanda kurung tetap
    // tampil utuh sebagai judul.
    $namaAplikasi = config('app.name');
    $judulAplikasi = trim(Str::before($namaAplikasi, '('));
    $subJudulAplikasi = Str::contains($namaAplikasi, '(')
        ? trim(Str::after($namaAplikasi, '('), " \t\n\r\0\x0B)")
        : null;
@endphp

{{-- Pemicu sidebar di layar sempit. Checkbox dipakai supaya laci ini bekerja
     tanpa JavaScript sama sekali; di layar lg ke atas sidebar selalu terbuka
     dan checkbox tidak berpengaruh. --}}
<input type="checkbox" id="buka-sidebar" class="peer sr-only">

<label for="buka-sidebar" aria-hidden="true"
       class="fixed inset-0 z-20 hidden bg-tinta/40 peer-checked:block lg:peer-checked:hidden"></label>

<aside class="fixed inset-y-0 left-0 z-30 flex w-64 -translate-x-full flex-col border-r border-garis bg-permukaan transition-transform peer-checked:translate-x-0 lg:translate-x-0">
    <a href="{{ route('beranda') }}" class="flex items-center gap-3 border-b border-garis px-4 py-4">
        <img src="{{ asset('images/logo-smkn1amuntai.png') }}" alt="" class="h-10 w-auto shrink-0">
        <span class="min-w-0">
            <span class="block truncate font-semibold text-pine">{{ $judulAplikasi }}</span>
            @if ($subJudulAplikasi)
                <span class="block text-xs leading-snug text-redup">{{ $subJudulAplikasi }}</span>
            @endif
        </span>
    </a>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm">
        @foreach ($menu as [$rute, $label, $tampil, $ikon])
            <a href="{{ route($rute) }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs(Str::before($rute, '.') . '.*') ? 'bg-pine-lembut font-medium text-pine' : 'text-redup hover:bg-latar hover:text-tinta' }}">
                <x-ikon :nama="$ikon" />
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <form method="POST" action="{{ route('logout') }}" class="border-t border-garis px-4 py-4 text-sm">
        @csrf
        <p class="mb-3 min-w-0">
            <span class="block truncate font-medium">{{ $pengguna->nama }}</span>
            <span class="block text-xs text-redup">{{ $pengguna->labelRole() }}</span>
        </p>
        <button type="submit" class="w-full rounded-lg border border-garis px-3 py-2 hover:bg-latar">Keluar</button>
    </form>
</aside>

<div class="lg:pl-64">
    <header class="sticky top-0 z-10 flex items-center gap-3 border-b border-garis bg-permukaan px-4 py-3 lg:hidden">
        <label for="buka-sidebar" class="rounded-lg border border-garis p-2 hover:bg-latar" aria-label="Buka menu">
            <x-ikon nama="menu" />
        </label>
        <span class="truncate font-semibold text-pine">{{ $judulAplikasi }}</span>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6 lg:px-8">
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
</div>

</body>
</html>
