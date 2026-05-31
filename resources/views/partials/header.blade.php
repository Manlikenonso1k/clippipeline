<nav class="fixed top-0 w-full z-50 bg-surface/30 backdrop-blur-xl border-b border-white/10 transition-all duration-300">
    <div class="flex justify-between items-center h-16 px-gutter max-w-[1440px] mx-auto">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-primary-container" style="font-variation-settings: 'FILL' 1;">movie</span>
            <span class="font-headline-md text-headline-md tracking-tighter text-on-surface">clippipeline</span>
        </div>
        <div class="hidden md:flex items-center gap-8">
            <a class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors duration-200" href="#features">Features</a>
            <a class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors duration-200" href="#pricing">Pricing</a>
            <a class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors duration-200" href="#">System Status</a>
        </div>
        <div>
            @auth
                <a class="px-6 py-2 rounded-full font-label-md text-label-md text-white bg-white/5 border border-white/10 hover:bg-white/10 transition-all hover:scale-95 active:opacity-80" href="{{ route('integrations.index') }}">Dashboard</a>
            @else
                <a class="px-6 py-2 rounded-full font-label-md text-label-md text-white bg-white/5 border border-white/10 hover:bg-white/10 transition-all hover:scale-95 active:opacity-80" href="{{ route('auth.redirect', ['provider' => 'google']) }}">Sign in</a>
            @endauth
        </div>
    </div>
</nav>
