@extends('layouts.app')

@section('judul', 'Status kehadiran kepala sekolah')

@section('isi')
    <div class="max-w-xl space-y-5">
        <div class="rounded-xl border border-garis bg-permukaan p-5">
            <p class="mb-1 text-xs font-medium text-redup">Status saat ini</p>

            @if ($terkini)
                <p class="text-lg font-medium text-pine">{{ $terkini->status }}</p>

                @if (filled($terkini->estimasi_kembali))
                    <p class="text-sm">Perkiraan kembali: {{ $terkini->estimasi_kembali }}</p>
                @endif

                @if (filled($terkini->keterangan))
                    <p class="text-sm text-redup">{{ $terkini->keterangan }}</p>
                @endif

                <p class="mt-2 text-xs text-redup">
                    Diperbarui
                    {{ $terkini->diperbarui_pada?->translatedFormat('j F Y, H:i') ?? 'entah kapan' }}
                    @if (filled($terkini->diperbarui_oleh))
                        oleh {{ $terkini->diperbarui_oleh }}
                    @endif
                </p>
            @else
                {{-- Tabel baris tunggal ini bisa saja masih kosong. --}}
                <p class="text-redup">Belum pernah diisi.</p>
            @endif
        </div>

        <form method="POST" action="{{ route('status-kepsek.store') }}"
              class="space-y-4 rounded-xl border border-garis bg-permukaan p-6">
            @csrf

            <div>
                <label for="status" class="mb-1 block text-sm font-medium">Status baru</label>
                <select id="status" name="status" required
                        class="w-full rounded-lg border border-garis px-3 py-2 text-sm focus:border-pine focus:outline-none">
                    @foreach (App\Models\StatusKepsek::PILIHAN as $pilihan)
                        <option value="{{ $pilihan }}"
                            @selected(old('status', $terkini?->status) === $pilihan)>{{ $pilihan }}</option>
                    @endforeach
                </select>
            </div>

            <x-medan nama="estimasi" label="Perkiraan kembali"
                     petunjuk="Contoh: pukul 13.00, atau Senin depan." />

            <x-medan nama="keterangan" label="Keterangan" tipe="textarea" />

            <p class="text-xs text-redup">
                Perubahan dikirim ke layanan surat, yang memperbarui status
                sekaligus memberitahukannya ke kanal lain. Kalau pengiriman
                gagal, tidak ada yang tersimpan.
            </p>

            <button type="submit"
                    class="rounded-lg bg-pine px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                Perbarui status
            </button>
        </form>
    </div>
@endsection
