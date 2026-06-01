@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto py-12">
    <h1 class="text-2xl font-semibold mb-6">Integrations</h1>

    <div class="grid grid-cols-1 gap-4">
        <a href="{{ route('integrations.redirect', ['provider' => 'youtube']) }}" class="inline-block px-4 py-3 bg-blue-600 text-white rounded">Connect YouTube (Google)</a>
        <a href="{{ route('integrations.redirect', ['provider' => 'instagram']) }}" class="inline-block px-4 py-3 bg-pink-600 text-white rounded">Connect Instagram (Meta)</a>
        <a href="{{ route('integrations.redirect', ['provider' => 'tiktok']) }}" class="inline-block px-4 py-3 bg-black text-white rounded">Connect TikTok</a>
    </div>

    <p class="mt-6 text-sm text-gray-600">After authorizing, tokens will be stored in your <code>social_accounts</code> record.</p>
</div>
@endsection
