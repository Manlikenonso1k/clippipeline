@extends('legal.layout')

@section('title', 'Privacy Policy')
@section('updated', 'October 14, 2026')

@php($support = config('clippipeline.support_email'))

@section('content')
    <section class="space-y-3">
        <p>ClipPipeline ("we", "us") lets creators upload a video once and publish it to the YouTube, Facebook, Instagram and TikTok accounts they connect, and see the results in one analytics view. This policy explains what we store, why, how long we keep it, who we share it with, and how you can delete it. It covers the ClipPipeline website, web dashboard and mobile app.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">What we store</h2>
        <ul class="list-disc pl-6 space-y-2">
            <li><strong class="text-white">Your profile:</strong> name, email address and profile picture from the account you sign in with (Google, Facebook or TikTok), plus your preferences (notifications, auto-sync, default platforms and hashtags).</li>
            <li><strong class="text-white">Videos and post details:</strong> the videos you upload, titles, captions, hashtags, schedule times, the platforms you choose and each platform's publishing status.</li>
            <li><strong class="text-white">Connected-account tokens:</strong> OAuth access and refresh tokens for the accounts you connect. Tokens are encrypted at rest and never shown to anyone.</li>
            <li><strong class="text-white">Platform data:</strong> for each connected account, its id, name or handle, profile picture and follower count; for videos published through ClipPipeline, their ids, links and statistics (views, likes, comments, shares, saves, watch time).</li>
            <li><strong class="text-white">Device and service data:</strong> push-notification tokens for your devices, in-app notifications, billing status, and technical logs needed to run and secure the service.</li>
        </ul>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">Why we use it</h2>
        <ul class="list-disc pl-6 space-y-2">
            <li>To sign you in and keep your account secure.</li>
            <li>To publish or schedule your videos, only when you press Publish or at the time you scheduled.</li>
            <li>To show you analytics for your own accounts and posts.</li>
            <li>To send you notifications about your posts and connections (you can turn these off).</li>
            <li>To enforce plan limits and platform limits, prevent abuse and fix problems.</li>
        </ul>
        <p>We do not sell your data, we do not use it for advertising, and we do not build profiles of you beyond what the service needs.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">Google and YouTube data</h2>
        <p>When you connect YouTube we access your channel id, title and avatar, subscriber count, and the videos you publish through ClipPipeline with their statistics (YouTube Data API and YouTube Analytics API). We upload videos to your channel only when you ask us to. When you sign in with Google we receive your name, email address and profile picture.</p>
        <p>ClipPipeline's use and transfer to any other app of information received from Google APIs will adhere to the <a class="text-sky-300 underline" href="https://developers.google.com/terms/api-services-user-data-policy">Google API Services User Data Policy</a>, including the Limited Use requirements.</p>
        <p>We use Google user data only to provide the features you see. We do not transfer or sell it to advertising platforms, data brokers or resellers, we do not use it for advertising, and no person reads it except with your consent, for security, to comply with the law, or in aggregated form to operate the service.</p>
        <p>By using ClipPipeline's YouTube features you are also using YouTube's services: see the <a class="text-sky-300 underline" href="https://www.youtube.com/t/terms">YouTube Terms of Service</a> and the <a class="text-sky-300 underline" href="https://policies.google.com/privacy">Google Privacy Policy</a>.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">TikTok data</h2>
        <p>When you connect TikTok we receive your open id, display name and avatar, your follower and video counts, the list of your videos with their statistics, and the status of posts you publish through ClipPipeline. We post to TikTok only when you choose the post settings and press Publish. TikTok data is used only to provide ClipPipeline's features and is never sold or shared. See <a class="text-sky-300 underline" href="https://www.tiktok.com/legal/privacy-policy">TikTok's Privacy Policy</a>.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">Meta (Facebook and Instagram) data</h2>
        <p>When you connect Facebook we receive your app-scoped Facebook user id, the Pages you choose to share with their names and Page tokens, and the Instagram professional account linked to your Page (id, username, profile picture, follower count). We use them to publish Reels you ask us to publish and to show insights for those posts. We do not sell, license or purchase Meta Platform Data and do not use it for profiling, advertising or eligibility decisions.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">Sharing</h2>
        <p>We share data only with the platforms you publish to (to publish your post), with service providers that host and run ClipPipeline (hosting, storage, email and push delivery), and when the law requires it. We never share data for advertising.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">Security</h2>
        <p>Data is encrypted in transit (HTTPS). Platform tokens are encrypted at rest. Access to production systems is restricted.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">How long we keep it</h2>
        <ul class="list-disc pl-6 space-y-2">
            <li>Data from a connected account is kept while the account stays connected. We re-check every connection at least daily and keep at most 36 months of daily statistics.</li>
            <li>If a platform revokes our access and you do not reconnect within 30 days, we delete that account's data.</li>
            <li>When you disconnect an account we revoke our access at the platform and delete its tokens, profile data and statistics.</li>
            <li>When you delete your ClipPipeline account we revoke every connection immediately and delete all your data within 7 days. A record of the request without personal data is kept to prove it was completed.</li>
        </ul>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">Revoking access</h2>
        <ul class="list-disc pl-6 space-y-2">
            <li><strong class="text-white">In ClipPipeline:</strong> Connect screen (app) or Connected accounts (web) &rarr; Disconnect. This revokes our access at the platform.</li>
            <li><strong class="text-white">Google / YouTube:</strong> <a class="text-sky-300 underline" href="https://myaccount.google.com/permissions">myaccount.google.com/permissions</a> (also <a class="text-sky-300 underline" href="https://security.google.com/settings/security/permissions">security.google.com/settings/security/permissions</a>).</li>
            <li><strong class="text-white">Facebook:</strong> Settings &amp; privacy &rarr; Settings &rarr; Apps and websites &rarr; ClipPipeline &rarr; Remove.</li>
            <li><strong class="text-white">Instagram:</strong> Settings &rarr; Website permissions / Apps and websites &rarr; ClipPipeline &rarr; Remove.</li>
            <li><strong class="text-white">TikTok:</strong> Profile &rarr; Settings and privacy &rarr; Security &amp; permissions &rarr; Manage app permissions &rarr; ClipPipeline &rarr; Remove access.</li>
        </ul>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">Deleting your data</h2>
        <p>Delete your account in the app (Profile &rarr; Delete account), on the web at <a class="text-sky-300 underline" href="{{ url('/delete-account') }}">{{ url('/delete-account') }}</a>, or by emailing <a class="text-sky-300 underline" href="mailto:{{ $support }}">{{ $support }}</a>. Per-platform instructions are on the <a class="text-sky-300 underline" href="{{ url('/data-deletion') }}">data deletion page</a>.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">Changes and contact</h2>
        <p>If we change how we use your data we will update this page and tell you in the app before the change applies. Questions: <a class="text-sky-300 underline" href="mailto:{{ $support }}">{{ $support }}</a>.</p>
        <p>ClipPipeline is not affiliated with, endorsed or sponsored by TikTok, Meta or YouTube.</p>
    </section>
@endsection
