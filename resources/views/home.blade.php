@extends('layouts.app')

@section('title', 'Home')

@section('content')
    {{-- Main content moved from welcome.blade.php --}}
    <section class="relative w-full overflow-hidden bg-background min-h-[80vh] flex items-center">
        <div class="carousel-container w-full">
            <div class="carousel-track flex transition-transform duration-500">
                {{-- Slide 1 --}}
                <div class="carousel-slide w-full min-w-full">
                    <div class="max-w-[1440px] mx-auto px-gutter py-xxl flex flex-col md:flex-row items-center gap-xl">
                        <div class="w-full md:w-[40%] flex flex-col gap-lg z-10">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full glass-panel w-fit font-label-sm text-label-sm text-primary">
                                <span class="material-symbols-outlined text-[14px]">movie</span>
                                Legacy Integration
                            </div>
                            <h1 class="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-surface mb-6 tracking-tight">
                                One upload.<br />
                                <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary-container to-secondary-container">Absolute omnipresence.</span>
                            </h1>
                            <p class="font-body-lg text-body-lg text-on-surface-variant mb-10 max-w-2xl">
                                Clippipeline automatically captures your new TikTok content and deploys it to Instagram Reels and YouTube Shorts without watermarks. Zero friction.
                            </p>
                            <div data-animate="slide-up">
                                @guest
                                    <a href="{{ route('auth.google') }}" class="cta-slide-up">Connect with Google</a>
                                @else
                                    @php
                                        $tiktok = \DB::table('social_accounts')->where('provider','tiktok')->where('user_id', Auth::id())->exists();
                                        $instagram = \DB::table('social_accounts')->where('provider','instagram')->where('user_id', Auth::id())->exists();
                                        $youtube = \DB::table('social_accounts')->where('provider','youtube')->where('user_id', Auth::id())->exists();
                                    @endphp
                                    <div class="flex gap-3">
                                        @if (! $tiktok)
                                            <a href="{{ route('auth.tiktok') }}" class="px-4 py-2 rounded-full bg-white/5 border border-white/10">Connect TikTok Feed</a>
                                        @else
                                            <span class="status-success">✓ TikTok Active</span>
                                        @endif
                                        @if (! $instagram)
                                            <a href="{{ route('auth.meta') }}" class="px-4 py-2 rounded-full bg-white/5 border border-white/10">Connect Instagram Reels</a>
                                        @else
                                            <span class="status-success">✓ Instagram Active</span>
                                        @endif
                                        @if (! $youtube)
                                            <a href="{{ route('auth.youtube') }}" class="px-4 py-2 rounded-full bg-white/5 border border-white/10">Connect YouTube Shorts</a>
                                        @else
                                            <span class="status-success">✓ YouTube Active</span>
                                        @endif
                                    </div>
                                @endguest
                            </div>
                        </div>
                        <div class="w-full md:w-[60%] relative">
                            <img alt="Clippipeline Content Cascade Dashboard UI" class="w-full h-auto rounded-xl object-cover relative z-0 transition-transform duration-700 group-hover:scale-105 opacity-90" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBVPoWM-vMlBpcOSMcnOCba3sM6Eg-mIUjRAXsVDNYlSRSPcWxh16RA096N8mX5wUf9DGjC65MMxkcfh-X8iZAhcBQAu5dB61AcaJEfsa6Ow5hEH8DLFWngel4jhfVDrE7KLCB_I6eWd-6vc7bdlVAQm_pczmwo2a4u3044scgLTArJS22OTe7-IVpd4-6Z8GAxokVxBH0e2onDZyRq978tirpcwt2hIKZv4ihz_4qdalF9WNAJFssvgigWiJsTormeDSoeq__c" />
                        </div>
                    </div>
                </div>
                {{-- Slide 2 --}}
                <div class="carousel-slide w-full min-w-full">
                    <div class="max-w-[1440px] mx-auto px-gutter py-xxl flex flex-col md:flex-row items-center gap-xl">
                        <div class="w-full md:w-[40%] flex flex-col gap-lg z-10">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full glass-panel w-fit font-label-sm text-label-sm text-tertiary">
                                <span class="material-symbols-outlined text-[14px]">analytics</span>
                                Data Consolidation
                            </div>
                            <h1 class="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-background">100% Unified Analytics Overview</h1>
                            <p class="font-body-lg text-body-lg text-on-surface-variant max-w-md">Aggregate engagement velocity, cross-platform metrics, and audience demographics into a single, high-fidelity command center.</p>
                            <div data-animate="slide-up">
                                @guest
                                    <a href="{{ route('auth.google') }}" class="cta-slide-up">Connect with Google</a>
                                @endguest
                            </div>
                        </div>
                        <div class="w-full md:w-[60%] relative">
                            <img alt="Analytics preview" class="w-full h-auto rounded-xl border border-white/10 shadow-2xl shadow-primary-container/10" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDgu_eHS8YMiUf6jm_ow0NY7wP7ZgzQok1y9pl4pZcbKQXy6Qe_2Pjil-n19nF3bgdq31xglvXIUaND9ACu7vuc3jDmCHHbwT_6n7b-CjtyeU2JbkvtskvkaedrekqXL-T3Pqi34ItHipgHN7hvYGgKGFWIxwyn5cs6320v7SF2oK20axCll-D17nGJzWnaGKHfDqzcnTq0Yb73DKIJJrwJVH1iYADvUPQT7NBVs15slwPw6Xqs5APDwDHUe6YOnFiBg4wor-sq" />
                        </div>
                    </div>
                </div>
                {{-- Slide 3 --}}
                <div class="carousel-slide w-full min-w-full">
                    <div class="max-w-[1440px] mx-auto px-gutter py-xxl flex flex-col md:flex-row items-center gap-xl">
                        <div class="w-full md:w-[40%] flex flex-col gap-lg z-10">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full glass-panel w-fit font-label-sm text-label-sm text-tertiary">
                                <span class="material-symbols-outlined text-[14px]">speed</span>
                                Velocity Protocols
                            </div>
                            <h1 class="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-background">85% Faster Upload Speeds</h1>
                            <p class="font-body-lg text-body-lg text-on-surface-variant max-w-md">Bypass standard API throttling. Our proprietary edge network routes your media directly to platform ingestion servers.</p>
                            <div data-animate="slide-up">
                                @guest
                                    <a href="{{ route('auth.google') }}" class="cta-slide-up">Connect with Google</a>
                                @endguest
                            </div>
                        </div>
                        <div class="w-full md:w-[60%] relative">
                            <img alt="Speed preview" class="w-full h-auto rounded-xl border border-white/10 shadow-2xl shadow-tertiary/10" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAKk5fw0UPsHPBUv8-r1__DygFdjR3eSzs39KygMYp226HY1TLdaJcQC29pY3oE-P6B1S82XAMrvXT_1_aOxtwEuK5mJaYOFojTiFE4HAYYAZTP-QKrpWtKCrg-4lYB8bO4HtNoGq81DYbzh1HqsAw0xwOaa1vU-gwKWSDU0WwiZ3wLOw0Bi6HYalyU_nxsQHaFAMbQokysBjwOuVOOexVZKfSJXM7vzBaVjhyhX2PhJfT_1QKCYuwdnEmgqJtEzhgOQP9EEv-G" />
                        </div>
                    </div>
                </div>
                {{-- Slide 4 --}}
                <div class="carousel-slide w-full min-w-full">
                    <div class="max-w-[1440px] mx-auto px-gutter py-xxl flex flex-col md:flex-row items-center gap-xl">
                        <div class="w-full md:w-[40%] flex flex-col gap-lg z-10">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full glass-panel w-fit font-label-sm text-label-sm text-error">
                                <span class="material-symbols-outlined text-[14px]">shield</span>
                                Algorithm Safety
                            </div>
                            <h1 class="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-background">0% Distribution Penalties</h1>
                            <p class="font-body-lg text-body-lg text-on-surface-variant max-w-md">Our verified delivery pipelines strip metadata footprinting, ensuring cross-posting doesn't trigger shadowbans.</p>
                            <div data-animate="slide-up">
                                @guest
                                    <a href="{{ route('auth.google') }}" class="cta-slide-up">Connect with Google</a>
                                @endguest
                            </div>
                        </div>
                        <div class="w-full md:w-[60%] relative">
                            <img alt="Safety preview" class="w-full h-auto rounded-xl border border-white/10 shadow-2xl shadow-error/10" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDxz2hWOhGLCJmjc9DkPVkBfHJSc8oacImdpS59VPrTeTCl_PmGo2gg0XS45bR28kTbaAYS5Q5KnuzM4n2T8v0Na72xOnS8CmTSWY8GI2fwyITTiAS3QfQ_t6YtLofMOKEJZTjuJn8TLfBYe2CP8Mf_nkIlizruZOrxVHgNroJYqtZFnKuZnWBb0jR0f-Oqtd2LJpFFZFOPNbMP963lZN3388ToAXS7YflLOKJSxzzxrV2FcC1SBoDP6KkWFoKgL39heVuLRl0T" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- navigation arrows --}}
        <button class="carousel-arrow left absolute left-4 top-1/2 -translate-y-1/2 bg-white/5 p-3 rounded-full">‹</button>
        <button class="carousel-arrow right absolute right-4 top-1/2 -translate-y-1/2 bg-white/5 p-3 rounded-full">›</button>

        {{-- dots indicator --}}
        <div class="carousel-dots absolute bottom-6 left-1/2 -translate-x-1/2 flex gap-2"></div>
    </section>

    {{-- Keep rest of page sections (pricing/features...) by including them here or via components. --}}
@endsection
