{{-- Hata sayfaları oturum gerektirmeyen bağımsız bir düzen kullanır (eşleşmeyen rotalarda oturum başlatılmaz). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script>if (window.matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark');</script>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen flex-col">
    <header class="container-page flex h-16 items-center"><a href="{{ url('/') }}"><x-logo/></a></header>
    <main class="container-page flex flex-1 items-center justify-center py-16">
        <div class="max-w-md text-center">
            <p class="font-display text-7xl font-extrabold text-katman-600 dark:text-katman-400">@yield('code')</p>
            <h1 class="mt-4 text-2xl font-extrabold">@yield('title')</h1>
            <p class="mt-3 text-muted">@yield('message')</p>
            <a href="{{ url('/') }}" class="btn-primary mt-8">{{ __('Ana sayfaya dön') }}</a>
        </div>
    </main>
</body>
</html>
