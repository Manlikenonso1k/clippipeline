{{-- Shared public footer (COMPLIANCE C-05, U-07): home + every public page. --}}
@php($supportEmail = config('clippipeline.support_email'))
<footer class="w-full border-t border-white/10 mt-16">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 py-8 text-sm text-slate-400 space-y-4">
        <nav class="flex flex-wrap gap-x-5 gap-y-2" aria-label="Legal">
            <a class="hover:text-white underline-offset-4 hover:underline" href="{{ url('/privacy') }}">Privacy</a>
            <a class="hover:text-white underline-offset-4 hover:underline" href="{{ url('/terms') }}">Terms</a>
            <a class="hover:text-white underline-offset-4 hover:underline" href="{{ url('/data-deletion') }}">Data deletion</a>
            <a class="hover:text-white underline-offset-4 hover:underline" href="{{ url('/delete-account') }}">Delete account</a>
            <a class="hover:text-white underline-offset-4 hover:underline" href="mailto:{{ $supportEmail }}">Support</a>
        </nav>
        <p>&copy; {{ date('Y') }} ClipPipeline. ClipPipeline is not affiliated with, endorsed or sponsored by TikTok, Meta (Facebook, Instagram) or YouTube (Google).</p>
    </div>
</footer>
