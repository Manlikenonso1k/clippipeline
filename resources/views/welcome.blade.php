<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Clippipeline | Absolute Omnipresence</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#ffb59d",
                        "on-surface": "#e2e2e4",
                        outline: "#a98a80",
                        "primary-fixed-dim": "#ffb59d",
                        "surface-dim": "#111415",
                        "on-tertiary-container": "#003744",
                        "on-background": "#e2e2e4",
                        error: "#ffb4ab",
                        "surface-container-high": "#282a2c",
                        "surface-container-lowest": "#0c0e10",
                        "surface-bright": "#37393b",
                        "error-container": "#93000a",
                        "on-error-container": "#ffdad6",
                        "inverse-surface": "#e2e2e4",
                        "on-primary-fixed": "#390c00",
                        "primary-container": "#ff6b35",
                        "on-secondary-container": "#98b8e0",
                        "secondary-fixed": "#d1e4ff",
                        tertiary: "#59d5fb",
                        "on-primary-container": "#5f1900",
                        "tertiary-fixed": "#b5ebff",
                        "surface-container-low": "#1a1c1d",
                        "on-tertiary-fixed": "#001f28",
                        "on-primary-fixed-variant": "#832600",
                        "on-primary": "#5d1900",
                        "outline-variant": "#594139",
                        "on-surface-variant": "#e1bfb5",
                        secondary: "#a9c9f2",
                        "inverse-primary": "#ab3500",
                        surface: "#111415",
                        "on-tertiary": "#003543",
                        "tertiary-container": "#00a7cb",
                        "on-secondary-fixed": "#001d36",
                        background: "#111415",
                        "primary-fixed": "#ffdbd0",
                        "surface-container-highest": "#333537",
                        "on-error": "#690005",
                        "secondary-fixed-dim": "#a9c9f2",
                        "surface-variant": "#333537",
                        "inverse-on-surface": "#2f3132",
                        "secondary-container": "#27496c",
                        "surface-tint": "#ffb59d",
                        "on-secondary-fixed-variant": "#27496c",
                        "tertiary-fixed-dim": "#59d5fb",
                        "on-secondary": "#0b3254",
                        "surface-container": "#1e2021",
                        "on-tertiary-fixed-variant": "#004e60"
                    },
                    borderRadius: {
                        DEFAULT: "0.25rem",
                        lg: "0.5rem",
                        xl: "0.75rem",
                        full: "9999px"
                    },
                    spacing: {
                        unit: "4px",
                        gutter: "24px",
                        xxl: "80px",
                        "bento-gap": "16px",
                        xs: "4px",
                        sm: "8px",
                        margin: "32px",
                        xl: "40px",
                        lg: "24px",
                        md: "16px"
                    },
                    fontFamily: {
                        "headline-xl": ["Inter"],
                        "headline-md": ["Inter"],
                        "body-lg": ["Inter"],
                        "display-lg-mobile": ["Inter"],
                        "body-md": ["Inter"],
                        "label-md": ["Inter"],
                        "label-sm": ["Inter"],
                        "display-lg": ["Inter"],
                        "headline-xl-mobile": ["Inter"]
                    },
                    fontSize: {
                        "headline-xl": ["40px", { lineHeight: "1.2", letterSpacing: "-0.02em", fontWeight: "600" }],
                        "headline-md": ["24px", { lineHeight: "1.3", letterSpacing: "-0.01em", fontWeight: "600" }],
                        "body-lg": ["18px", { lineHeight: "1.6", letterSpacing: "0em", fontWeight: "400" }],
                        "display-lg-mobile": ["40px", { lineHeight: "1.1", letterSpacing: "-0.03em", fontWeight: "700" }],
                        "body-md": ["16px", { lineHeight: "1.6", letterSpacing: "0em", fontWeight: "400" }],
                        "label-md": ["14px", { lineHeight: "1.2", letterSpacing: "0.02em", fontWeight: "500" }],
                        "label-sm": ["12px", { lineHeight: "1.2", letterSpacing: "0.05em", fontWeight: "600" }],
                        "display-lg": ["64px", { lineHeight: "1.1", letterSpacing: "-0.04em", fontWeight: "700" }],
                        "headline-xl-mobile": ["32px", { lineHeight: "1.2", letterSpacing: "-0.02em", fontWeight: "600" }]
                    }
                }
            }
        };
    </script>
    <style>
        .glass-panel {
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .glass-panel:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
        }
        .bento-card {
            transition: all 0.3s ease;
        }
        .bento-card:hover {
            transform: scale(1.02);
            box-shadow: 0 0 20px rgba(255, 107, 53, 0.1);
        }
    </style>
