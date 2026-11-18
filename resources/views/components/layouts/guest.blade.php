@props(['title' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head', ['title' => $title ?? 'Masuk'])
</head>
<body class="min-h-svh bg-pit">
    <main class="relative mx-auto flex min-h-svh max-w-[480px] flex-col overflow-hidden px-6 pt-[max(1.5rem,env(safe-area-inset-top))] pb-[calc(6rem+env(safe-area-inset-bottom))]">
        <x-contour class="absolute -top-10 -right-24 h-[420px] w-[620px] opacity-70" />
        <div aria-hidden="true" class="absolute -top-40 -right-40 h-96 w-96 rounded-full bg-neon/20 blur-[100px]"></div>
        <div class="relative flex grow flex-col">
            {{ $slot }}
        </div>
    </main>
    <x-mentari-chat page="login" :launcher="true" :tips="['Halo! Saya Mentari 👋', 'Masuk cukup pakai NRP dan PIN 6 angka.', 'Lupa PIN? Saya sambungkan ke admin.']" />
    @include('partials.toast')
</body>
</html>
