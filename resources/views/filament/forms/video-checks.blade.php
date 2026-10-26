@php
    $tiktokDefaultMax = (int) $tiktokMax;
@endphp
<div
    x-data="{
        src: @js($src),
        sizeMb: @js($sizeMb),
        dur: null,
        probe() {
            this.dur = null;
            if (! this.src) { return; }
            const v = document.createElement('video');
            v.preload = 'metadata';
            v.onloadedmetadata = () => { this.dur = v.duration; };
            v.src = this.src;
        },
        platforms() { return ($wire.data && $wire.data.platforms) || []; },
        ttMax() { return Number(($wire.tiktokCreator && $wire.tiktokCreator.max_video_post_duration_sec) || {{ $tiktokDefaultMax }}) || 0; },
        warnings() {
            const out = [];
            const p = this.platforms();
            if (this.dur !== null && p.includes('tiktok') && this.ttMax() > 0 && this.dur > this.ttMax()) {
                out.push({ k: 'tiktok', t: 'TikTok: this video is ' + Math.round(this.dur) + ' s but your TikTok account allows at most ' + this.ttMax() + ' s. It will be rejected.' });
            }
            if (this.dur !== null && p.includes('youtube') && this.dur > 180) {
                out.push({ k: 'youtube', t: 'YouTube: videos over 180 s are not classified as Shorts and may publish as regular videos.' });
            }
            if (this.sizeMb !== null && p.includes('instagram') && this.sizeMb > 300) {
                out.push({ k: 'instagram', t: 'Instagram: Reels must be 300 MB or smaller (this file is ' + Math.round(this.sizeMb) + ' MB). It will be rejected.' });
            }
            return out;
        },
    }"
    x-init="probe()"
    data-video-checks
    wire:key="video-checks-{{ md5((string) $src) }}"
    style="display: flex; flex-direction: column; gap: 0.5rem;"
>
    <template x-for="w in warnings()" :key="w.k">
        <div role="alert" :data-warning="w.k" style="padding: 0.6rem 0.8rem; border-radius: 0.5rem; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.5); font-size: 0.875rem;" x-text="w.t"></div>
    </template>
    <noscript>
        <p style="font-size: 0.8rem;">Limits: YouTube Shorts up to 180 s, Instagram Reels up to 300 MB, TikTok per your account's maximum.</p>
    </noscript>
</div>
