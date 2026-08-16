@extends('layouts.app')

@section('judul', 'Data siswa')

@section('isi')
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div class="grow sm:grow-0">
            <label for="cari" class="mb-1 block text-xs font-medium text-redup">Cari</label>
            <input id="cari" name="cari" type="search" value="{{ request('cari') }}"
                   placeholder="Nama, NIS/NISN, orang tua"
                   class="w-full rounded-lg border border-garis px-3 py-2 text-sm focus:border-pine focus:outline-none sm:w-64">
        </div>

        <div>
            <label for="kelas" class="mb-1 block text-xs font-medium text-redup">Kelas</label>
            <select id="kelas" name="kelas"
                    class="rounded-lg border border-garis px-3 py-2 text-sm focus:border-pine focus:outline-none">
                <option value="">Semua</option>
                @foreach ($daftarKelas as $pilihan)
                    <option value="{{ $pilihan }}" @selected(request('kelas') === $pilihan)>{{ $pilihan }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="jurusan" class="mb-1 block text-xs font-medium text-redup">Jurusan</label>
            <select id="jurusan" name="jurusan"
                    class="rounded-lg border border-garis px-3 py-2 text-sm focus:border-pine focus:outline-none">
                <option value="">Semua</option>
                @foreach ($daftarJurusan as $pilihan)
                    <option value="{{ $pilihan }}" @selected(request('jurusan') === $pilihan)>{{ $pilihan }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status" class="mb-1 block text-xs font-medium text-redup">Status</label>
            <select id="status" name="status"
                    class="rounded-lg border border-garis px-3 py-2 text-sm focus:border-pine focus:outline-none">
                <option value="">Semua</option>
                <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option>
            </select>
        </div>

        <button type="submit" class="rounded-lg bg-pine px-4 py-2 text-sm font-medium text-white hover:opacity-90">
            Saring
        </button>

        <a href="{{ route('siswa.create') }}"
           class="ms-auto rounded-lg border border-pine px-4 py-2 text-sm font-medium text-pine hover:bg-pine-lembut">
            Tambah siswa
        </a>
    </form>

    <p class="mb-3 text-sm text-redup">{{ $siswa->total() }} siswa.</p>

    <div class="overflow-x-auto rounded-xl border border-garis bg-permukaan">
        <table class="w-full text-sm">
            <thead class="border-b border-garis text-left text-redup">
                <tr>
                    <th class="px-4 py-3 font-medium">Nama</th>
                    <th class="px-4 py-3 font-medium">NIS / NISN</th>
                    <th class="px-4 py-3 font-medium">Kelas / Jurusan</th>
                    <th class="px-4 py-3 font-medium">Orang tua</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($siswa as $anak)
                    <tr class="border-b border-garis last:border-0">
                        <td class="px-4 py-3">{{ $anak->nama }}</td>
                        <td class="px-4 py-3">{{ $anak->nis_nisn ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $anak->kelasJurusan() }}</td>
                        <td class="px-4 py-3">{{ $anak->nama_orang_tua ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $anak->aktif ? 'bg-setuju/10 text-setuju' : 'bg-latar text-redup' }}">
                                {{ $anak->aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('siswa.edit', $anak) }}" class="text-pine hover:underline">Ubah</a>

                            <form method="POST" action="{{ route('siswa.destroy', $anak) }}" class="ms-3 inline"
                                  onsubmit="return confirm('Hapus {{ $anak->nama }}? Riwayat surat tidak terpengaruh.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-tolak hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-redup">Tidak ada siswa yang cocok.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $siswa->links() }}</div>
@endsection
