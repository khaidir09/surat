@extends('layouts.app')

@php $baru = ! $siswa->exists; @endphp

@section('judul', $baru ? 'Tambah siswa' : 'Ubah siswa')

@section('isi')
    <form method="POST"
          action="{{ $baru ? route('siswa.store') : route('siswa.update', $siswa) }}"
          class="max-w-2xl space-y-4 rounded-xl border border-garis bg-permukaan p-6">
        @csrf
        @unless ($baru) @method('PUT') @endunless

        <div class="grid gap-4 sm:grid-cols-2">
            <x-medan nama="nama" label="Nama" :nilai="$siswa->nama" wajib />
            <x-medan nama="nis_nisn" label="NIS / NISN" :nilai="$siswa->nis_nisn"
                     petunjuk="Harus unik bila diisi." />

            <x-medan nama="kelas" label="Kelas" :nilai="$siswa->kelas" :daftar="$daftarKelas" />
            <x-medan nama="jurusan" label="Jurusan" :nilai="$siswa->jurusan" :daftar="$daftarJurusan" />

            <x-medan nama="tempat_lahir" label="Tempat lahir" :nilai="$siswa->tempat_lahir" />

            {{-- Bukan input type=date: kolomnya text dan data lama berisi
                 bermacam format (E12). Memaksakan satu format akan membuat
                 baris lama gagal disimpan ulang. --}}
            <x-medan nama="tanggal_lahir" label="Tanggal lahir" :nilai="$siswa->tanggal_lahir"
                     petunjuk="Ditulis apa adanya, misalnya 17 Agustus 2008." />

            <x-medan nama="nama_orang_tua" label="Nama orang tua" :nilai="$siswa->nama_orang_tua" />
            <x-medan nama="tahun_masuk" label="Tahun masuk" tipe="number" :nilai="$siswa->tahun_masuk" />
        </div>

        <x-medan nama="alamat" label="Alamat" tipe="textarea" :nilai="$siswa->alamat" />

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="aktif" value="1" class="rounded border-garis"
                   @checked(old('aktif', $baru ? true : $siswa->aktif))>
            Masih bersekolah
        </label>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-pine px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                Simpan
            </button>
            <a href="{{ route('siswa.index') }}" class="text-sm text-redup hover:text-tinta">Batal</a>
        </div>
    </form>
@endsection
