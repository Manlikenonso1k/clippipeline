<!doctype html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title', 'Clippipeline')</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/hero-slider.css') }}">
    @stack('styles')
    <style>html,body{background:var(--tw-bg-opacity, #111415)}</style>
</head>
<body class="bg-background text-on-surface font-body-md antialiased min-h-screen flex flex-col relative overflow-x-hidden">
    @include('partials.header')

    <main class="flex-grow z-10 pt-[120px] pb-xxl px-gutter max-w-[1440px] mx-auto w-full flex flex-col gap-xxl">
        @yield('content')
    </main>

    @include('partials.footer')

    <script src="{{ asset('js/hero-slider.js') }}"></script>
    @stack('scripts')
</body>
</html>
