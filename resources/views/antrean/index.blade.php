@extends('layouts.app')

@section('judul', 'Antrean persetujuan')

@php
    // Padanan .pill pada index.html: warna per seksi dipertahankan supaya
    // staf mengenali bentuk yang sama seperti dashboard lama.
    $seksi = [
        ['Surat Tugas — Menunggu Persetujuan', 'bg-amber/15 text-amber', $tugasMenunggu, 'tugas'],
        ['Surat Tugas — Menunggu TTD Kepsek', 'bg-info/15 text-info', $tugasTtd, 'ttd'],
        ['Surat Keterangan — Menunggu Persetujuan', 'bg-ket/15 text-ket', $keteranganMenunggu, 'keterangan'],
    ];
@endphp

@section('isi')
    @if ($galat)
        <div class="mb-5 rounded-xl border border-tolak/30 bg-tolak/5 p-4 text-sm">
            <p class="font-medium text-tolak">Daftar tidak dapat dimuat.</p>
            <p class="mt-1 text-redup">{{ $galat }}</p>
            <p class="mt-1 text-redup">
                Halaman ini sedang tidak dapat melihat antrean — bukan berarti
                antreannya kosong. Jangan menganggap tidak ada pekerjaan.
            </p>
        </div>
    @endif

    @if (session('konfirmasi'))
        @php $k = session('konfirmasi'); @endphp
        <div class="mb-5 rounded-xl border border-amber/40 bg-amber/10 p-4">
            <p class="mb-2 text-sm font-medium text-amber">Nomor kemungkinan ganda</p>
            <p class="mb-3 text-sm">{{ $k['pesan'] }}</p>

            {{-- E5: peringatan, bukan penolakan. Buku agenda fisik yang
                 menentukan, jadi staf harus tetap bisa melanjutkan. --}}
            <form method="POST" action="{{ route('antrean.putuskan', [$k['jenis'], $k['id']]) }}" class="inline">
                @csrf
                <input type="hidden" name="aksi" value="setujui">
                <input type="hidden" name="nomor" value="{{ $k['nomor'] }}">
                {{-- Tanggal yang tadi diketik ikut dibawa; tanpa ini konfirmasi
                     duplikat akan diam-diam mengembalikannya ke hari ini. --}}
                <input type="hidden" name="tanggal" value="{{ $k['tanggal'] }}">
                <input type="hidden" name="abaikan_duplikat" value="1">
                <button type="submit" class="rounded-lg bg-amber px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                    Tetap setujui dengan nomor {{ $k['nomor'] }}
                    @if ($k['tanggal'])
                        <span class="font-normal">
                            tanggal {{ \Illuminate\Support\Carbon::parse($k['tanggal'])->translatedFormat('j M Y') }}
                        </span>
                    @endif
                </button>
            </form>

            <a href="{{ route('antrean.index') }}" class="ms-3 text-sm text-redup hover:text-tinta">Batal</a>
        </div>
    @endif

    <div class="mb-5 flex items-center justify-between gap-4">
        <p class="text-sm text-redup">
            {{ $jumlah }} permintaan di antrean.
            {{-- Sengaja tanpa muat ulang otomatis 30 detik seperti index.html:
                 nomor surat kini diketik di dalam kartu, dan pemuatan ulang di
                 tengah pengetikan akan menghapusnya. --}}
            Muat ulang secara manual bila perlu.
        </p>
        <a href="{{ route('antrean.index') }}"
           class="shrink-0 rounded-lg border border-garis px-4 py-2 text-sm hover:bg-latar">Muat ulang</a>
    </div>

    @if (! $galat && $jumlah === 0)
        <p class="rounded-xl border border-garis bg-permukaan px-4 py-10 text-center text-redup mb-5">
            Belum ada permintaan yang perlu diproses.
        </p>
    @endif

    @foreach ($seksi as [$judul, $warnaPill, $daftar, $bentuk])
        <section class="mb-8">
            <div class="mb-3 flex items-center gap-3">
                <h2 class="text-base font-medium">{{ $judul }}</h2>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $warnaPill }}">{{ count($daftar) }}</span>
            </div>

            @if (! $daftar)
                <p class="rounded-xl border border-garis bg-permukaan px-4 py-6 text-center text-sm text-redup">
                    Tidak ada.
                </p>
            @else
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($daftar as $s)
                        <x-kartu-antrean :surat="$s" :bentuk="$bentuk" />
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach

    @if ($takDikenal)
        {{-- E3: cacat index.html yang membuang item ini tanpa jejak tidak
             ikut terbawa. Satu salah ketik status di n8n cukup untuk membuat
             permintaan hilang dari layar tanpa siapa pun tahu. --}}
        <section class="mb-8">
            <div class="mb-3 flex items-center gap-3">
                <h2 class="text-base font-medium">Status tidak dikenal</h2>
                <span class="rounded-full bg-tolak/15 px-2.5 py-0.5 text-xs font-medium text-tolak">
                    {{ count($takDikenal) }}
                </span>
            </div>

            <p class="mb-3 text-sm text-redup">
                Permintaan berikut tidak cocok dengan kombinasi jenis dan status
                mana pun yang dikenal dashboard. Ditampilkan apa adanya supaya
                tidak hilang diam-diam; tindakannya perlu diperiksa di n8n.
            </p>

            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($takDikenal as $s)
                    <article class="rounded-xl border border-tolak/30 bg-permukaan p-4">
                        <p class="mb-2 text-sm">
                            <span class="rounded bg-latar px-1.5 py-0.5 text-xs">#{{ $s['id'] ?? '?' }}</span>
                            <span class="ms-2 text-redup">jenis:</span> {{ $s['jenis'] ?? '(kosong)' }}
                            <span class="ms-2 text-redup">status:</span> {{ $s['status'] ?? '(kosong)' }}
                        </p>
                        <pre class="overflow-x-auto rounded bg-latar p-2 text-xs">{{ json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <script>
        // Padanan kelas .busy pada index.html:300-303. Mencegah klik ganda
        // yang akan mengirim dua keputusan untuk satu surat.
        document.querySelectorAll('form[data-kartu]').forEach((form) => {
            form.addEventListener('submit', (peristiwa) => {
                // JANGAN menonaktifkan tombol di sini.
                //
                // Aksi (setujui/tolak) dibawa oleh tombol yang ditekan, dan
                // peramban tidak menyertakan kontrol ber-atribut disabled ke
                // dalam payload. Versi pertama halaman ini menonaktifkan
                // seluruh tombol kartu saat submit, sehingga "aksi" lenyap
                // sebelum terkirim dan setiap keputusan gagal.
                //
                // Menandai form dan meredupkan kartu mencapai tujuan yang sama
                // — mencegah keputusan ganda untuk satu surat — tanpa pernah
                // menyentuh isi yang dikirim.
                if (form.dataset.terkirim) {
                    peristiwa.preventDefault();
                    return;
                }

                form.dataset.terkirim = '1';
                form.closest('article').classList.add('opacity-50', 'pointer-events-none');
            });
        });
    </script>
@endsection
