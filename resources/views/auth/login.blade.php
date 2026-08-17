<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — {{ config('app.name') }}</title>
    @vite('resources/css/app.css')
</head>
<body class="flex min-h-screen items-center justify-center bg-latar px-4 text-tinta antialiased">

<main class="w-full max-w-sm">
    <img src="{{ asset('images/logo-smkn1amuntai.png') }}" alt="Logo" class="mx-auto mb-4 h-24">
    <h1 class="mb-4 text-center text-lg font-semibold text-pine">{{ config('app.name') }} <br>SMK Negeri 1 Amuntai</h1>
    {{-- <p class="mb-6 text-center text-sm text-redup">Masuk dengan akun yang diberikan staf TAS.</p> --}}

    <form method="POST" action="{{ route('login') }}"
          class="space-y-4 rounded-xl border border-garis bg-permukaan p-6 shadow-sm">
        @csrf

        @if ($errors->any())
            <p class="rounded-lg border border-tolak/30 bg-tolak/10 px-3 py-2 text-sm text-tolak">
                {{ $errors->first() }}
            </p>
        @endif

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username"
                   class="w-full rounded-lg border border-garis px-3 py-2 focus:border-pine focus:outline-none">
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">Kata sandi</label>
            <input id="password" name="password" type="password"
                   required autocomplete="current-password"
                   class="w-full rounded-lg border border-garis px-3 py-2 focus:border-pine focus:outline-none">
        </div>

        <label class="flex items-center gap-2 text-sm text-redup">
            <input type="checkbox" name="ingat" value="1" class="rounded border-garis">
            Ingat saya di perangkat ini
        </label>

        <button type="submit"
                class="w-full rounded-lg bg-pine px-4 py-2 font-medium text-white hover:opacity-90">
            Masuk
        </button>
    </form>
</main>

</body>
</html>
