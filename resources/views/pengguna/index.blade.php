@extends('layouts.app')

@section('judul', 'Kelola pengguna')

@section('isi')
    <div class="mb-4 flex items-center justify-between gap-4">
        <p class="text-sm text-redup">
            Hanya staf TAS dan kepala sekolah yang memiliki akun. Guru dan siswa
            mengajukan surat lewat chatbot, tanpa akun.
        </p>
        <a href="{{ route('pengguna.create') }}"
           class="shrink-0 rounded-lg bg-pine px-4 py-2 text-sm font-medium text-white hover:opacity-90">
            Tambah akun
        </a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-garis bg-permukaan">
        <table class="w-full text-sm">
            <thead class="border-b border-garis text-left text-redup">
                <tr>
                    <th class="px-4 py-3 font-medium">Nama</th>
                    <th class="px-4 py-3 font-medium">Email</th>
                    <th class="px-4 py-3 font-medium">Role</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pengguna as $baris)
                    <tr class="border-b border-garis last:border-0 {{ $baris->aktif ? '' : 'text-redup' }}">
                        <td class="px-4 py-3">
                            {{ $baris->nama }}
                            @if ($baris->is(auth()->user()))
                                <span class="ms-1 text-xs text-redup">(Anda)</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $baris->email }}</td>
                        <td class="px-4 py-3">{{ $baris->labelRole() }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs
                                {{ $baris->aktif ? 'bg-setuju/10 text-setuju' : 'bg-latar text-redup' }}">
                                {{ $baris->aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('pengguna.edit', $baris) }}" class="text-pine hover:underline">Ubah</a>

                            @if ($baris->aktif && ! $baris->is(auth()->user()))
                                <form method="POST" action="{{ route('pengguna.destroy', $baris) }}"
                                      class="ms-3 inline"
                                      onsubmit="return confirm('Nonaktifkan akun {{ $baris->nama }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-tolak hover:underline">Nonaktifkan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-redup">Belum ada akun.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $pengguna->links() }}</div>
@endsection
