@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <div class="fixed inset-0 z-0 pointer-events-none">
        <div class="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] rounded-full bg-primary-container opacity-[0.05] blur-[120px]"></div>
        <div class="absolute bottom-[-20%] right-[-10%] w-[50%] h-[50%] rounded-full bg-secondary-container opacity-[0.05] blur-[120px]"></div>
    </div>

    <section class="relative w-full overflow-hidden bg-background min-h-[80vh] flex items-center">
        <div class="carousel-container w-full">
            <div class="carousel-track">
                <div class="carousel-slide w-full max-w-[1440px] mx-auto px-gutter py-xxl flex flex-col md:flex-row items-center gap-xl" id="slide-1">
                    <div class="w-full md:w-[40%] flex flex-col gap-lg z-10">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full glass-panel w-fit font-label-sm text-label-sm text-primary">
                            <span class="material-symbols-outlined text-[14px]">analytics</span>
                            Data Consolidation
                        </div>
                        <h1 class="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-background">
                            100% Unified Analytics Overview
                        </h1>
                        <p class="font-body-lg text-body-lg text-on-surface-variant max-w-md">
                            Aggregate engagement velocity, cross-platform metrics, and audience demographics into a single, high-fidelity command center.
                        </p>
                        <div class="flex gap-4 items-center mt-4">
                            <div class="flex flex-col">
                                <span class="font-headline-md text-headline-md text-white">4.2M</span>
                                <span class="font-label-sm text-label-sm text-on-surface-variant">Events Processed</span>
                            </div>
                            <div class="w-[1px] h-8 bg-white/10"></div>
                            <div class="flex flex-col">
                                <span class="font-headline-md text-headline-md text-tertiary">Real-time</span>
                                <span class="font-label-sm text-label-sm text-on-surface-variant">Sync Latency</span>
                            </div>
                        </div>
                        <button class="mt-8 bg-primary-container text-white px-8 py-4 rounded-full font-label-md text-label-md w-fit hover:bg-primary transition-colors animate-slide-up opacity-0" style="animation-delay: 0.2s;">
                            Explore Unified Dashboard
                        </button>
                    </div>
                    <div class="w-full md:w-[60%] relative">
                        <div class="absolute inset-0 bg-gradient-to-r from-background to-transparent z-10 hidden md:block"></div>
                        <img alt="" class="w-full h-auto rounded-xl border border-white/10 shadow-2xl shadow-primary-container/10" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDgu_eHS8YMiUf6jm_ow0NY7wP7ZgzQok1y9pl4pZcbKQXy6Qe_2Pjil-n19nF3bgdq31xglvXIUaND9ACu7vuc3jDmCHHbwT_6n7b-CjtyeU2JbkvtskvkaedrekqXL-T3Pqi34ItHipgHN7hvYGgKGFWIxwyn5cs6320v7SF2oK20axCll-D17nGJzWnaGKHfDqzcnTq0Yb73DKIJJrwJVH1iYADvUPQT7NBVs15slwPw6Xqs5APDwDHUe6YOnFiBg4wor-sq"/>
                    </div>
                </div>

                <div class="carousel-slide w-full max-w-[1440px] mx-auto px-gutter py-xxl flex flex-col md:flex-row items-center gap-xl" id="slide-2">
                    <div class="w-full md:w-[40%] flex flex-col gap-lg z-10">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full glass-panel w-fit font-label-sm text-label-sm text-tertiary">
                            <span class="material-symbols-outlined text-[14px]">speed</span>
                            Velocity Protocols
                        </div>
                        <h1 class="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-background">
                            85% Faster Upload Speeds
                        </h1>
                        <p class="font-body-lg text-body-lg text-on-surface-variant max-w-md">
                            Bypass standard API throttling. Our proprietary edge network routes your media directly to platform ingestion servers.
                        </p>
                        <div class="flex gap-4 items-center mt-4">
                            <div class="flex flex-col">
                                <span class="font-headline-md text-headline-md text-white">&lt; 2s</span>
                                <span class="font-label-sm text-label-sm text-on-surface-variant">Handshake Time</span>
                            </div>
                            <div class="w-[1px] h-8 bg-white/10"></div>
                            <div class="flex flex-col">
                                <span class="font-headline-md text-headline-md text-tertiary">10Gbps</span>
                                <span class="font-label-sm text-label-sm text-on-surface-variant">Bandwidth Pipe</span>
                            </div>
                        </div>
                        <button class="mt-8 bg-primary-container text-white px-8 py-4 rounded-full font-label-md text-label-md w-fit hover:bg-primary transition-colors animate-slide-up opacity-0" style="animation-delay: 0.2s;">
                            Speed Up My Distribution
                        </button>
                    </div>
                    <div class="w-full md:w-[60%] relative">
                        <div class="absolute inset-0 bg-gradient-to-r from-background to-transparent z-10 hidden md:block"></div>
                        <img alt="" class="w-full h-auto rounded-xl border border-white/10 shadow-2xl shadow-tertiary/10" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAKk5fw0UPsHPBUv8-r1__DygFdjR3eSzs39KygMYp226HY1TLdaJcQC29pY3oE-P6B1S82XAMrvXT_1_aOxtwEuK5mJaYOFojTiFE4HAYYAZTP-QKrpWtKCrg-4lYB8bO4HtNoGq81DYbzh1HqsAw0xwOaa1vU-gwKWSDU0WwiZ3wLOw0Bi6HYalyU_nxsQHaFAMbQokysBjwOuVOOexVZKfSJXM7vzBaVjhyhX2PhJfT_1QKCYuwdnEmgqJtEzhgOQP9EEv-G"/>
                    </div>
                </div>

                <div class="carousel-slide w-full max-w-[1440px] mx-auto px-gutter py-xxl flex flex-col md:flex-row items-center gap-xl" id="slide-3">
                    <div class="w-full md:w-[40%] flex flex-col gap-lg z-10">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full glass-panel w-fit font-label-sm text-label-sm text-error">
                            <span class="material-symbols-outlined text-[14px]">shield</span>
                            Algorithm Safety
                        </div>
                        <h1 class="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-background">
                            0% Distribution Penalties
                        </h1>
                        <p class="font-body-lg text-body-lg text-on-surface-variant max-w-md">
                            Our verified delivery pipelines strip metadata footprinting, ensuring cross-posting doesn't trigger shadowbans.
                        </p>
                        <div class="flex gap-4 items-center mt-4">
                            <div class="flex flex-col">
                                <span class="font-headline-md text-headline-md text-white">Clean</span>
                                <span class="font-label-sm text-label-sm text-on-surface-variant">Hash Signature</span>
                            </div>
                            <div class="w-[1px] h-8 bg-white/10"></div>
                            <div class="flex flex-col">
                                <span class="font-headline-md text-headline-md text-tertiary">Verified</span>
                                <span class="font-label-sm text-label-sm text-on-surface-variant">API Endpoints</span>
                            </div>
                        </div>
                        <button class="mt-8 bg-primary-container text-white px-8 py-4 rounded-full font-label-md text-label-md w-fit hover:bg-primary transition-colors animate-slide-up opacity-0" style="animation-delay: 0.2s;">
                            Deploy Clean Content
                        </button>
                    </div>
                    <div class="w-full md:w-[60%] relative">
                        <div class="absolute inset-0 bg-gradient-to-r from-background to-transparent z-10 hidden md:block"></div>
                        <img alt="" class="w-full h-auto rounded-xl border border-white/10 shadow-2xl shadow-error/10" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDxz2hWOhGLCJmjc9DkPVkBfHJSc8oacImdpS59VPrTeTCl_PmGo2gg0XS45bR28kTbaAYS5Q5KnuzM4n2T8v0Na72xOnS8CmTSWY8GI2fwyITTiAS3QfQ_t6YtLofMOKEJZTjuJn8TLfBYe2CP8Mf_nkIlizruZOrxVHgNroJYqtZFnKuZnWBb0jR0f-Oqtd2LJpFFZFOPNbMP963lZN3388ToAXS7YflLOKJSxzzxrV2FcC1SBoDP6KkWFoKgL39heVuLRl0T"/>
                    </div>
                </div>

                <div class="carousel-slide w-full max-w-[1440px] mx-auto px-gutter py-xxl flex flex-col md:flex-row items-center gap-xl" id="slide-4">
                    <div class="w-full md:w-[40%] flex flex-col gap-lg z-10">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full glass-panel w-fit font-label-sm text-label-sm text-secondary-fixed">
                            <span class="material-symbols-outlined text-[14px]">rocket_launch</span>
                            Scale Multipliers
                        </div>
                        <h1 class="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-background">
                            300% Short-Form Multiplier
                        </h1>
                        <p class="font-body-lg text-body-lg text-on-surface-variant max-w-md">
                            Take one source node and cascade it instantly across TikTok, Instagram Reels, and YouTube Shorts simultaneously.
                        </p>
                        <div class="flex gap-4 items-center mt-4">
                            <div class="flex flex-col">
                                <span class="font-headline-md text-headline-md text-white">1 to N</span>
                                <span class="font-label-sm text-label-sm text-on-surface-variant">Fan-out Ratio</span>
                            </div>
                            <div class="w-[1px] h-8 bg-white/10"></div>
                            <div class="flex flex-col">
                                <span class="font-headline-md text-headline-md text-tertiary">Automated</span>
                                <span class="font-label-sm text-label-sm text-on-surface-variant">Format Adaptation</span>
                            </div>
                        </div>
                        <button class="mt-8 bg-primary-container text-white px-8 py-4 rounded-full font-label-md text-label-md w-fit hover:bg-primary transition-colors animate-slide-up opacity-0" style="animation-delay: 0.2s;">
                            Scale My Footprint
                        </button>
                    </div>
                    <div class="w-full md:w-[60%] relative">
                        <div class="absolute inset-0 bg-gradient-to-r from-background to-transparent z-10 hidden md:block"></div>
                        <img alt="" class="w-full h-auto rounded-xl border border-white/10 shadow-2xl shadow-secondary-fixed/10" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBS23rm9XafQPgzatfFv-vxFVRFxt4LMUPBrqGbtNV-zt2V2y481jbFrHg50QPGxBAH7JDpQScLQtIRvR-lH87GeJ-HfnjlQYnVDrffpGXiRLQpncWT2ON62z6eFFy1GqbJxShrRyfuqu7Bwm0L-Dzl-ZwLG_b9zC69x2uE1sTpceeq_5YUchUQyBAt7dgA4VJrVtjRXol6x-5gO7TjcbaTM4umLn4gTD2sUKYTPrrpB04MN8u-g7tjQ2axYRSGo1RGo8K3NRAM"/>
                    </div>
                </div>
            </div>
        </div>

        <button class="carousel-arrow left absolute left-4 top-1/2 -translate-y-1/2" aria-label="Previous slide">&lt;</button>
        <button class="carousel-arrow right absolute right-4 top-1/2 -translate-y-1/2" aria-label="Next slide">&gt;</button>

        <div class="carousel-dots absolute bottom-6 left-1/2 -translate-x-1/2 flex gap-2"></div>
    </section>

    <section class="pt-xl" id="features">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-bento-gap">
            <div class="glass-panel bento-card rounded-[16px] p-[24px] flex flex-col items-start gap-4 h-full">
                <div class="w-12 h-12 rounded-full bg-surface-container flex items-center justify-center border border-white/5 text-primary">
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">timer</span>
                </div>
                <h3 class="font-headline-md text-headline-md text-on-surface">Instant Ingestion</h3>
                <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
                    Checks your TikTok profile every 10 minutes. The moment you post, our engine begins the extraction process, ensuring minimal delay between platforms.
                </p>
            </div>
            <div class="glass-panel bento-card rounded-[16px] p-[24px] flex flex-col items-start gap-4 h-full">
                <div class="w-12 h-12 rounded-full bg-surface-container flex items-center justify-center border border-white/5 text-tertiary">
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">water_drop</span>
                </div>
                <h3 class="font-headline-md text-headline-md text-on-surface">Watermark Stripping</h3>
                <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
                    Crisp, raw HD audio and video delivery. Our proprietary algorithm removes the native platform watermarks without compromising the original quality.
                </p>
            </div>
            <div class="glass-panel bento-card rounded-[16px] p-[24px] flex flex-col items-start gap-4 h-full">
                <div class="w-12 h-12 rounded-full bg-surface-container flex items-center justify-center border border-white/5 text-secondary">
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">smart_display</span>
                </div>
                <h3 class="font-headline-md text-headline-md text-on-surface">Native Short Form</h3>
                <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
                    Automatically appends #Shorts for YouTube and structures Meta container requests for Reels, optimizing metadata for maximum algorithmic reach.
                </p>
            </div>
        </div>
    </section>

    <section class="pt-xxl flex flex-col items-center" id="pricing">
        <h2 class="font-headline-xl-mobile md:font-headline-xl text-headline-xl-mobile md:text-headline-xl text-on-surface mb-12 text-center">Simple, Scalable Pricing</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-bento-gap w-full max-w-5xl items-center">
            <div class="glass-panel rounded-[16px] p-[32px] flex flex-col h-[90%] md:h-[400px]">
                <h3 class="font-headline-md text-headline-md text-on-surface mb-2">Starter</h3>
                <div class="text-[32px] font-bold text-on-surface mb-6">₦0 <span class="font-body-md text-on-surface-variant font-normal">/ forever</span></div>
                <ul class="flex flex-col gap-3 mb-auto text-on-surface-variant font-body-md">
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-on-surface">check</span> 10 posts free</li>
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-on-surface">check</span> Standard speed</li>
                </ul>
                <a class="mt-8 w-full py-3 rounded-full text-center font-label-md text-label-md text-on-surface bg-white/5 border border-white/10 hover:bg-white/10 transition-colors" href="#">
                    Get Started
                </a>
            </div>
            <div class="glass-panel rounded-[16px] p-[32px] flex flex-col relative border-primary-container/50 bg-primary-container/5 shadow-[0_0_30px_rgba(255,107,53,0.1)] h-full md:h-[450px] transform md:-translate-y-4">
                <div class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-primary-container text-white px-3 py-1 rounded-full font-label-sm text-[10px] uppercase tracking-wider">
                    Most Popular
                </div>
                <h3 class="font-headline-md text-headline-md text-on-surface mb-2">Creator Pro</h3>
                <div class="text-[32px] font-bold text-on-surface mb-6">₦3,000 <span class="font-body-md text-on-surface-variant font-normal">/ month</span></div>
                <ul class="flex flex-col gap-3 mb-auto text-on-surface-variant font-body-md">
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-primary-container">check</span> 20 automated posts</li>
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-primary-container">check</span> Priority queue</li>
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-primary-container">check</span> Advanced analytics</li>
                </ul>
                <a class="mt-8 w-full py-3 rounded-full text-center font-label-md text-label-md text-white bg-primary-container hover:bg-primary transition-colors" href="#">
                    Upgrade to Pro
                </a>
            </div>
            <div class="glass-panel rounded-[16px] p-[32px] flex flex-col h-[90%] md:h-[400px]">
                <h3 class="font-headline-md text-headline-md text-on-surface mb-2">Lifetime Access</h3>
                <div class="text-[32px] font-bold text-on-surface mb-6">₦18,000 <span class="font-body-md text-on-surface-variant font-normal">/ one-time</span></div>
                <ul class="flex flex-col gap-3 mb-auto text-on-surface-variant font-body-md">
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-on-surface">check</span> Unlimited posts</li>
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-on-surface">check</span> Permanent access</li>
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-on-surface">check</span> All future updates</li>
                </ul>
                <a class="mt-8 w-full py-3 rounded-full text-center font-label-md text-label-md text-on-surface bg-surface-container-highest border border-white/20 hover:bg-surface-bright transition-colors shadow-inner" href="#">
                    Claim Lifetime Access
                </a>
            </div>
        </div>
    </section>
@endsection
