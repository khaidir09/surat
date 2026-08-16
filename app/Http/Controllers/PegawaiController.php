<?php

namespace App\Http\Controllers;

use App\Models\Aktivitas;
use App\Models\Pegawai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PegawaiController extends Controller
{
    public function index(Request $request): View
    {
        $pegawai = Pegawai::query()
            ->cari($request->string('cari')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('aktif', $request->input('status') === 'aktif'))
            // Nama boleh NULL (E11); baris tanpa nama didorong ke bawah alih-alih
            // menjadi deretan kosong di puncak daftar.
            ->orderByRaw('nama asc nulls last')
            ->paginate(25)
            ->withQueryString();

        return view('pegawai.index', compact('pegawai'));
    }

    public function create(): View
    {
        return view('pegawai.form', ['pegawai' => new Pegawai(['aktif' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->aturan());
        $data['aktif'] = $request->boolean('aktif');

        $pegawai = Pegawai::create($data);

        Aktivitas::catat('buat_pegawai', 'pegawai', $pegawai->id, ['nama' => $pegawai->nama]);

        return redirect()->route('pegawai.index')
            ->with('sukses', 'Pegawai ' . $pegawai->namaTampil() . ' ditambahkan.');
    }

    public function edit(Pegawai $pegawai): View
    {
        return view('pegawai.form', compact('pegawai'));
    }

    public function update(Request $request, Pegawai $pegawai): RedirectResponse
    {
        $data = $request->validate($this->aturan($pegawai));
        $data['aktif'] = $request->boolean('aktif');

        $pegawai->update($data);

        if ($pegawai->wasChanged()) {
            Aktivitas::catat('ubah_pegawai', 'pegawai', $pegawai->id, [
                'berubah' => array_keys($pegawai->getChanges()),
            ]);
        }

        return redirect()->route('pegawai.index')
            ->with('sukses', 'Data ' . $pegawai->namaTampil() . ' diperbarui.');
    }

    /**
     * Soft delete: surat tugas yang pernah terbit tetap harus menampilkan
     * nama petugasnya (E7).
     */
    public function destroy(Pegawai $pegawai): RedirectResponse
    {
        $pegawai->delete();

        Aktivitas::catat('hapus_pegawai', 'pegawai', $pegawai->id, ['nama' => $pegawai->nama]);

        return redirect()->route('pegawai.index')
            ->with('sukses', 'Pegawai ' . $pegawai->namaTampil() . ' dihapus. Riwayat surat tidak terpengaruh.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function aturan(?Pegawai $pegawai = null): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],

            // Unik hanya bila diisi: sebagian pegawai memang belum ber-NIP.
            // Baris terhapus dikecualikan agar NIP-nya dapat dipakai kembali.
            'nip' => [
                'nullable', 'string', 'max:255',
                Rule::unique('pegawai', 'nip')->ignore($pegawai)->whereNull('deleted_at'),
            ],

            'pangkat_golongan' => ['nullable', 'string', 'max:255'],
            'jabatan' => ['nullable', 'string', 'max:255'],
        ];
    }
}
