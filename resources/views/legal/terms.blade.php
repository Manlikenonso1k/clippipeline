@extends('legal.layout')

@section('title', 'Terms of Service')
@section('updated', 'October 14, 2026')

@php($support = config('clippipeline.support_email'))

@section('content')
    <section class="space-y-3">
        <p>These terms govern your use of ClipPipeline (the website, web dashboard and mobile app). By creating an account or using ClipPipeline you agree to them. If you do not agree, do not use the service.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">The service</h2>
        <p>ClipPipeline lets you upload a video once and publish it to the YouTube, Facebook, Instagram and TikTok accounts you connect, schedule posts, and view analytics for them. Posts are published only when you press Publish or at the time you schedule. Platforms process uploads on their side, so a post can take a few minutes to appear and a platform may reject it.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">Your content and your accounts</h2>
        <ul class="list-disc pl-6 space-y-2">
            <li>You keep ownership of your videos. You give us permission to store, process and send them to the platforms you choose, only to provide the service.</li>
            <li>You must own or have the rights to everything you upload, including music, and you are responsible for what you publish.</li>
            <li>You must follow each platform's rules for the accounts you connect. You can disconnect an account or delete your ClipPipeline account at any time.</li>
            <li>We may pause publishing or suspend an account that breaks these terms, a platform's rules, or the law.</li>
        </ul>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">YouTube</h2>
        <p>ClipPipeline uses YouTube API Services. By using ClipPipeline's YouTube features, you agree to be bound by the <a class="text-sky-300 underline" href="https://www.youtube.com/t/terms">YouTube Terms of Service</a>. Google's handling of your data is described in the <a class="text-sky-300 underline" href="https://policies.google.com/privacy">Google Privacy Policy</a>. Before each YouTube upload you confirm the video follows YouTube's Community Guidelines.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">TikTok</h2>
        <p>When you post to TikTok you choose the privacy and interaction settings for that post, and you agree to TikTok's <a class="text-sky-300 underline" href="https://www.tiktok.com/legal/page/global/music-usage-confirmation/en">Music Usage Confirmation</a>. If you mark a post as branded content you also agree to TikTok's <a class="text-sky-300 underline" href="https://www.tiktok.com/legal/page/global/bc-policy/en">Branded Content Policy</a>. Your use of TikTok is also governed by <a class="text-sky-300 underline" href="https://www.tiktok.com/legal/terms-of-service">TikTok's Terms of Service</a>.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">Facebook and Instagram</h2>
        <p>Publishing to Facebook Pages and Instagram professional accounts uses Meta's APIs and is subject to <a class="text-sky-300 underline" href="https://www.facebook.com/terms.php">Meta's Terms</a>, <a class="text-sky-300 underline" href="https://help.instagram.com/581066165581870">Instagram's Terms of Use</a> and the <a class="text-sky-300 underline" href="https://developers.facebook.com/terms/">Meta Platform Terms</a>. Meta limits how many posts can be published per day; ClipPipeline enforces those limits.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">Plans and billing</h2>
        <p>Paid plans unlock higher monthly limits. Prices and limits are shown before you pay. Monthly plans renew until you cancel.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">Availability and liability</h2>
        <p>We work to keep ClipPipeline running, but the service depends on third-party platforms that can change or stop their APIs. The service is provided "as is". To the extent the law allows, we are not liable for indirect losses, lost revenue, or content a platform removes or rejects.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-2xl font-semibold text-white">Privacy, changes and contact</h2>
        <p>Our <a class="text-sky-300 underline" href="{{ url('/privacy') }}">Privacy Policy</a> explains how we handle your data. We may update these terms and will tell you in the app before material changes apply. Contact: <a class="text-sky-300 underline" href="mailto:{{ $support }}">{{ $support }}</a>.</p>
        <p>ClipPipeline is an independent product. It is not affiliated with, endorsed or sponsored by TikTok, Meta (Facebook, Instagram) or YouTube (Google). Platform names are used only to describe the integrations.</p>
    </section>
@endsection
