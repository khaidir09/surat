@props(['surat', 'bentuk'])

@php
    $id = $surat['id'] ?? null;
    $jenis = $surat['jenis'] ?? null;

    // Semua field bisa saja kosong: yang tiba di sini adalah JSON dari n8n,
    // bukan baris database dengan kolom yang terjamin.
    $isi = fn ($kunci) => filled($surat[$kunci] ?? null) ? $surat[$kunci] : '—';

    $sejak = filled($surat['created_at'] ?? null)
        ? \Illuminate\Support\Carbon::parse($surat['created_at'])->diffForHumans()
        : null;

    $awalan = $jenis === 'surat_tugas' ? 'ST' : 'SK';

    // Aksen garis kiri 4px per seksi, sama seperti .card / .card.ttd /
    // .card.ket pada index.html:199-207 — penanda cepat yang sudah dikenal
    // staf untuk membedakan jenis kartu tanpa membaca judulnya.
    $aksen = match ($bentuk) {
        'ttd' => 'border-l-info',
        'keterangan' => 'border-l-ket',
        default => 'border-l-amber',
    };
@endphp

<article {{ $attributes->merge(['class' => "rounded-xl border border-garis border-l-4 $aksen bg-permukaan p-4"]) }}>
    <div class="mb-3 flex items-baseline justify-between gap-3">
        <span class="rounded bg-latar px-2 py-0.5 text-xs font-medium">#{{ $awalan }}-{{ $id }}</span>
        @if ($sejak)
            <span class="text-xs text-redup">{{ $sejak }}</span>
        @endif
    </div>

    @if ($bentuk === 'keterangan')
        <p class="mb-2 font-medium">{{ $isi('nama_siswa') }}</p>
        <dl class="mb-4 space-y-1 text-sm">
            <div class="flex gap-2"><dt class="w-24 shrink-0 text-redup">Kelas</dt><dd>{{ $isi('kelas_jurusan') }}</dd></div>
            <div class="flex gap-2"><dt class="w-24 shrink-0 text-redup">Keperluan</dt><dd>{{ $isi('keperluan') }}</dd></div>
        </dl>
    @else
        @if (filled($surat['petugas'] ?? null))
            <ul class="mb-3 space-y-0.5 text-sm">
                @foreach ($surat['petugas'] as $orang)
                    <li>
                        {{ $orang['nama'] ?? '(tanpa nama)' }}
                        @if (filled($orang['jabatan'] ?? null))
                            <span class="text-redup">— {{ $orang['jabatan'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            {{-- E13: junction boleh kosong. --}}
            <p class="mb-3 text-sm text-redup">Belum ada petugas.</p>
        @endif

        <dl class="mb-4 space-y-1 text-sm">
            <div class="flex gap-2"><dt class="w-24 shrink-0 text-redup">Kegiatan</dt><dd>{{ $isi('kegiatan') }}</dd></div>
            <div class="flex gap-2"><dt class="w-24 shrink-0 text-redup">Tempat</dt><dd>{{ $isi('tempat') }}</dd></div>
            <div class="flex gap-2">
                <dt class="w-24 shrink-0 text-redup">Tanggal</dt>
                <dd>
                    @if (filled($surat['tanggal_mulai'] ?? null))
                        {{ \Illuminate\Support\Carbon::parse($surat['tanggal_mulai'])->translatedFormat('j M Y') }}
                        @if (filled($surat['tanggal_selesai'] ?? null) && $surat['tanggal_selesai'] !== $surat['tanggal_mulai'])
                            &ndash; {{ \Illuminate\Support\Carbon::parse($surat['tanggal_selesai'])->translatedFormat('j M Y') }}
                        @endif
                    @else
                        —
                    @endif
                </dd>
            </div>
        </dl>
    @endif

    @if ($bentuk === 'ttd')
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('surat.pdf', [$jenis, $id]) }}" target="_blank" rel="noopener"
               class="rounded-lg border border-garis px-4 py-2 text-sm hover:bg-latar">Unduh PDF</a>

            <form method="POST" action="{{ route('antrean.selesai', [$jenis, $id]) }}" data-kartu
                  onsubmit="return confirm('Tandai surat #{{ $id }} sudah ditandatangani? Pemohon akan diberi tahu.')">
                @csrf
                <button type="submit" class="rounded-lg bg-setuju px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                    Sudah ditandatangani
                </button>
            </form>
        </div>
    @else
        <form method="POST" action="{{ route('antrean.putuskan', [$jenis, $id]) }}" data-kartu class="space-y-3">
            @csrf

            <div>
                <label for="nomor-{{ $awalan }}-{{ $id }}" class="mb-1 block text-xs font-medium text-redup">
                    Nomor urut surat (angka saja, sesuai buku agenda)
                </label>

                {{-- Diketik langsung di kartu, bukan lewat prompt() seperti
                     index.html:611. Penomoran manual memang disengaja (K9),
                     tetapi prompt() tidak dapat divalidasi, tidak menyimpan
                     isian saat gagal, dan tidak dapat diperiksa ulang. --}}
                <input id="nomor-{{ $awalan }}-{{ $id }}" name="nomor" type="text"
                       inputmode="numeric" pattern="\d+" placeholder="mis. 68"
                       class="w-32 rounded-lg border border-garis px-3 py-2 text-sm focus:border-pine focus:outline-none">
            </div>

            <div>
                <label for="tanggal-{{ $awalan }}-{{ $id }}" class="mb-1 block text-xs font-medium text-redup">
                    Tanggal surat
                </label>

                {{-- Terisi hari ini karena itulah yang benar hampir setiap
                     kali; tetap dapat diubah untuk surat yang baru disetujui
                     belakangan agar tanggalnya cocok dengan buku agenda.
                     Nilainya dikirim apa adanya ke n8n, yang merakit
                     new Date(tgl + 'T00:00:00'), jadi formatnya harus Y-m-d. --}}
                <input id="tanggal-{{ $awalan }}-{{ $id }}" name="tanggal" type="date"
                       value="{{ now()->toDateString() }}"
                       class="w-44 rounded-lg border border-garis px-3 py-2 text-sm focus:border-pine focus:outline-none">
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit" name="aksi" value="setujui"
                        class="rounded-lg bg-setuju px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                    Setujui &amp; terbitkan
                </button>

                <button type="submit" name="aksi" value="tolak"
                        class="rounded-lg border border-tolak px-4 py-2 text-sm font-medium text-tolak hover:bg-tolak/10"
                        onclick="return confirm('Tolak surat #{{ $id }}? Pemohon akan diberi tahu.')">
                    Tolak
                </button>
            </div>
        </form>
    @endif
</article>
