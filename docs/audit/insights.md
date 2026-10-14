# Audit P4 - Insights (mobile `src/app/(tabs)/insights.tsx`)

Web equivalent: Filament `Dashboard` page (period Select feeding all widgets) = AnalyticsOverview, ViewsTrendChart (platform filter), PlatformBreakdown, TopPostsTable. `TiktokInsights` page is separate and reads the DB directly, not AnalyticsService (not the same numbers).
Guest mode: hooks are disabled (no token) and return `placeholderData` from `src/api/demo.ts` (demo numbers, by design). Logged-in: real API only (`enabled: !!token`, `keepPreviousData`).

| # | Element | Mode | Should do | Now (file:line) | Mobile status | Web equivalent / status | Fix needed |
|---|---|---|---|---|---|---|---|
| 1 | Notification bell | both | Open notifications (or sign-in sheet in guest) | `<Pressable style={s.bell}>` no onPress, insights.tsx:240 | dead | none (Filament has built-in DB notifications bell if enabled) | Wire onPress: guest -> "Sign in to do this" sheet; logged-in -> notifications sheet/route (or remove from this tab) |
| 2 | Avatar | both | Open profile/account | Plain `View`, not pressable, insights.tsx:243 | dead (non-interactive) | Filament user menu - works | Make pressable -> /profile (logged-in) or sign-in sheet (guest) |
| 3 | Period button (calendar + label) | both | Toggle period popover | `setPeriodOpen` toggle, insights.tsx:261 | works | Dashboard `Select::make('period')` Dashboard.php:18 - works | none |
| 4 | Period option (30/7/90 days) | both | Set period, refetch 4 queries | `setPeriod` insights.tsx:279 -> `periodDef.api` in all hooks | works (guest: demoX(period)) | Same periods via Select - works | none. Note default is 30d while contract default is 7d (fine, always sent). Popover has no outside-tap dismiss |
| 5 | Share/export button | both | Share/export the report (RN Share of summary text, or CSV) ; guest -> sign-in sheet | `<Pressable style={s.shareBtn}>` no onPress, insights.tsx:266 | dead | none: no export/share on Dashboard | Guest: sign-in sheet. Logged-in: `Share.share` of KPIs+range from current query data (no new endpoint). Web: optional Filament export action |
| 6 | Platform chips x5 (All/YouTube/Facebook/Instagram/TikTok) | both | Filter KPIs, chart, split | `setPlatform` insights.tsx:308; drives `useTimeseries(period, platform)`; KPIs from `/analytics/platforms` row | works | ViewsTrendChart `$filter` (platform) ViewsTrendChart.php:21 - works but only filters the chart, not KPI/other widgets | none mobile. Web: filter does not scope stats/table (parity gap, minor) |
| 7 | KPI cards x4 (views/likes/comments/shares) + sparklines | both | Display totals + change % | Not pressable; totals from `ov.totals` (all) or platform row; sparks from timeseries | works (display only) | AnalyticsOverview stats - works | Sparkline only has views/likes? verify each series uses own metric; no action needed |
| 8 | Cross-channel reach chart + growth badge | both | Show views trend, delta | Not interactive (no scrub/tooltip), insights.tsx:354+; delta=`change.views` :184 | works (display) | ViewsTrendChart - works | Optional: touch tooltip (UI frozen, skip) |
| 9 | TikTok overlay curve | both | Show TikTok share of views | Synthetic: `series * ttShare` (insights.tsx:156-160), not a real per-platform series | demo-data-when-logged-in (derived/fabricated curve) | n/a (web uses real filter) | Replace with real `GET /analytics/timeseries?platform=tiktok` second query, or hide overlay |
| 10 | Platform split bars + legend (TT/YT/IG/FB %) | both | Share of views per platform | From `/analytics/platforms` insights.tsx:148; legend not pressable | works | PlatformBreakdown widget - works | none |
| 11 | "By Views" sort header | both | Change sort metric (views/likes/engagement) | `<Pressable style={s.legendHead}>` no onPress, insights.tsx:523. `/analytics/top-posts` has no sort param (ranked by views) | dead | TopPostsTable has no sort control either | Either make non-pressable, or client-side sort of the 3 rows via small sheet; backend sort param optional |
| 12 | Top clip rows (x up to 3) | both | Open post detail | `router.push('/post/[id]')` insights.tsx:547 | works (guest: demo post id -> demo detail) | TopPostsTable rows - check row link to post resource | Web: confirm row URL action |
| 13 | "View Full Video Breakdown" CTA | both | Go to posts list | `router.push('/posts')` insights.tsx:594 | works | none | Guest: lands on demo posts, ok |
| 14 | Pull to refresh | both | Refetch | `useRefresh([...4 queries])` insights.tsx:133 | works (guest: refetch no-ops, disabled) | n/a | none |
| 15 | Auto refresh | logged-in | Poll | overview refetchInterval 60s hooks.ts:149 only; other 3 queries not polled | works (partial) | Filament widgets poll? not set | Optional: invalidate on SSE already (`['analytics']`) - ok |

