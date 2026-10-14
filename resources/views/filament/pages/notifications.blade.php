<x-filament-panels::page>
    @php
        $feed = $this->feed();
        $colors = ['distribution_published' => 'success', 'distribution_failed' => 'danger', 'account_needs_reauth' => 'warning'];
    @endphp

    @if (empty($feed['items']))
        <x-filament::empty-state
            icon="heroicon-o-bell-slash"
            heading="No notifications yet"
            description="You'll see published posts, failed uploads and accounts that need reconnecting here."
        />
    @else
        <x-filament::section>
            <ul style="display: flex; flex-direction: column; gap: 1rem;">
                @foreach ($feed['items'] as $item)
                    @php $link = $this->linkFor($item); @endphp
                    <li wire:key="notification-{{ $item['id'] }}" style="display: flex; gap: 0.75rem; align-items: flex-start; {{ $item['read_at'] ? 'opacity: 0.7;' : '' }}">
                        <x-filament::badge :color="$colors[$item['type']] ?? 'gray'">
                            {{ $item['read_at'] ? 'Read' : 'New' }}
                        </x-filament::badge>
                        <div style="flex: 1; min-width: 0;">
                            <strong>
                                @if ($link)
                                    <x-filament::link :href="$link">{{ $item['title'] }}</x-filament::link>
                                @else
                                    {{ $item['title'] }}
                                @endif
                            </strong>
                            <p style="font-size: 0.875rem;">{{ $item['body'] }}</p>
                            <p style="font-size: 0.75rem; opacity: 0.7;">{{ \Illuminate\Support\Carbon::parse($item['created_at'])->diffForHumans() }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-panels::page>
