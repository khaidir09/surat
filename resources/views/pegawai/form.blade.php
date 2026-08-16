@extends('layouts.app')

@php $baru = ! $pegawai->exists; @endphp

@section('judul', $baru ? 'Tambah pegawai' : 'Ubah pegawai')

@section('isi')
    <form method="POST"
          action="{{ $baru ? route('pegawai.store') : route('pegawai.update', $pegawai) }}"
          class="max-w-lg space-y-4 rounded-xl border border-garis bg-permukaan p-6">
        @csrf
        @unless ($baru) @method('PUT') @endunless

        <x-medan nama="nama" label="Nama" :nilai="$pegawai->nama" wajib />
        <x-medan nama="nip" label="NIP" :nilai="$pegawai->nip"
                 petunjuk="Harus unik bila diisi. Boleh dikosongkan untuk pegawai non-ASN." />
        <x-medan nama="pangkat_golongan" label="Pangkat / Golongan" :nilai="$pegawai->pangkat_golongan" />
        <x-medan nama="jabatan" label="Jabatan" :nilai="$pegawai->jabatan" />

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="aktif" value="1" class="rounded border-garis"
                   @checked(old('aktif', $baru ? true : $pegawai->aktif))>
            Masih bertugas
        </label>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-pine px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                Simpan
            </button>
            <a href="{{ route('pegawai.index') }}" class="text-sm text-redup hover:text-tinta">Batal</a>
        </div>
    </form>
@endsection
