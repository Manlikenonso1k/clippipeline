<x-filament-panels::page>
    @php
        $statusColors = ['active' => 'success', 'needs_reauth' => 'warning', 'error' => 'danger'];
        $statusLabels = ['active' => 'Active', 'needs_reauth' => 'Needs reauth', 'error' => 'Error'];
    @endphp

    @if (session('status'))
        <x-filament::callout color="success" icon="heroicon-o-check-circle" :heading="session('status')" />
    @endif

    @if ($errors->any())
        <x-filament::callout color="danger" icon="heroicon-o-exclamation-triangle" :heading="$errors->first()" />
    @endif

    <div style="display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 20rem), 1fr));">
        @foreach ($this->getProviderCards() as $card)
            <x-filament::section :heading="$card['label']" wire:key="provider-{{ $card['provider'] }}">
                <x-slot name="afterHeader">
                    <x-filament::button
                        tag="a"
                        :href="$card['connect_url']"
                        size="sm"
                        :color="$card['connected'] ? 'gray' : 'primary'"
                        icon="heroicon-o-arrow-top-right-on-square"
                    >
                        {{ $card['connected'] ? 'Reconnect' : 'Connect' }}
                    </x-filament::button>
                </x-slot>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    @foreach ($card['rows'] as $row)
                        <div wire:key="row-{{ $row['platform'] }}" data-platform="{{ $row['platform'] }}" style="display: flex; flex-direction: column; gap: 0.35rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap;">
                                <strong>{{ $row['label'] }}</strong>
                                @if ($row['status'])
                                    <x-filament::badge :color="$statusColors[$row['status']] ?? 'gray'">
                                        {{ $statusLabels[$row['status']] ?? $row['status'] }}
                                    </x-filament::badge>
                                @else
                                    <x-filament::badge color="gray">Not connected</x-filament::badge>
                                @endif
                            </div>

                            @if ($row['id'])
                                <dl style="display: grid; grid-template-columns: auto 1fr; gap: 0.15rem 0.75rem; font-size: 0.875rem; opacity: 0.85;">
                                    <dt>Handle</dt><dd>{{ $row['handle'] ?: '—' }}</dd>
                                    <dt>Followers</dt><dd>{{ number_format($row['followers']) }}</dd>
                                    <dt>Token expires</dt><dd>{{ $row['expires_at']?->toDayDateTimeString() ?? '—' }}</dd>
                                    <dt>Last synced</dt><dd>{{ $row['last_synced_at']?->diffForHumans() ?? '—' }}</dd>
                                </dl>

                                @if ($row['error'])
                                    <p style="font-size: 0.875rem; color: rgb(220 38 38);">{{ $row['error'] }}</p>
                                @endif

                                <div>
                                    {{ ($this->disconnectAction)(['account' => $row['id'], 'label' => $row['label']]) }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach
    </div>

</x-filament-panels::page>
