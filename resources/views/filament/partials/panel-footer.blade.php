@php
    $support = (string) config('clippipeline.support_email');
@endphp

<footer style="padding: 1.5rem 1rem; font-size: 0.8125rem; text-align: center; opacity: 0.75;">
    <nav style="display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem 1.25rem;">
        <x-filament::link :href="url('/privacy')" size="sm" color="gray">Privacy</x-filament::link>
        <x-filament::link :href="url('/terms')" size="sm" color="gray">Terms</x-filament::link>
        <x-filament::link :href="url('/data-deletion')" size="sm" color="gray">Data deletion</x-filament::link>
        <x-filament::link :href="url('/delete-account')" size="sm" color="gray">Delete account</x-filament::link>
        @if ($support !== '')
            <x-filament::link :href="'mailto:'.$support" size="sm" color="gray">Support</x-filament::link>
        @endif
    </nav>
    <p style="margin-top: 0.5rem;">ClipPipeline is not affiliated with TikTok, Meta or YouTube.</p>
</footer>
