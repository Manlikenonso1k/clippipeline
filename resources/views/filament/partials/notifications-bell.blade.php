@php
    $unread = \App\Filament\Pages\Notifications::unreadCount();
@endphp

<x-filament::icon-button
    tag="a"
    :href="\App\Filament\Pages\Notifications::getUrl()"
    icon="heroicon-o-bell"
    color="gray"
    label="Notifications"
    :badge="$unread > 0 ? $unread : null"
    badge-color="danger"
/>
