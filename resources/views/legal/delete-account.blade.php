@extends('legal.layout')

@section('title', 'Delete your ClipPipeline account')

@php($support = config('clippipeline.support_email'))

@section('content')
    <section class="space-y-3">
        <p>This page lets you delete your <strong class="text-white">ClipPipeline</strong> account and its data, whether you use the ClipPipeline mobile app or the website.</p>
        <h2 class="text-2xl font-semibold text-white">What gets deleted</h2>
        <ul class="list-disc pl-6 space-y-2">
            <li>Immediately: our access to every connected YouTube, Facebook, Instagram and TikTok account is revoked, their tokens are deleted, and you are signed out of every device.</li>
            <li>Within 7 days: your uploaded videos, posts and schedules, statistics and follower history, notifications, preferences and your profile.</li>
            <li>Kept: a record that a deletion was requested and completed, without your name or email.</li>
            <li>Not deleted: videos already published on the platforms. Delete those on each platform if you want them removed.</li>
        </ul>
    </section>

    <section class="space-y-4">
        <h2 class="text-2xl font-semibold text-white">Delete now</h2>
        @auth
            <p>Signed in as <span class="text-white">{{ auth()->user()->email }}</span>. Type <span class="font-mono text-white">DELETE</span> to confirm.</p>
            <form method="POST" action="{{ url('/delete-account') }}" class="flex flex-col sm:flex-row gap-3">
                @csrf
                <label for="confirm" class="sr-only">Type DELETE</label>
                <input id="confirm" name="confirm" autocomplete="off" placeholder="DELETE"
                    class="flex-1 rounded-lg bg-slate-900 border border-white/15 px-4 py-2 text-white placeholder-slate-500" />
                <button class="rounded-lg bg-red-600 hover:bg-red-500 px-5 py-2 font-medium text-white">Delete my account</button>
            </form>
        @else
            <p>Sign in with the account you use in ClipPipeline, then confirm the deletion on this page.</p>
            <a href="{{ url('/delete-account/sign-in') }}" class="inline-block rounded-lg bg-white text-slate-900 hover:bg-slate-200 px-5 py-2 font-medium">Sign in with Google to delete</a>
            <p class="text-sm text-slate-400">Signed up with Facebook or TikTok in the app? Delete in the app (Profile &rarr; Delete account) or email us.</p>
        @endauth
        <p>You can also delete in the mobile app (Profile &rarr; Delete account) or email <a class="text-sky-300 underline" href="mailto:{{ $support }}?subject=Delete%20my%20ClipPipeline%20account">{{ $support }}</a> from your account's email address.</p>
    </section>
@endsection
