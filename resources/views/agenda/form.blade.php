@extends('layouts.app')

@php $baru = ! $agenda->exists; @endphp

@section('judul', $baru ? 'Tambah kegiatan' : 'Ubah kegiatan')

@section('isi')
    <form method="POST"
          action="{{ $baru ? route('agenda.store') : route('agenda.update', $agenda) }}"
          class="max-w-2xl space-y-4 rounded-xl border border-garis bg-permukaan p-6">
        @csrf
        @unless ($baru) @method('PUT') @endunless

        <x-medan nama="judul" label="Judul kegiatan" :nilai="$agenda->judul" wajib />

        <div class="grid gap-4 sm:grid-cols-2">
            <x-medan nama="tanggal_mulai" label="Tanggal mulai" tipe="date"
                     :nilai="$agenda->tanggal_mulai?->toDateString()" wajib />
            <x-medan nama="tanggal_selesai" label="Tanggal selesai" tipe="date"
                     :nilai="$agenda->tanggal_selesai?->toDateString()" wajib
                     petunjuk="Isi sama dengan tanggal mulai untuk kegiatan sehari." />

            <x-medan nama="kategori" label="Kategori" :nilai="$agenda->kategori" />
            <x-medan nama="tempat" label="Tempat" :nilai="$agenda->tempat" />
        </div>

        <x-medan nama="keterangan" label="Keterangan" tipe="textarea" :nilai="$agenda->keterangan" />

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-pine px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                Simpan
            </button>
            <a href="{{ route('agenda.index') }}" class="text-sm text-redup hover:text-tinta">Batal</a>
        </div>
    </form>
@endsection
