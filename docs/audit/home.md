# Home audit (mobile `src/app/(tabs)/dashboard.tsx` vs Filament Dashboard)

Mobile paths are relative to clippipeline_mobile. Guest = no token (demo placeholderData from `src/api/demo.ts`, queries disabled).

| # | Element | Mode | Should do | Now (file:line) | Mobile status | Web equivalent / status | Fix needed |
|---|---|---|---|---|---|---|---|
| 1 | Notification bell | Guest + logged-in | Open notifications list (or sheet); guest: "Sign in to do this" sheet | `dashboard.tsx:163-165` Pressable has styling only, NO onPress | dead | missing-on-web (Filament has no database-notifications bell configured on this panel) | Add onPress: guest -> sign-in sheet; logged-in -> new notifications sheet (needs endpoint; CONTRACT has no notifications endpoint -> either add `GET /api/notifications` or hide-not-allowed; at minimum open an empty-state sheet "You're all caught up") |
| 2 | Header avatar (person icon) | Both | Go to Profile tab / sign-in for guest | `dashboard.tsx:166-168` plain View, not pressable, never shows `avatar` | dead (non-interactive) | Filament user menu (top-right) works | Wrap in Pressable -> `router.push('/(tabs)/profile)'`; guest -> sign-in sheet; render `me.avatar` like greeting avatar |
| 3 | Greeting avatar + name/handle | Both | Display only (optionally open profile) | `dashboard.tsx:175-195` Image from `me.data.user.avatar`; fallback icon; handle falls back to `@creator` | works (display) | n/a (web shows no greeting) | none; guest shows "there"/@creator by design |
| 4 | "Auto-sync active" badge | Both | Status indicator | `dashboard.tsx:197-200` static text, always "active" regardless of accounts/sync state | demo-data-when-logged-in (hard-coded) | missing-on-web | Derive from accounts (any `status==='active'` / `last_synced_at`); show "No channels" otherwise |
| 5 | KPI: Total Views (+delta badge) | Both | `overview.totals.views`, `change.views` | `dashboard.tsx:207`, `useOverview(range)` hooks.ts:143 | works (logged-in real API `/analytics/overview`; guest demoOverview) | works: AnalyticsOverview widget uses AnalyticsService::overview (same service as API) | none |
| 6 | KPI: Total Likes | Both | `overview.totals.likes` | `dashboard.tsx:208` | works | works (AnalyticsOverview) | none; no delta badge shown (change.likes available) |
| 7 | KPI: Published clips | Both | `overview.posts_published` | `dashboard.tsx:209` | works | works/ verify web shows same stat | none |
| 8 | KPI: Connected "N of 4" + dots | Both | connected accounts, per-platform dot | `dashboard.tsx:210-220` from `overview.connected_accounts` / `useSocialAccounts` | works; NOT tappable | missing-on-web (ConnectAccounts page exists separately) | Optional: make card tap -> Profile/Connect screen; guest -> sign-in sheet |
| 9 | Period toggle 7d/30d/90d (3 pills) | Both | Switch period, refetch overview + timeseries | `dashboard.tsx:231-235` `setRange(r)` | works (guest: demo for each period) | works: Dashboard.php Select filter, same 7d/30d/90d; web default 7d vs mobile default 30d (dashboard.tsx:68) | Align default period (web 7d) |
| 10 | Performance chart (view line, peak marker) | Both | Timeseries views | `dashboard.tsx:94-95,285-297` `useTimeseries(range,'all')` | works (not interactive, no scrub) | works: ViewsTrendChart (AnalyticsService::timeseries) | none |
| 11 | Recent Posts "New" button | Logged-in | Open create-post | `dashboard.tsx:308` `router.push('/post/new')` | works logged-in; guest: navigates to screen with disabled CTA (post/new.tsx:167,416) | PostResource create (works) | Guest: open "Sign in to do this" sheet instead of navigating |
| 12 | Recent Posts "View all" | Both | Open posts list | `dashboard.tsx:312` `router.push('/posts')` (tab route) | works | TopPostsTable / PostResource list | none (verify `/posts` resolves to tabs/posts) |
| 13 | Post card (x up to 3) | Both | Open post detail | `dashboard.tsx:341` push `/post/[id]` | works logged-in (`usePosts('all')`); guest demo posts open demoPost | TopPostsTable row link `->url(PostResource view)` works | none. Note: web lists top posts by views; mobile lists latest 3 posts (different data/endpoint: `/posts` vs `/analytics/top-posts` - acceptable, label "Recent") |
| 14 | Per-platform view chips inside post card | Both | Display | `dashboard.tsx:365-372` display only (not pressable) | works (display) | PlatformBreakdown widget (AnalyticsService::platforms) | Mobile fetches `/analytics/platforms` nowhere on Home; web has platform breakdown, mobile Home lacks it (Insights tab likely) |
| 15 | Pull-to-refresh | Both | Refetch all | `dashboard.tsx:77,157` `useRefresh([...])` | works | Filament polling (n/a) | none |
| 16 | Web: platform breakdown widget | n/a | Per-platform totals | n/a on mobile Home | n/a | works (PlatformBreakdown) | none (mobile Insights covers) |
| 17 | Web: Top posts table | n/a | Ranking | n/a on mobile Home | n/a | works (TopPostsTable) | mobile equivalent is Recent Posts (see 13) |

## States & data source

- Loading: KPI cards use `loading` skeleton (`Stat`), chart and posts use `Skeleton` (dashboard.tsx:287-288,320-324). OK.
- Error: chart and posts show `InlineMessage` "Couldn't load data" only when no cached data (`:285`, `:318`). KPI cards have NO error state; failed overview shows zeros silently (`overview?.totals.views ?? 0`). No retry affordance except pull-to-refresh. Fix: show error tone/retry on Stat when `overviewQ.isError`.
- Empty: chart "No activity yet for this period"; posts "No posts yet" (guest vs logged-in copy). OK.
- Data source: hooks.ts:61-62 guarantees demo data only for guests (`placeholderData: token ? keepPreviousData : demo...`, queries disabled for guests). Logged-in uses the real `/analytics/overview`, `/analytics/timeseries`, `/posts`, social accounts. No demo.ts leak found on this page. Only hard-coded value is the "Auto-sync active" badge (#4) and "of 4" channel total.
- Parity: mobile `/api/analytics/*` and Filament widgets both route through AnalyticsService (overview §5.1, platforms §5.2, timeseries §5.3, topPosts §5.5) with identical periods 7d/30d/90d, so numbers match. Web default period 7d; mobile initial range is 30d (:68) - mismatch.
- Guest gating: there is no "Sign in to do this" sheet on this page. Bell and header avatar do nothing; "New" navigates to a screen with disabled CTA.
- Dead patterns found: bell Pressable with no onPress (:163); avatar View non-interactive (:166). No TODO/FIXME, console.log or "coming soon" in dashboard.tsx.
