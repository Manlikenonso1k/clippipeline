@extends('legal.layout')

@section('title', 'Data Deletion')
@section('updated', 'October 14, 2026')

@php($support = config('clippipeline.support_email'))

@section('content')
    <section class="space-y-3">
        <p>You can delete the data ClipPipeline holds about you at any time. Choose what you want to remove:</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">Delete everything (your ClipPipeline account)</h2>
        <ul class="list-disc pl-6 space-y-2">
            <li><strong class="text-white">In the app:</strong> Profile &rarr; Delete account, then type DELETE to confirm.</li>
            <li><strong class="text-white">On the web:</strong> go to <a class="text-sky-300 underline" href="{{ url('/delete-account') }}">{{ url('/delete-account') }}</a>, sign in and confirm.</li>
            <li><strong class="text-white">By email:</strong> write to <a class="text-sky-300 underline" href="mailto:{{ $support }}?subject=Delete%20my%20ClipPipeline%20account">{{ $support }}</a> from the email address on your account.</li>
        </ul>
        <p>We revoke our access to every connected platform and sign you out of every device immediately, then delete your videos, posts, statistics, notifications and profile within 7 days.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">Delete one platform's data</h2>
        <p>Disconnecting an account revokes our access at the platform and deletes its tokens, profile data and statistics from ClipPipeline. Videos already published stay on the platform; delete them there if you want them gone.</p>
        <ul class="list-disc pl-6 space-y-2">
            <li><strong class="text-white">YouTube:</strong> in ClipPipeline, Connect &rarr; YouTube &rarr; Disconnect. Or remove ClipPipeline at <a class="text-sky-300 underline" href="https://myaccount.google.com/permissions">myaccount.google.com/permissions</a>.</li>
            <li><strong class="text-white">Facebook and Instagram:</strong> in ClipPipeline, Connect &rarr; Facebook &amp; Instagram &rarr; Disconnect. Or in Facebook: Settings &amp; privacy &rarr; Settings &rarr; Apps and websites &rarr; ClipPipeline &rarr; Remove. When you remove the app there you can also choose to send us a deletion request; you will receive a confirmation code you can check on the <a class="text-sky-300 underline" href="{{ url('/data-deletion/status') }}">deletion status page</a>.</li>
            <li><strong class="text-white">TikTok:</strong> in ClipPipeline, Connect &rarr; TikTok &rarr; Disconnect. Or in TikTok: Settings and privacy &rarr; Security &amp; permissions &rarr; Manage app permissions &rarr; ClipPipeline &rarr; Remove access. TikTok tells us immediately and we delete the tokens; remaining TikTok data is deleted within 30 days if you do not reconnect.</li>
        </ul>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">Delete a single post</h2>
        <p>Open the post in ClipPipeline and choose Delete. This removes the video file, the post and its statistics from ClipPipeline. It does not delete the post from the platforms.</p>
    </section>
@endsection
