<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head', ['title' => 'Offline', 'siteName' => \App\Models\Setting::get('site_name')])
</head>
<body class="grid min-h-svh place-items-center bg-pit px-6 text-center">
    <main class="max-w-sm">
        <x-mentari :size="150" class="mx-auto" />
        <h1 class="mt-4 font-display text-2xl font-bold">Sinyal sedang hilang</h1>
        <p class="mt-2 leading-relaxed text-fog">{{ \App\Models\Setting::get('site_name') }} butuh internet untuk membuka aplikasi. Coba lagi saat sinyal kembali.</p>
        <button onclick="location.reload()" class="btn-neon mt-6 w-full"><i class="ri-refresh-line text-lg"></i> Coba lagi</button>
    </main>
</body>
</html>
