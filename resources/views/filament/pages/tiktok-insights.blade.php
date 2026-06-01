<div class="clippy-dashboard">
    @php
        $performanceBars = $this->getPerformanceBars();
        $recentUploads = $this->getRecentTiktokUploads();
    @endphp

    <section class="clippy-topbar">
        <button type="button" class="clippy-icon-button" aria-label="Open menu">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <div class="clippy-topbar__title">TikTok Insights</div>

        <button type="button" class="clippy-avatar-button" aria-label="Account">
            <span>CL</span>
        </button>
    </section>

    <main class="clippy-shell">
        <section class="clippy-card clippy-card--hero">
            <div class="clippy-card__header">
                <div>
                    <p class="clippy-label">Views</p>
                </div>
                <span class="clippy-chip clippy-chip--glow">Live</span>
            </div>

            <div class="clippy-hero-metric">
                <h1>{{ $this->getTotalTiktokViews() }}</h1>
                <p><span>↑</span> {{ $this->getWeeklyTiktokVelocity() }} this week</p>
            </div>
        </section>

        <section class="clippy-grid clippy-grid--two">
            <article class="clippy-card clippy-card--metric">
                <div class="clippy-card__header">
                    <p class="clippy-label">Growth</p>
                </div>
                <div class="clippy-metric-value">{{ $this->getFollowerGrowth() }}</div>
                <p class="clippy-metric-caption">Followers</p>
            </article>

            <article class="clippy-card clippy-card--metric clippy-card--metric-alt">
                <div class="clippy-card__header">
                    <p class="clippy-label">Engagement Rate</p>
                    <span class="clippy-chip">High</span>
                </div>
                <div class="clippy-metric-value">{{ $this->getEngagementRate() }}</div>
                <div class="clippy-sparkline" aria-hidden="true">
                    <span></span><span></span><span></span><span></span><span></span>
                </div>
            </article>
        </section>

        <section class="clippy-card clippy-performance">
            <div class="clippy-card__header clippy-performance__header">
                <div>
                    <h2>Performance</h2>
                </div>
                <span class="clippy-label clippy-label--muted">30 Days</span>
            </div>

            <div class="clippy-bar-chart" aria-label="TikTok performance chart">
                @foreach($performanceBars as $bar)
                    <div class="clippy-bar-chart__bar-wrap">
                        <div class="clippy-bar-chart__bar" style="height: {{ $bar }}%;"></div>
                    </div>
                @endforeach
            </div>

            <div class="clippy-bar-chart__axis">
                <span>Oct 1</span>
                <span>Oct 15</span>
                <span>Oct 30</span>
            </div>
        </section>

        <section class="clippy-section">
            <div class="clippy-section__header">
                <h2>Recent Uploads</h2>
                <a href="{{ route('filament.admin.pages.dashboard') }}" class="clippy-link">View all</a>
            </div>

            <div class="clippy-card clippy-list-card">
                @foreach($recentUploads as $upload)
                    <article class="clippy-upload-row">
                        <div class="clippy-upload-thumb">
                            @if(($upload['status'] ?? '') === 'Processing')
                                <span class="clippy-upload-thumb__icon">↻</span>
                            @else
                                <span class="clippy-upload-thumb__duration">{{ $upload['duration'] }}</span>
                            @endif
                        </div>

                        <div class="clippy-upload-content">
                            <h3>{{ $upload['title'] }}</h3>
                            <p>
                                <span>👁 {{ $upload['views'] }}</span>
                                <span>♥ {{ $upload['likes'] }}</span>
                            </p>
                            @if(($upload['status'] ?? '') === 'Processing')
                                <div class="clippy-progress">
                                    <div class="clippy-progress__bar" style="width: {{ $upload['progress'] ?? 0 }}%;"></div>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </main>

    @include('filament.partials.dashboard-nav')
</div>
