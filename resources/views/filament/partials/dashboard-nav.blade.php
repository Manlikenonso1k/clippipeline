@php
    $tabs = [
        [
            'label' => 'Home',
            'href' => route('filament.admin.pages.dashboard'),
            'icon' => 'home',
            'active' => request()->routeIs('filament.admin.pages.dashboard'),
        ],
        [
            'label' => 'TikTok',
            'href' => route('filament.admin.pages.tiktok-insights'),
            'icon' => 'pulse',
            'active' => request()->routeIs('filament.admin.pages.tiktok-insights'),
        ],
        [
            'label' => 'Integrations',
            'href' => route('filament.admin.pages.connect-accounts'),
            'icon' => 'link',
            'active' => request()->routeIs('filament.admin.pages.connect-accounts'),
        ],
        [
            'label' => 'Settings',
            'href' => route('filament.admin.pages.dashboard'),
            'icon' => 'gear',
            'active' => false,
        ],
    ];
@endphp

<nav class="clippy-bottom-nav" aria-label="Dashboard navigation">
    @foreach($tabs as $tab)
        <a
            href="{{ $tab['href'] }}"
            class="clippy-bottom-nav__item {{ $tab['active'] ? 'is-active' : '' }}"
            @if($tab['active']) aria-current="page" @endif
        >
            <span class="clippy-bottom-nav__icon clippy-bottom-nav__icon--{{ $tab['icon'] }}" aria-hidden="true"></span>
            <span class="clippy-bottom-nav__label">{{ $tab['label'] }}</span>
        </a>
    @endforeach
</nav>
