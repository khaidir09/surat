@extends('layouts.app')

@section('judul', 'Agenda kegiatan')

@section('isi')
    @php $bolehUbah = auth()->user()->adalahTas(); @endphp

    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
        <div>
            <label for="bulan" class="mb-1 block text-xs font-medium text-redup">Bulan</label>
            <input id="bulan" name="bulan" type="month" value="{{ $bulan->format('Y-m') }}"
                   class="rounded-lg border border-garis px-3 py-2 text-sm focus:border-pine focus:outline-none">
        </div>

        <div class="grow sm:grow-0">
            <label for="cari" class="mb-1 block text-xs font-medium text-redup">Cari</label>
            <input id="cari" name="cari" type="search" value="{{ $cari }}" placeholder="Judul, kategori, tempat"
                   class="w-full rounded-lg border border-garis px-3 py-2 text-sm focus:border-pine focus:outline-none sm:w-56">
        </div>

        <button type="submit" class="rounded-lg bg-pine px-4 py-2 text-sm font-medium text-white hover:opacity-90">
            Tampilkan
        </button>

        <div class="ms-auto flex items-center gap-2 text-sm">
            <a href="{{ route('agenda.index', ['bulan' => $bulan->copy()->subMonth()->format('Y-m'), 'cari' => $cari]) }}"
               class="rounded border border-garis px-3 py-2 hover:bg-latar">&larr; {{ $bulan->copy()->subMonth()->translatedFormat('F') }}</a>
            <a href="{{ route('agenda.index', ['bulan' => $bulan->copy()->addMonth()->format('Y-m'), 'cari' => $cari]) }}"
               class="rounded border border-garis px-3 py-2 hover:bg-latar">{{ $bulan->copy()->addMonth()->translatedFormat('F') }} &rarr;</a>

            @if ($bolehUbah)
                <a href="{{ route('agenda.create') }}"
                   class="rounded-lg border border-pine px-4 py-2 font-medium text-pine hover:bg-pine-lembut">
                    Tambah kegiatan
                </a>
            @endif
        </div>
    </form>

    <h2 class="mb-3 text-lg font-medium">{{ $bulan->translatedFormat('F Y') }}</h2>

    <div class="mb-8 overflow-x-auto rounded-xl border border-garis bg-permukaan">
        <table class="w-full table-fixed text-sm">
            <thead class="border-b border-garis text-redup">
                <tr>
                    @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $hari)
                        <th class="px-2 py-2 font-medium">{{ $hari }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($kalender as $minggu)
                    <tr>
                        @foreach ($minggu as $petak)
                            <td class="h-24 border border-garis align-top {{ $petak['bulanIni'] ? '' : 'bg-latar/60' }}">
                                <div class="px-2 py-1">
                                    <span class="text-xs {{ $petak['bulanIni'] ? 'text-redup' : 'text-redup/50' }}">
                                        {{ $petak['tanggal']->day }}
                                    </span>

                                    @foreach ($petak['kegiatan'] as $acara)
                                        <div class="mt-1 truncate rounded bg-pine-lembut px-1.5 py-0.5 text-xs text-pine"
                                             title="{{ $acara->judul }}">
                                            {{ $acara->judul }}
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2 class="mb-3 text-lg font-medium">Daftar kegiatan</h2>

    <div class="overflow-x-auto rounded-xl border border-garis bg-permukaan">
        <table class="w-full text-sm">
            <thead class="border-b border-garis text-left text-redup">
                <tr>
                    <th class="px-4 py-3 font-medium">Tanggal</th>
                    <th class="px-4 py-3 font-medium">Judul</th>
                    <th class="px-4 py-3 font-medium">Kategori</th>
                    <th class="px-4 py-3 font-medium">Tempat</th>
                    @if ($bolehUbah)
                        <th class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($kegiatan as $acara)
                    <tr class="border-b border-garis last:border-0">
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ $acara->tanggal_mulai->translatedFormat('j M') }}
                            @if (! $acara->tanggal_mulai->isSameDay($acara->tanggal_selesai))
                                &ndash; {{ $acara->tanggal_selesai->translatedFormat('j M') }}
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            {{ $acara->judul }}
                            @if ($acara->keterangan)
                                <p class="text-xs text-redup">{{ $acara->keterangan }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $acara->kategori ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $acara->tempat ?: '—' }}</td>

                        @if ($bolehUbah)
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('agenda.edit', $acara) }}" class="text-pine hover:underline">Ubah</a>

                                <form method="POST" action="{{ route('agenda.destroy', $acara) }}" class="ms-3 inline"
                                      onsubmit="return confirm('Hapus kegiatan {{ $acara->judul }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-tolak hover:underline">Hapus</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $bolehUbah ? 5 : 4 }}" class="px-4 py-6 text-center text-redup">
                            Tidak ada kegiatan pada bulan ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
