<div class="clippy-dashboard clippy-dashboard--desktop">
    @php
        $performanceBars = $this->getPerformanceBars();
        $recentUploads = $this->getRecentTiktokUploads();
        $axisLabels = $this->getPerformanceAxisLabels();
    @endphp

    @include('filament.partials.dashboard-sidebar')

    <div class="clippy-dashboard__content">
        <header class="clippy-topbar">
            <div class="clippy-topbar__left">
                <button type="button" class="clippy-icon-button clippy-icon-button--mobile" aria-label="Open menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <div class="clippy-search">
                    <span class="clippy-search__icon" aria-hidden="true">⌕</span>
                    <input type="text" placeholder="Search..." />
                </div>
            </div>

            <div class="clippy-topbar__actions">
                <button type="button" class="clippy-topbar__icon" aria-label="Notifications">
                    <span class="clippy-dot"></span>
                    ⌁
                </button>
                <button type="button" class="clippy-topbar__icon" aria-label="Help">?</button>
                <button type="button" class="clippy-new-post">+ New Post</button>
                <button type="button" class="clippy-avatar-button" aria-label="Account">
                    <span>CL</span>
                </button>
            </div>
        </header>

        <main class="clippy-main">
            <section class="clippy-page-header">
                <div>
                    <div class="clippy-kicker">
                        <span class="clippy-kicker__icon" aria-hidden="true">▶</span>
                        <span>TIKTOK INSIGHTS</span>
                    </div>
                    <h2>{{ $this->getTiktokHandle() }}</h2>
                </div>

                <div class="clippy-live-pill">
                    <span></span>
                    Live Sync Active
                </div>
            </section>

            <section class="clippy-stats-grid">
                <article class="clippy-card clippy-card--hero">
                    <div class="clippy-card__header">
                        <p class="clippy-label">Total Views</p>
                        <span class="clippy-card__icon">◉</span>
                    </div>
                    <div class="clippy-stat-value clippy-stat-value--xl">{{ $this->getTotalTiktokViews() }}</div>
                    <div class="clippy-stat-meta">
                        <span class="clippy-stat-badge clippy-stat-badge--orange">↑ {{ $this->getWeeklyTiktokVelocity() }}</span>
                        <span class="clippy-stat-note">vs last 30 days</span>
                    </div>
                </article>

                <article class="clippy-card">
                    <div class="clippy-card__header">
                        <p class="clippy-label">Follower Growth</p>
                        <span class="clippy-card__icon clippy-card__icon--blue">◔</span>
                    </div>
                    <div class="clippy-stat-value">{{ $this->getFollowerGrowth() }}</div>
                    <p class="clippy-stat-note">Followers</p>
                </article>

                <article class="clippy-card">
                    <div class="clippy-card__header">
                        <p class="clippy-label">Engagement Rate</p>
                        <span class="clippy-stat-badge">High</span>
                    </div>
                    <div class="clippy-stat-value">{{ $this->getEngagementRate() }}</div>
                    <div class="clippy-sparkline" aria-hidden="true">
                        <span></span><span></span><span></span><span></span><span></span>
                    </div>
                </article>
            </section>

            <section class="clippy-panel">
                <div class="clippy-panel__header">
                    <div>
                        <h3>Performance Over 30 Days</h3>
                        <p>Daily view velocity and engagement spikes.</p>
                    </div>
                    <div class="clippy-panel__switcher">
                        <button type="button">7D</button>
                        <button type="button" class="is-active">30D</button>
                        <button type="button">90D</button>
                    </div>
                </div>

                <div class="clippy-chart">
                    <div class="clippy-chart__axis clippy-chart__axis--y">
                        <span>50k</span>
                        <span>25k</span>
                        <span>0</span>
                    </div>

                    <div class="clippy-chart__bars" aria-label="TikTok performance chart">
                        @foreach($performanceBars as $bar)
                            <div class="clippy-chart__bar-wrap">
                                <div class="clippy-chart__bar" style="height: {{ $bar }}%;"></div>
                            </div>
                        @endforeach
                    </div>

                    <div class="clippy-chart__axis clippy-chart__axis--x">
                        <span>{{ $axisLabels[0] }}</span>
                        <span>{{ $axisLabels[1] }}</span>
                        <span>{{ $axisLabels[2] }}</span>
                    </div>
                </div>
            </section>

            <section class="clippy-section">
                <div class="clippy-section__header">
                    <h3>Recent Uploads</h3>
                    <a href="{{ route('filament.admin.pages.dashboard') }}">View All</a>
                </div>

                <div class="clippy-list">
                    @forelse($recentUploads as $upload)
                        <article class="clippy-upload-card">
                            <div class="clippy-upload-thumb">
                                @if(($upload['status'] ?? '') === 'Processing')
                                    <span class="clippy-upload-thumb__status">↻</span>
                                @else
                                    <img src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=320&q=80" alt="Thumbnail" />
                                    <span class="clippy-upload-thumb__duration">{{ $upload['duration'] }}</span>
                                @endif
                            </div>

                            <div class="clippy-upload-content">
                                <h4>{{ $upload['title'] }}</h4>
                                <div class="clippy-upload-metrics">
                                    <span>◔ {{ $upload['views'] }}</span>
                                    <span>♥ {{ $upload['likes'] }}</span>
                                </div>

                                @if(($upload['status'] ?? '') === 'Processing')
                                    <div class="clippy-progress">
                                        <div class="clippy-progress__bar" style="width: {{ $upload['progress'] ?? 0 }}%;"></div>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @empty
                        <article class="clippy-upload-card clippy-upload-card--empty">
                            <div class="clippy-upload-content">
                                <h4>No TikTok posts found yet</h4>
                                <p>Seed the dashboard data again to populate this section.</p>
                            </div>
                        </article>
                    @endforelse
                </div>
            </section>
        </main>

        @include('filament.partials.dashboard-nav')
    </div>
</div>
