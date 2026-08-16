@extends('layouts.app')

@section('judul', 'Riwayat surat')

@section('isi')
    <form method="GET" action="{{ route('riwayat.index') }}"
          class="mb-5 grid gap-3 rounded-xl border border-garis bg-permukaan p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label for="cari" class="mb-1 block text-xs font-medium text-redup">Cari</label>
            <input id="cari" name="cari" type="search" value="{{ $saring['cari'] ?? '' }}"
                   placeholder="Nomor, kegiatan, nama siswa"
                   class="w-full rounded-lg border border-garis px-3 py-2 text-sm focus:border-pine focus:outline-none">
        </div>

        <div>
            <label for="jenis" class="mb-1 block text-xs font-medium text-redup">Jenis</label>
            <select id="jenis" name="jenis"
                    class="w-full rounded-lg border border-garis px-3 py-2 text-sm focus:border-pine focus:outline-none">
                <option value="">Semua</option>
                <option value="surat_tugas" @selected(($saring['jenis'] ?? '') === 'surat_tugas')>Surat tugas</option>
                <option value="keterangan_aktif" @selected(($saring['jenis'] ?? '') === 'keterangan_aktif')>Surat keterangan</option>
            </select>
        </div>

        <div>
            <label for="status" class="mb-1 block text-xs font-medium text-redup">Status</label>
            <select id="status" name="status"
                    class="w-full rounded-lg border border-garis px-3 py-2 text-sm focus:border-pine focus:outline-none">
                <option value="">Semua</option>
                @foreach ($statusTersedia as $nilai)
                    <option value="{{ $nilai }}" @selected(($saring['status'] ?? '') === $nilai)>{{ $nilai }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="dari" class="mb-1 block text-xs font-medium text-redup">Dari tanggal</label>
            <input id="dari" name="dari" type="date" value="{{ $saring['dari'] ?? '' }}"
                   class="w-full rounded-lg border border-garis px-3 py-2 text-sm focus:border-pine focus:outline-none">
        </div>

        <div class="flex items-end gap-2">
            <div class="grow">
                <label for="sampai" class="mb-1 block text-xs font-medium text-redup">Sampai</label>
                <input id="sampai" name="sampai" type="date" value="{{ $saring['sampai'] ?? '' }}"
                       class="w-full rounded-lg border border-garis px-3 py-2 text-sm focus:border-pine focus:outline-none">
            </div>
            <button type="submit" class="rounded-lg bg-pine px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                Saring
            </button>
        </div>

        @if (array_filter($saring))
            <p class="text-xs text-redup sm:col-span-2 lg:col-span-5">
                <a href="{{ route('riwayat.index') }}" class="text-pine hover:underline">Bersihkan semua filter</a>
            </p>
        @endif
    </form>

    <p class="mb-3 text-sm text-redup">{{ $surat->total() }} surat ditemukan.</p>

    <div class="overflow-x-auto rounded-xl border border-garis bg-permukaan">
        <table class="w-full text-sm">
            <thead class="border-b border-garis text-left text-redup">
                <tr>
                    <th class="px-4 py-3 font-medium">Nomor</th>
                    <th class="px-4 py-3 font-medium">Jenis</th>
                    <th class="px-4 py-3 font-medium">Judul / Nama</th>
                    <th class="px-4 py-3 font-medium">Tanggal</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">PDF</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($surat as $baris)
                    @php $tugas = $baris->jenis === 'surat_tugas'; @endphp
                    <tr class="border-b border-garis last:border-0">
                        <td class="px-4 py-3">
                            <a href="{{ route('riwayat.show', [$baris->jenis, $baris->id]) }}"
                               class="text-pine hover:underline">
                                {{-- Surat lama bisa belum bernomor (E10). --}}
                                {{ filled($baris->no_surat) ? $baris->no_surat : 'belum bernomor' }}
                            </a>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $tugas ? 'bg-pine-lembut text-pine' : 'bg-ket/10 text-ket' }}">
                                {{ $tugas ? 'Surat tugas' : 'Keterangan' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ filled($baris->judul) ? $baris->judul : '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ $baris->tanggal ? \Illuminate\Support\Carbon::parse($baris->tanggal)->translatedFormat('j M Y') : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            {{-- Label ditulis apa adanya: status yang belum dikenal
                                 tetap harus terlihat, bukan disembunyikan (E3). --}}
                            <span class="rounded-full bg-latar px-2 py-0.5 text-xs">{{ $baris->status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if (filled($baris->pdf_url))
                                <a href="{{ $baris->pdf_url }}" target="_blank" rel="noopener"
                                   class="text-pine hover:underline">Unduh</a>
                            @else
                                {{-- Tautan kosong tidak dijadikan tombol yang membuka
                                     tab kosong (E15). --}}
                                <span class="text-redup" title="Berkas PDF belum tersedia">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-redup">
                            Tidak ada surat yang cocok dengan filter ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $surat->links() }}</div>
@endsection
