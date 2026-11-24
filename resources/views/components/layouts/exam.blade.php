@props(['title' => 'Ujian'])
<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head', ['title' => $title])
</head>
{{-- Ruang ujian: tanpa navigasi portal & Mentari agar peserta fokus --}}
<body class="min-h-svh bg-pit select-none [-webkit-touch-callout:none]">
    {{ $slot }}
    @include('partials.toast')
</body>
</html>
