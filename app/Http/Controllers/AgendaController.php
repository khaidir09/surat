<?php

namespace App\Http\Controllers;

use App\Models\Agenda;
use App\Models\Aktivitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'bulan' => ['nullable', 'date_format:Y-m'],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);

        $bulan = $request->filled('bulan')
            ? Carbon::createFromFormat('Y-m', $request->input('bulan'))->startOfMonth()
            : Carbon::now()->startOfMonth();

        $kegiatan = Agenda::query()
            ->cari($request->string('cari')->toString())
            ->padaBulan((int) $bulan->year, (int) $bulan->month)
            ->orderBy('tanggal_mulai')
            ->orderBy('judul')
            ->get();

        return view('agenda.index', [
            'kegiatan' => $kegiatan,
            'bulan' => $bulan,
            'kalender' => $this->susunKalender($bulan, $kegiatan),
            'cari' => $request->string('cari')->toString(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('agenda.form', [
            'agenda' => new Agenda([
                'tanggal_mulai' => $request->input('tanggal', Carbon::now()->toDateString()),
                'tanggal_selesai' => $request->input('tanggal', Carbon::now()->toDateString()),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $agenda = Agenda::create($request->validate($this->aturan()));

        Aktivitas::catat('buat_agenda', 'agenda', $agenda->id, ['judul' => $agenda->judul]);

        return redirect()
            ->route('agenda.index', ['bulan' => $agenda->tanggal_mulai->format('Y-m')])
            ->with('sukses', 'Kegiatan ' . $agenda->judul . ' ditambahkan.');
    }

    public function edit(Agenda $agenda): View
    {
        return view('agenda.form', compact('agenda'));
    }

    public function update(Request $request, Agenda $agenda): RedirectResponse
    {
        $agenda->update($request->validate($this->aturan()));

        if ($agenda->wasChanged()) {
            Aktivitas::catat('ubah_agenda', 'agenda', $agenda->id, [
                'berubah' => array_keys($agenda->getChanges()),
            ]);
        }

        return redirect()
            ->route('agenda.index', ['bulan' => $agenda->tanggal_mulai->format('Y-m')])
            ->with('sukses', 'Kegiatan ' . $agenda->judul . ' diperbarui.');
    }

    public function destroy(Agenda $agenda): RedirectResponse
    {
        $bulan = $agenda->tanggal_mulai->format('Y-m');

        $agenda->delete();

        Aktivitas::catat('hapus_agenda', 'agenda', $agenda->id, ['judul' => $agenda->judul]);

        return redirect()
            ->route('agenda.index', ['bulan' => $bulan])
            ->with('sukses', 'Kegiatan ' . $agenda->judul . ' dihapus.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function aturan(): array
    {
        return [
            'judul' => ['required', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date'],

            // E18: rentang terbalik ditolak, bukan diam-diam ditukar.
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],

            'kategori' => ['nullable', 'string', 'max:255'],
            'tempat' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Menyusun petak kalender bulanan: selalu dimulai hari Senin dan berisi
     * kelipatan tujuh hari, sehingga barisnya rapi tanpa perhitungan di Blade.
     *
     * @param  \Illuminate\Support\Collection<int, Agenda>  $kegiatan
     * @return array<int, array<int, array{tanggal: Carbon, bulanIni: bool, kegiatan: array<int, Agenda>}>>
     */
    private function susunKalender(Carbon $bulan, $kegiatan): array
    {
        $mulai = $bulan->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $akhir = $bulan->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $petak = [];
        $hari = $mulai->copy();

        while ($hari <= $akhir) {
            $petak[] = [
                'tanggal' => $hari->copy(),
                'bulanIni' => $hari->month === $bulan->month,
                // Kegiatan berhari-hari muncul di setiap hari yang dilaluinya,
                // bukan hanya pada tanggal mulainya.
                'kegiatan' => $kegiatan->filter(
                    fn (Agenda $a) => $hari->betweenIncluded($a->tanggal_mulai, $a->tanggal_selesai)
                )->values()->all(),
            ];

            $hari->addDay();
        }

        return array_chunk($petak, 7);
    }
}
