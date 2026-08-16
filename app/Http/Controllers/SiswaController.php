<?php

namespace App\Http\Controllers;

use App\Models\Aktivitas;
use App\Models\Siswa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiswaController extends Controller
{
    public function index(Request $request): View
    {
        $siswa = Siswa::query()
            ->cari($request->string('cari')->toString())
            ->when($request->filled('kelas'), fn ($q) => $q->where('kelas', $request->input('kelas')))
            ->when($request->filled('jurusan'), fn ($q) => $q->where('jurusan', $request->input('jurusan')))
            ->when($request->filled('status'), fn ($q) => $q->where('aktif', $request->input('status') === 'aktif'))
            ->orderBy('nama')
            ->paginate(25)
            ->withQueryString();

        return view('siswa.index', [
            'siswa' => $siswa,
            'daftarKelas' => Siswa::daftarKelas(),
            'daftarJurusan' => Siswa::daftarJurusan(),
        ]);
    }

    public function create(): View
    {
        return view('siswa.form', [
            'siswa' => new Siswa(['aktif' => true]),
            'daftarKelas' => Siswa::daftarKelas(),
            'daftarJurusan' => Siswa::daftarJurusan(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->aturan());
        $data['aktif'] = $request->boolean('aktif');

        $siswa = Siswa::create($data);

        Aktivitas::catat('buat_siswa', 'siswa', $siswa->id, ['nama' => $siswa->nama]);

        return redirect()->route('siswa.index')->with('sukses', 'Siswa ' . $siswa->nama . ' ditambahkan.');
    }

    public function edit(Siswa $siswa): View
    {
        return view('siswa.form', [
            'siswa' => $siswa,
            'daftarKelas' => Siswa::daftarKelas(),
            'daftarJurusan' => Siswa::daftarJurusan(),
        ]);
    }

    public function update(Request $request, Siswa $siswa): RedirectResponse
    {
        $data = $request->validate($this->aturan($siswa));
        $data['aktif'] = $request->boolean('aktif');

        $siswa->update($data);

        if ($siswa->wasChanged()) {
            Aktivitas::catat('ubah_siswa', 'siswa', $siswa->id, [
                'berubah' => array_keys($siswa->getChanges()),
            ]);
        }

        return redirect()->route('siswa.index')->with('sukses', 'Data ' . $siswa->nama . ' diperbarui.');
    }

    /**
     * Soft delete: surat keterangan yang pernah terbit tetap menampilkan nama
     * siswanya, dengan penanda nonaktif (E7, E8).
     */
    public function destroy(Siswa $siswa): RedirectResponse
    {
        $siswa->delete();

        Aktivitas::catat('hapus_siswa', 'siswa', $siswa->id, ['nama' => $siswa->nama]);

        return redirect()->route('siswa.index')
            ->with('sukses', 'Siswa ' . $siswa->nama . ' dihapus. Riwayat surat tidak terpengaruh.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function aturan(?Siswa $siswa = null): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],

            'nis_nisn' => [
                'nullable', 'string', 'max:255',
                Rule::unique('siswa', 'nis_nisn')->ignore($siswa)->whereNull('deleted_at'),
            ],

            'kelas' => ['nullable', 'string', 'max:255'],
            'jurusan' => ['nullable', 'string', 'max:255'],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],

            // Diterima sebagai teks bebas, tanpa aturan date (E12). Data lama
            // berisi bermacam format hasil ketikan lewat chatbot, dan memaksa
            // satu format di sini akan membuat baris lama gagal disimpan ulang.
            'tanggal_lahir' => ['nullable', 'string', 'max:255'],

            'nama_orang_tua' => ['nullable', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'tahun_masuk' => ['nullable', 'integer', 'min:1900', 'max:2100'],
        ];
    }
}
