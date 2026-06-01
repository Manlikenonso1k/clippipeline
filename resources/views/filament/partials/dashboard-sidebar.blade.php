@php
    $items = [
        [
            'label' => 'Overview',
            'href' => route('filament.admin.pages.dashboard'),
            'icon' => 'dashboard',
            'active' => request()->routeIs('filament.admin.pages.dashboard'),
        ],
        [
            'label' => 'TikTok',
            'href' => route('filament.admin.pages.tiktok-insights'),
            'icon' => 'video_library',
            'active' => request()->routeIs('filament.admin.pages.tiktok-insights'),
        ],
        [
            'label' => 'YouTube',
            'href' => route('integrations.index', ['provider' => 'youtube']),
            'icon' => 'smart_display',
            'active' => false,
        ],
        [
            'label' => 'Instagram',
            'href' => route('integrations.index', ['provider' => 'instagram']),
            'icon' => 'photo_camera',
            'active' => false,
        ],
    ];
@endphp

<nav class="clippy-sidebar" aria-label="Dashboard sections">
    <div class="clippy-sidebar__brand">
        <div class="clippy-sidebar__avatar">CL</div>
        <div>
            <h1>Creator Hub</h1>
            <p>Pro Dashboard</p>
        </div>
    </div>

    <div class="clippy-sidebar__links">
        @foreach($items as $item)
            <a
                href="{{ $item['href'] }}"
                class="clippy-sidebar__link {{ $item['active'] ? 'is-active' : '' }}"
                @if($item['active']) aria-current="page" @endif
            >
                <span class="clippy-sidebar__icon clippy-sidebar__icon--{{ $item['icon'] }}" aria-hidden="true"></span>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>

    <div class="clippy-sidebar__footer">
        <a href="{{ route('filament.admin.pages.connect-accounts') }}" class="clippy-sidebar__link">
            <span class="clippy-sidebar__icon clippy-sidebar__icon--settings" aria-hidden="true"></span>
            <span>Settings</span>
        </a>
        <button type="button" class="clippy-sidebar__cta">Upgrade to Pro</button>
    </div>
</nav>
