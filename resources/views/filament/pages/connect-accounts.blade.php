@extends('filament::page')

@section('content')
<div class="space-y-6">
    <h2 class="text-2xl font-semibold">Platform Integrations</h2>

    @php
        $userId = auth()->id();
        $accounts = [];
        if ($userId) {
            $accounts = \Illuminate\Support\Facades\DB::table('social_accounts')
                ->where('user_id', $userId)
                ->get()
                ->keyBy('provider');
        }
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach(['youtube' => 'YouTube Shorts', 'instagram' => 'Instagram Reels', 'tiktok' => 'TikTok'] as $key => $label)
            @php $connected = isset($accounts[$key]); @endphp
            <div class="p-4 border rounded-lg bg-white shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="font-semibold">{{ $label }}</h3>
                        <p class="text-sm text-gray-500">{{ $connected ? 'Connected' : 'Not connected' }}</p>
                    </div>
                    <div class="text-right">
                        @if($connected)
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded">Active</span>
                        @else
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded">Inactive</span>
                        @endif
                    </div>
                </div>

                <div class="mt-4">
                    @if($connected)
                        <form method="POST" action="{{ route('integrations.disconnect', ['provider' => $key]) }}">
                            @csrf
                            <button type="submit" class="inline-block px-3 py-2 bg-red-600 text-white rounded">Disconnect</button>
                        </form>
                    @else
                        <a href="{{ route('integrations.redirect', ['provider' => $key]) }}" class="inline-block px-3 py-2 bg-blue-600 text-white rounded">Connect {{ $label }}</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

@endsection