Counts: works 10 (3,4,6,7,8,10,12,13,14,15), dead 3 (1,2,5)+ dead 11 = 4 dead + 1 non-interactive avatar (counted dead => 5 dead), demo-data-when-logged-in 1 (9), missing-on-web 2 (5 export, 11 sort), broken 0.
(Totals: works 10, dead 5, demo/derived 1, broken 0; of the 15 rows.)

## States & data source

Endpoints used (all via AnalyticsService, same as Filament widgets; hooks.ts:142-183):
- KPIs (All platforms) + change: `GET /analytics/overview?period=` -> AnalyticsService::overview (web: AnalyticsOverview widget)
- Platform split + per-platform KPIs: `GET /analytics/platforms?period=` -> platforms() (web: PlatformBreakdown)
- Reach chart + sparklines: `GET /analytics/timeseries?period=&platform=` -> timeseries() (web: ViewsTrendChart)
- Top clips: `GET /analytics/top-posts?period=&limit=3` -> topPosts() (web: TopPostsTable, limit 10)
Numbers match web for same user/period (widgets call the service directly). Exception: TiktokInsights page queries PostAnalytic directly -> can diverge; should be moved to AnalyticsService or hidden.

States:
- Loading: Skeletons on KPI value, chart, top posts (insights.tsx:141, 348, 531). Good.
- Error: InlineMessage "Couldn't load data" only for chart (346) and top posts (529), and only when no cached data. KPI/overview/platforms errors are silent -> shows zeros. Fix: surface overviewQ/platformsQ error with retry.
- Empty: top posts empty message (guest/logged-in variants); chart flat line when no views; KPIs zero. No explicit "connect an account" CTA when user has no social accounts - add nudge using existing InlineMessage.
- Guest: all data is demo (`demoOverview/demoPlatforms/demoTimeseries/demoTopPosts`), no sign-in prompt anywhere; account-only actions (bell, avatar, share) need the "Sign in to do this" sheet.
- Logged-in: no demo leakage in hooks (placeholder = keepPreviousData); only the synthetic TikTok overlay curve (#9) is not real API data.
- Dead patterns found: 4 Pressables without onPress (insights.tsx:240, 266, 523 + non-pressable avatar). No TODO/FIXME/console.log/Alert "coming soon".

## Top fixes
1. Share/export button (266): Share.share summary; guest -> sign-in sheet.
2. Bell (240) + avatar (243): wire to notifications / profile, guest -> sign-in sheet.
3. "By Views" (523): wire client-side sort sheet or de-pressable.
4. Replace synthetic TikTok overlay with real `/analytics/timeseries?platform=tiktok`.
5. Surface overview/platforms errors + retry; make web TiktokInsights use AnalyticsService (or hide it).