</head>
<body class="bg-background text-on-surface font-body-md antialiased min-h-screen flex flex-col relative overflow-x-hidden">
    <div class="fixed inset-0 z-0 pointer-events-none">
        <div class="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] rounded-full bg-primary-container opacity-[0.05] blur-[120px]"></div>
        <div class="absolute bottom-[-20%] right-[-10%] w-[50%] h-[50%] rounded-full bg-secondary-container opacity-[0.05] blur-[120px]"></div>
    </div>

    <nav class="fixed top-0 w-full z-50 bg-surface/30 backdrop-blur-xl border-b border-white/10 transition-all duration-300">
        <div class="flex justify-between items-center h-16 px-gutter max-w-[1440px] mx-auto">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary-container" style="font-variation-settings: 'FILL' 1;">movie</span>
                <span class="font-headline-md text-headline-md tracking-tighter text-on-surface">clippipeline</span>
            </div>
            <div class="hidden md:flex items-center gap-8">
                <a class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors duration-200" href="#features">Features</a>
                <a class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors duration-200" href="#pricing">Pricing</a>
                <a class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors duration-200" href="#">System Status</a>
            </div>
            <div>
                @auth
                    <a class="px-6 py-2 rounded-full font-label-md text-label-md text-white bg-white/5 border border-white/10 hover:bg-white/10 transition-all hover:scale-95 active:opacity-80" href="{{ route('integrations.index') }}">Dashboard</a>
                @else
                    <a class="px-6 py-2 rounded-full font-label-md text-label-md text-white bg-white/5 border border-white/10 hover:bg-white/10 transition-all hover:scale-95 active:opacity-80" href="{{ route('auth.redirect', ['provider' => 'google']) }}">Sign in</a>
                @endauth
            </div>
        </div>
    </nav>

    <main class="flex-grow z-10 pt-[120px] pb-xxl px-gutter max-w-[1440px] mx-auto w-full flex flex-col gap-xxl">
        @if (session('status'))
            <div class="glass-panel rounded-xl p-4 text-sm border-primary-container/30 text-on-surface max-w-2xl mx-auto w-full">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="glass-panel rounded-xl p-4 text-sm border-error/30 text-error max-w-2xl mx-auto w-full">
                {{ $errors->first() }}
            </div>
        @endif

        @if (Auth::check() && data_get(Auth::user(), 'settings.billing.flagged'))
            <div class="glass-panel rounded-xl p-4 text-sm border-primary-container/30 text-on-surface max-w-2xl mx-auto w-full">
                It looks like your account was flagged for exceeding the free plan limits. Please consider upgrading to <strong>Creator Pro</strong> to continue automatic posting.
                <a class="ml-3 underline font-semibold" href="{{ route('billing.select', ['plan' => 'creator_pro']) }}">Upgrade to Creator Pro</a>
            </div>
        @endif

        <section class="flex flex-col items-center text-center max-w-4xl mx-auto pt-xl">
            <h1 class="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-on-surface mb-6 tracking-tight">
                One upload.<br />
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary-container to-secondary-container">Absolute omnipresence.</span>
            </h1>
            <p class="font-body-lg text-body-lg text-on-surface-variant mb-10 max-w-2xl">
                Clippipeline automatically captures your new TikTok content and deploys it to Instagram Reels and YouTube Shorts without watermarks. Zero friction.
            </p>
            <a class="inline-flex items-center justify-center px-8 py-4 rounded-full font-label-md text-label-md text-white bg-gradient-to-r from-primary-container to-[#2B4C6F] hover:opacity-90 transition-all transform hover:scale-105 mb-16 shadow-[0_0_30px_rgba(255,107,53,0.3)]" href="{{ route('auth.redirect', ['provider' => 'google']) }}">
                Connect TikTok Free
                <span class="material-symbols-outlined ml-2 text-[18px]">arrow_forward</span>
            </a>
            <div class="w-full max-w-5xl relative group rounded-2xl overflow-hidden glass-panel p-2">
                <div class="absolute inset-0 bg-gradient-to-b from-transparent to-surface-dim/80 pointer-events-none z-10"></div>
                <img alt="Clippipeline Content Cascade Dashboard UI" class="w-full h-auto rounded-xl object-cover relative z-0 transition-transform duration-700 group-hover:scale-105 opacity-90" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBVPoWM-vMlBpcOSMcnOCba3sM6Eg-mIUjRAXsVDNYlSRSPcWxh16RA096N8mX5wUf9DGjC65MMxkcfh-X8iZAhcBQAu5dB61AcaJEfsa6Ow5hEH8DLFWngel4jhfVDrE7KLCB_I6eWd-6vc7bdlVAQm_pczmwo2a4u3044scgLTArJS22OTe7-IVpd4-6Z8GAxokVxBH0e2onDZyRq978tirpcwt2hIKZv4ihz_4qdalF9WNAJFssvgigWiJsTormeDSoeq__c" />
            </div>
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
                    <form class="mt-8" method="POST" action="{{ route('billing.select', ['plan' => 'starter']) }}">
                        @csrf
                        <button class="w-full py-3 rounded-full text-center font-label-md text-label-md text-on-surface bg-white/5 border border-white/10 hover:bg-white/10 transition-colors" type="submit">
                            Get Started
                        </button>
                    </form>
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
                    <div class="mt-8 grid grid-cols-1 gap-2 w-full">
                        <form method="POST" action="{{ route('billing.checkout', ['plan' => 'creator_pro', 'gateway' => 'paystack']) }}">
                            @csrf
                            <button class="w-full py-3 rounded-full text-center font-label-md text-label-md text-white bg-primary-container hover:bg-primary transition-colors" type="submit">
                                Pay with Paystack
                            </button>
                        </form>
                        <form method="POST" action="{{ route('billing.checkout', ['plan' => 'creator_pro', 'gateway' => 'flutterwave']) }}">
                            @csrf
                            <button class="w-full py-3 rounded-full text-center font-label-md text-label-md text-on-surface bg-white/10 border border-white/20 hover:bg-white/15 transition-colors" type="submit">
                                Pay with Flutterwave
                            </button>
                        </form>
                        <form method="POST" action="{{ route('billing.checkout', ['plan' => 'creator_pro', 'gateway' => 'tgipay']) }}">
                            @csrf
                            <button class="w-full py-3 rounded-full text-center font-label-md text-label-md text-on-surface bg-surface-container-highest border border-white/20 hover:bg-surface-bright transition-colors" type="submit">
                                Pay with TgiPay
                            </button>
                        </form>
                    </div>
                </div>

                <div class="glass-panel rounded-[16px] p-[32px] flex flex-col h-[90%] md:h-[400px]">
                    <h3 class="font-headline-md text-headline-md text-on-surface mb-2">Lifetime Access</h3>
                    <div class="text-[32px] font-bold text-on-surface mb-6">₦18,000 <span class="font-body-md text-on-surface-variant font-normal">/ one-time</span></div>
                    <ul class="flex flex-col gap-3 mb-auto text-on-surface-variant font-body-md">
                        <li class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-on-surface">check</span> Unlimited posts</li>
                        <li class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-on-surface">check</span> Permanent access</li>
                        <li class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-on-surface">check</span> All future updates</li>
                    </ul>
                    <div class="mt-8 grid grid-cols-1 gap-2 w-full">
                        <form method="POST" action="{{ route('billing.checkout', ['plan' => 'lifetime', 'gateway' => 'paystack']) }}">
                            @csrf
                            <button class="w-full py-3 rounded-full text-center font-label-md text-label-md text-on-surface bg-surface-container-highest border border-white/20 hover:bg-surface-bright transition-colors shadow-inner" type="submit">
                                Lifetime via Paystack
                            </button>
                        </form>
                        <form method="POST" action="{{ route('billing.checkout', ['plan' => 'lifetime', 'gateway' => 'flutterwave']) }}">
                            @csrf
                            <button class="w-full py-3 rounded-full text-center font-label-md text-label-md text-on-surface bg-white/10 border border-white/20 hover:bg-white/15 transition-colors" type="submit">
                                Lifetime via Flutterwave
                            </button>
                        </form>
                        <form method="POST" action="{{ route('billing.checkout', ['plan' => 'lifetime', 'gateway' => 'tgipay']) }}">
                            @csrf
                            <button class="w-full py-3 rounded-full text-center font-label-md text-label-md text-on-surface bg-surface-container border border-white/20 hover:bg-surface-container-high transition-colors" type="submit">
                                Lifetime via TgiPay
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="w-full py-xl bg-surface border-t border-white/5 mt-auto z-10">
        <div class="flex flex-col md:flex-row justify-between items-center px-margin max-w-[1440px] mx-auto gap-8">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-on-surface-variant" style="font-variation-settings: 'FILL' 1;">movie</span>
                <span class="font-headline-md text-headline-md text-on-surface tracking-tighter">clippipeline</span>
            </div>
            <div class="flex flex-wrap justify-center gap-6">
                <a class="font-label-sm text-label-sm text-on-surface-variant hover:text-primary transition-colors" href="#">Privacy</a>
                <a class="font-label-sm text-label-sm text-on-surface-variant hover:text-primary transition-colors" href="#">Terms</a>
                <a class="font-label-sm text-label-sm text-on-surface-variant hover:text-primary transition-colors" href="#">Support</a>
            </div>
            <div class="font-label-sm text-label-sm text-on-surface-variant">
                © {{ now()->year }} clippipeline. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        window.addEventListener('scroll', () => {
            const nav = document.querySelector('nav');
            if (window.scrollY > 20) {
                nav.classList.add('shadow-lg');
                nav.classList.replace('bg-surface/30', 'bg-surface/80');
            } else {
                nav.classList.remove('shadow-lg');
                nav.classList.replace('bg-surface/80', 'bg-surface/30');
            }
        });
    </script>
</body>
</html>
