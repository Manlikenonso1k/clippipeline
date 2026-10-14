<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('title') | ClipPipeline</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
    <header class="border-b border-white/10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 py-4 flex items-center justify-between">
            <a href="{{ url('/') }}" class="font-semibold tracking-wide text-white">ClipPipeline</a>
            <a href="{{ url('/') }}" class="text-sm text-slate-400 hover:text-white">Home</a>
        </div>
    </header>

    <main class="flex-grow mx-auto w-full max-w-4xl px-4 sm:px-6 py-12 space-y-8 leading-7 text-slate-300">
        <div>
            <p class="text-sm uppercase tracking-[0.3em] text-slate-400">ClipPipeline</p>
            <h1 class="mt-3 text-3xl sm:text-4xl font-bold text-white">@yield('title')</h1>
            @hasSection('updated')
                <p class="mt-3 text-slate-400">Last updated: @yield('updated')</p>
            @endif
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-emerald-200">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-red-200">{{ $errors->first() }}</div>
        @endif

        @yield('content')
    </main>

    @include('partials.public-footer')
</body>
</html>
