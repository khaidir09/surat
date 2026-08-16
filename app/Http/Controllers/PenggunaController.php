<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Kelola akun — hanya untuk role tas (§6). Tidak ada registrasi mandiri:
 * akun dibuat di sini atau lewat seeder.
 */
class PenggunaController extends Controller
{
    public function index(): View
    {
        $pengguna = User::query()
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->paginate(25);

        return view('pengguna.index', compact('pengguna'));
    }

    public function create(): View
    {
        return view('pengguna.form', ['pengguna' => new User(['aktif' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->aturan());

        User::create($data);

        return redirect()
            ->route('pengguna.index')
            ->with('sukses', 'Akun ' . $data['nama'] . ' dibuat.');
    }

    public function edit(User $pengguna): View
    {
        return view('pengguna.form', compact('pengguna'));
    }

    public function update(Request $request, User $pengguna): RedirectResponse
    {
        $data = $request->validate($this->aturan($pengguna));

        // Kosongkan berarti "biarkan kata sandi lama", bukan "kosongkan".
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $this->pastikanBukanTasTerakhir(
            $pengguna,
            saatIni: $data['role'] !== User::ROLE_TAS || ! $request->boolean('aktif'),
            pesan: 'Ini satu-satunya akun TAS yang aktif. Buat akun TAS lain dulu sebelum mengubah yang ini.'
        );

        $pengguna->update($data + ['aktif' => $request->boolean('aktif')]);

        return redirect()
            ->route('pengguna.index')
            ->with('sukses', 'Akun ' . $pengguna->nama . ' diperbarui.');
    }

    /**
     * Akun tidak pernah dihapus, hanya dinonaktifkan — supaya baris
     * app.aktivitas yang menunjuknya tetap punya nama penanggung jawab.
     */
    public function destroy(Request $request, User $pengguna): RedirectResponse
    {
        if ($pengguna->is($request->user())) {
            throw ValidationException::withMessages([
                'pengguna' => 'Anda tidak dapat menonaktifkan akun sendiri.',
            ]);
        }

        $this->pastikanBukanTasTerakhir(
            $pengguna,
            saatIni: true,
            pesan: 'Ini satu-satunya akun TAS yang aktif. Menonaktifkannya akan mengunci semua orang dari pengelolaan akun.'
        );

        $pengguna->update(['aktif' => false]);

        return redirect()
            ->route('pengguna.index')
            ->with('sukses', 'Akun ' . $pengguna->nama . ' dinonaktifkan.');
    }

    /**
     * Aturan validasi. $pengguna diisi saat memperbarui agar email miliknya
     * sendiri tidak dianggap duplikat.
     *
     * @return array<string, array<int, mixed>>
     */
    private function aturan(?User $pengguna = null): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($pengguna),
            ],
            'password' => [
                $pengguna ? 'nullable' : 'required',
                'string',
                'min:8',
                'confirmed',
            ],
            'role' => ['required', Rule::in(array_keys(User::ROLE))],
        ];
    }

    /**
     * Mencegah sistem terkunci: harus selalu tersisa minimal satu akun TAS
     * aktif, karena hanya role itu yang dapat membuat akun baru.
     */
    private function pastikanBukanTasTerakhir(User $pengguna, bool $saatIni, string $pesan): void
    {
        if (! $saatIni || ! $pengguna->adalahTas() || ! $pengguna->aktif) {
            return;
        }

        $tasAktifLain = User::query()
            ->where('role', User::ROLE_TAS)
            ->where('aktif', true)
            ->whereKeyNot($pengguna->getKey())
            ->exists();

        if (! $tasAktifLain) {
            throw ValidationException::withMessages(['pengguna' => $pesan]);
        }
    }
}
