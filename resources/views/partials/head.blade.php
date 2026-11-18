<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#071a11">
<meta name="color-scheme" content="dark">
<meta name="description" content="{{ \App\Models\Setting::get('site_tagline') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $siteName ?? 'AstoOne' }}">
<title>{{ isset($title) ? $title.' · ' : '' }}{{ $siteName ?? 'AstoOne' }}</title>
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/favicon.png" type="image/png">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
@vite(['resources/css/app.css', 'resources/js/app.js'])
