@extends('layouts.app')

@php $baru = ! $pengguna->exists; @endphp

@section('judul', $baru ? 'Tambah akun' : 'Ubah akun')

@section('isi')
    <form method="POST"
          action="{{ $baru ? route('pengguna.store') : route('pengguna.update', $pengguna) }}"
          class="max-w-lg space-y-4 rounded-xl border border-garis bg-permukaan p-6">
        @csrf
        @unless ($baru)
            @method('PUT')
        @endunless

        <div>
            <label for="nama" class="mb-1 block text-sm font-medium">Nama</label>
            <input id="nama" name="nama" type="text" required
                   value="{{ old('nama', $pengguna->nama) }}"
                   class="w-full rounded-lg border border-garis px-3 py-2 focus:border-pine focus:outline-none">
        </div>

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" required autocomplete="off"
                   value="{{ old('email', $pengguna->email) }}"
                   class="w-full rounded-lg border border-garis px-3 py-2 focus:border-pine focus:outline-none">
        </div>

        <div>
            <label for="role" class="mb-1 block text-sm font-medium">Role</label>
            <select id="role" name="role" required
                    class="w-full rounded-lg border border-garis px-3 py-2 focus:border-pine focus:outline-none">
                @foreach (App\Models\User::ROLE as $nilai => $label)
                    <option value="{{ $nilai }}" @selected(old('role', $pengguna->role) === $nilai)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-redup">
                Kepala sekolah hanya dapat melihat riwayat dan agenda, serta mengubah status kehadirannya.
            </p>
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">
                Kata sandi @unless ($baru) <span class="font-normal text-redup">(kosongkan bila tidak diubah)</span> @endunless
            </label>
            <input id="password" name="password" type="password" autocomplete="new-password"
                   @required($baru) minlength="8"
                   class="w-full rounded-lg border border-garis px-3 py-2 focus:border-pine focus:outline-none">
        </div>

        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium">Ulangi kata sandi</label>
            <input id="password_confirmation" name="password_confirmation" type="password"
                   autocomplete="new-password" @required($baru)
                   class="w-full rounded-lg border border-garis px-3 py-2 focus:border-pine focus:outline-none">
        </div>

        @unless ($baru)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="aktif" value="1" class="rounded border-garis"
                       @checked(old('aktif', $pengguna->aktif))>
                Akun aktif
            </label>
        @endunless

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-pine px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                Simpan
            </button>
            <a href="{{ route('pengguna.index') }}" class="text-sm text-redup hover:text-tinta">Batal</a>
        </div>
    </form>
@endsection
