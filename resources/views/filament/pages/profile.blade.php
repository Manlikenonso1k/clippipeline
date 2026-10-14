<x-filament-panels::page>
    @php
        $me = $this->me();
        $plan = $me['plan'];
        $support = $me['flags']['support_email'];
    @endphp

    <form wire:submit="save" style="display: flex; flex-direction: column; gap: 1.5rem;">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit" icon="heroicon-o-check">Save</x-filament::button>
        </div>
    </form>

    <x-filament::section heading="Plan & usage" data-section="plan">
        <x-slot name="afterHeader">
            <x-filament::button tag="a" :href="$plan['billing_url']" size="sm" icon="heroicon-o-arrow-up-circle">
                Upgrade
            </x-filament::button>
        </x-slot>

        <dl style="display: grid; grid-template-columns: max-content 1fr; gap: 0.35rem 1rem; font-size: 0.875rem;">
            <dt><strong>Plan</strong></dt>
            <dd>{{ ucfirst($plan['name']) }}</dd>
            <dt><strong>Clips this month</strong></dt>
            <dd>{{ number_format($plan['clips_used']) }} / {{ $plan['clips_limit'] === null ? 'Unlimited' : number_format($plan['clips_limit']) }}</dd>
            <dt><strong>Renews</strong></dt>
            <dd>{{ $plan['renews_at'] ? \Illuminate\Support\Carbon::parse($plan['renews_at'])->toFormattedDateString() : '—' }}</dd>
        </dl>
    </x-filament::section>

    <x-filament::section heading="Your data">
        <p style="font-size: 0.875rem; margin-bottom: 0.75rem;">Download per-post analytics as a CSV file.</p>
        {{ $this->exportCsvAction }}
    </x-filament::section>

    <x-filament::section heading="Legal & support">
        <ul style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem;">
            <li><x-filament::link :href="url('/privacy')">Privacy policy</x-filament::link></li>
            <li><x-filament::link :href="url('/terms')">Terms of service</x-filament::link></li>
            <li><x-filament::link :href="url('/data-deletion')">Data deletion instructions</x-filament::link></li>
            <li><x-filament::link :href="url('/delete-account')">Delete account (public page)</x-filament::link></li>
            @if ($support)
                <li><x-filament::link :href="'mailto:'.$support">Contact support ({{ $support }})</x-filament::link></li>
            @endif
        </ul>
        <p style="margin-top: 0.75rem; font-size: 0.8125rem; opacity: 0.75;">ClipPipeline is not affiliated with TikTok, Meta or YouTube.</p>
    </x-filament::section>

    <x-filament::section heading="Session">
        <form method="POST" action="{{ \Filament\Facades\Filament::getLogoutUrl() }}">
            @csrf
            <x-filament::button type="submit" color="gray" icon="heroicon-o-arrow-left-on-rectangle">Sign out</x-filament::button>
        </form>
    </x-filament::section>

    <x-filament::section heading="Delete account">
        <p style="font-size: 0.875rem; margin-bottom: 0.75rem;">{{ \App\Filament\Pages\Profile::DELETE_EXPLANATION }}</p>
        {{ $this->deleteAccountAction }}
    </x-filament::section>
</x-filament-panels::page>
