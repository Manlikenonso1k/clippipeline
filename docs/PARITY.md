# Parity table (mobile ↔ web)

Per-element detail (file:line, current behaviour) lives in `docs/audit/{home,posts,connect,insights,profile}.md`.
This file is the merged fix list: every row has a mobile status, a web status and an owner.
Owners: **BE** = backend agent, **MOB** = mobile agent, **WEB** = Filament agent. Status after fix must be `works` or carry a written reason.

Legend: works · dead · broken · demo (demo/hard-coded data when logged in) · missing

## Cross-cutting

| # | Item | Mobile | Web | Fix | Owner | QA |
|---|---|---|---|---|---|---|
| X1 | "Sign in to do this" sheet for guest-only actions | missing | n/a | One reusable sheet built from existing ui-kit components; used by every guest-gated action below | MOB | Mobile: see mobile repo docs/QA_MOBILE.md. Web: n/a (web is always signed in). |
| X2 | Notification bell (Home, Posts, Insights, Profile headers) | dead | missing | Notifications sheet listing recent events from `GET /api/notifications` (distribution published/failed, account needs re-auth); guest → X1 | BE, MOB, WEB (Filament database notifications or a simple list) | Web works: bell in topbar resources/views/filament/partials/notifications-bell.blade.php + app/Filament/Pages/Notifications.php:47 (NotificationFeed, same as GET /api/notifications; test_feed_matches_the_api). Mobile: mobile repo docs/QA_MOBILE.md |
| X3 | Header avatar (all tabs) | dead | n/a | Pressable → Profile tab; renders `me.user.avatar`; guest → X1 | MOB | Web n/a. Mobile: mobile repo docs/QA_MOBILE.md. |
| X4 | Error states for analytics queries (KPIs/platforms silently show 0) | broken | works | Existing error/empty component with Retry | MOB | Web works (widgets read AnalyticsService). Mobile: mobile repo docs/QA_MOBILE.md. |
| X5 | Default period | 30d | 7d | Both default to **7d** (API default) | MOB | Web works: app/Filament/Widgets/Concerns/UsesAnalyticsPeriod.php (7d default) and Insights.php:75. Mobile: mobile repo docs/QA_MOBILE.md |

## Home (`docs/audit/home.md`)

| # | Item | Mobile | Web | Fix | Owner | QA |
|---|---|---|---|---|---|---|
| H1 | KPI cards, period pills, chart, recent posts, View all, pull-to-refresh | works | works | — | — | Web works: app/Filament/Pages/Dashboard.php:33 widgets (AnalyticsOverview, ViewsTrendChart, TopPostsTable). |
| H2 | "New" quick action | broken (guest lands on disabled CTA) | works | Guest → X1; logged-in → /post/new | MOB | Web works: Posts > New (PostResource create page). |
| H3 | "Auto-sync active" badge | demo (static) | missing | Derive from `GET /api/me` → `sync.status` (active when ≥1 active account and last analytics sync < 4h) | BE, MOB, WEB | Web works: app/Filament/Widgets/SyncStatus.php:33 (MeService sync, same as GET /api/me). |
| H4 | Platform breakdown / top posts widgets | n/a | works | — (web-only, same AnalyticsService) | — | Web works: app/Filament/Widgets/PlatformBreakdown.php, TopPostsTable.php. |

## Posts + details + create (`docs/audit/posts.md`)

| # | Item | Mobile | Web | Fix | Owner | QA |
|---|---|---|---|---|---|---|
| P1 | Filter tabs, list items, details, status chips, retry, open-on-platform, polling | works | works | — | — | Web works: app/Filament/Resources/Posts/Tables/PostsTable.php, Pages/ViewPost.php, RelationManagers/DistributionsRelationManager.php:107 (retry). |
| P2 | Search button | dead | works (table search) | Search field/sheet → `GET /api/posts?q=` | BE, MOB | Web works: PostsTable.php:44,107 (search over caption/platform; FilamentPostsTest::test_list_search_matches_caption_and_platform_filter). |
| P3 | Platform filter button | dead | missing | Platform sheet → `GET /api/posts?platform=` ; web table filter | BE, MOB, WEB | Web works: PostsTable.php:75 (SelectFilter platform). |
| P4 | Post "…" menu | dead | n/a | Action sheet: Share (`Share.share` url), Open on platform, Retry failed, Delete | MOB | Web n/a: row actions View/Delete/Cancel are in PostsTable.php:83-146. |
| P5 | Delete post | missing | works (DeleteAction, local only) | `DELETE /api/posts/{id}` (deletes local post + files; does NOT delete from platforms, says so in confirm) ; web uses same service | BE, MOB, WEB | Web works: PostsTable.php:116-133 + bulk delete :89, via PostDeletionService (same as DELETE /api/posts/{id}); confirm text says platforms are untouched. |
| P6 | Guest retry / publish | broken (raw Alert / inline text) | n/a | Guest → X1 | MOB | Web n/a (always signed in). |
| P7 | TikTok post form (creator_info, privacy w/o default, interaction toggles, disclosure, music notice, confirm step) | missing | missing | See COMPLIANCE.md T-items. `GET /api/tiktok/creator-info`, `platform_options.tiktok` on `POST /api/posts` | BE, MOB, WEB | Web works: app/Filament/Resources/Posts/Schemas/PostForm.php:198-300 (T-01..T-10), confirm step :300-340, platform_options :386. Video preview on Review step added this pass (PostForm::videoPreview). Block over-max duration is server-side (PublishRules.php:152) and shown as a form error. Mobile: mobile repo docs/QA_MOBILE.md |
| P8 | Play button when `video_url` null | broken | n/a | Disabled state | MOB | Web works: video preview only rendered when a temporary URL exists (PostForm.php videoPreview). |
| P9 | "Re-authorize" from a failed distribution | works (generic) | works | Navigate to Connect with the provider pre-selected | MOB | Web works: DistributionsRelationManager.php (retry) and Connect page link (Notifications.php:56 linkFor). |
| P10 | Web create vs API create use different services | — | drift risk | Web create + cross-post go through `PostUploadService` | WEB | Web works: CreatePost.php goes through PostUploadService / CrossPostService (same as POST /api/posts); FilamentPostsTest::test_create_runs_the_api_upload_flow. |

## Connect + login (`docs/audit/connect.md`)

| # | Item | Mobile | Web | Fix | Owner | QA |
|---|---|---|---|---|---|---|
| C1 | Connect / reconnect / disconnect / status / return from OAuth | works | works | — | — | Web works: app/Filament/Pages/ConnectAccounts.php:65,109 (DisconnectService revokes at the platform). |
| C2 | Account with status `error` shown as "Re-auth" | broken | works | Own "Error" pill + `account.error` text | MOB | Web works: ConnectAccounts.php:65 getProviderCards (status + error text); resources/views/filament/pages/connect-accounts.blade.php. |
| C3 | "Pipeline Rules" rows | demo (hard-coded) | missing | Back with `GET/PATCH /api/me/preferences` (`auto_sync`, `default_platforms`, `default_hashtags`); web Profile form | BE, MOB, WEB | Web works: Profile.php:87-100 (notifications, auto-sync, default platforms, default hashtags via PreferencesService, same as /api/me/preferences). |
| C4 | Meta disconnect double tap | risk | works | Disable while pending (API already deletes both Meta rows) | MOB | Web works: DisconnectService handles both Meta rows (DisconnectService.php:38). |
| C5 | Login: Google | broken (custom-scheme redirect, no code_verifier) | works | Backend broker via existing `/auth/callback/google` → one-time code → `clippipelinemobile://auth?code=` → `POST /api/auth/exchange` | BE, MOB | Web works (existing Google web login). Mobile broker: app/Http/Controllers/Api/AuthBrokerController.php (AuthBrokerTest). |
| C6 | Login: TikTok / Facebook | broken | n/a | Same broker using the existing integration callbacks with `purpose=login` in the signed state | BE, MOB | Web n/a. Broker: AuthBrokerController.php. |
| C7 | Login: Terms / Privacy links | dead | works | Open `/terms`, `/privacy` | MOB | Web works: public footer resources/views/partials/public-footer.blade.php:6-8, panel footer panel-footer.blade.php:7-8. |

## Insights (`docs/audit/insights.md`)

| # | Item | Mobile | Web | Fix | Owner | QA |
|---|---|---|---|---|---|---|
| I1 | Period + platform filters, charts, top posts | works | partial (dashboard only) | Web: dedicated **Insights** page with the same filters/charts via AnalyticsService | WEB | Web works: app/Filament/Pages/Insights.php:59-72 (period + platform filters) with InsightsTrendChart/PlatformBreakdown/TopPostsTable, AnalyticsService; test FilamentInsightsTest. |
| I2 | Share / export button | dead | missing | `GET /api/analytics/export?period=` (CSV); mobile downloads + shares, web download action | BE, MOB, WEB | Web works: Insights.php:104-120 export action (ExportService, byte-equal to GET /api/analytics/export, FilamentInsightsTest::test_csv_export_matches_the_api_when_available). |
| I3 | "By Views" sort header | dead | n/a | Sort sheet (views/likes/comments/shares) client-side | MOB | Web n/a (table column sorting). |
| I4 | TikTok overlay curve | demo (fabricated) | n/a | Real `GET /api/analytics/timeseries?platform=tiktok` | MOB | Web works: Insights platform filter uses the same timeseries service. |
| I5 | `TiktokInsights` Filament page | n/a | demo (direct queries, placeholder values) | Replaced by the Insights page; old page removed from navigation | WEB (needs owner sign-off — see report) | Web works: app/Filament/Pages/TiktokInsights.php removed from navigation (shouldRegisterNavigation=false) and made admin-only; Insights page replaces it. Placeholder data left in the legacy admin-only page by design. |

## Profile (`docs/audit/profile.md`)

| # | Item | Mobile | Web | Fix | Owner | QA |
|---|---|---|---|---|---|---|
| R1 | Guest state | broken (shows "Guest" + fake Pro plan + Log Out) | n/a | Sign-in CTA; hide plan/usage/logout; account rows → X1 | MOB | Web n/a. |
| R2 | Plan, renewal date, clip limit, "Resets in", SOC2 claim | demo | missing | `GET /api/me` → `plan` {name, clips_used, clips_limit, renews_at|null}; remove the SOC2 claim; Upgrade opens web billing page | BE, MOB, WEB | Web works: resources/views/filament/pages/profile.blade.php:18 (plan, Upgrade link), Profile.php:62 (MeService); SOC2 absent (FilamentProfileTest asserts). |
| R3 | App version text | demo | n/a | Real version from expo-application / expo-constants | MOB | Web n/a. |
| R4 | Edit profile | missing | missing | `PATCH /api/me` {name}; mobile sheet; web Profile page | BE, MOB, WEB | Web works: Profile.php:74,105 (name edit, saved in a transaction). |
| R5 | Push toggle | dead | missing | Real: register/`DELETE /api/device-tokens` + `notifications_enabled` preference | BE, MOB, WEB | Web works: Profile.php:87 (notifications_enabled). |
| R6 | Auto-sync / watermark toggles, default hashtags | dead | missing | `PATCH /api/me/preferences` (watermark removed if not implemented server-side) | BE, MOB, WEB | Web works: Profile.php:90-100. |
| R7 | Download CSV | dead | missing | Same as I2 | BE, MOB, WEB | Web works: Profile.php:133-154 exportCsv action (ExportService). |
| R8 | Terms / Privacy / Support / Upgrade links | dead | partial | Open `/terms`, `/privacy`, `mailto:` support, `/billing` web page | MOB, WEB | Web works: profile.blade.php:18,40-43, panel-footer.blade.php:7-12 (Privacy, Terms, Support mailto, Upgrade). |
| R9 | Sign out | partial | works | Unregister device token, clear token + react-query cache | MOB | Web works: profile.blade.php:54 + Profile.php:176 logout. |
| R10 | Delete account | missing | missing | `DELETE /api/me` with confirmation + public web page `/account/delete` | BE, MOB, WEB | Web works: Profile.php:156-182 (typed DELETE confirm, AccountDeletionService, same as DELETE /api/me); public /delete-account page. |


## Cross-Post Boost (A-07)

Web: n/a by design. The Boost card is a mobile-only Insights ratio; the Filament Insights page shows only sums and averages from AnalyticsService (no derived YouTube metric), so A-07 holds on web. Mobile excludes YouTube from the ratio and labels it an estimate (mobile repo docs/QA_MOBILE.md, `src/app/(tabs)/insights.tsx:231`).

## Web nav order (verified)

Home (Dashboard.php navigationSort 0), Posts (PostResource 1), Connect (ConnectAccounts 2), Insights (3), Profile (4). Notifications is the topbar bell (not in nav). TiktokInsights is admin-only and hidden from nav. `/admin` is now open to any signed-in user (`User::canAccessPanel` returns `auth()->check()`, 2026-10). Audit: the only Resource (Posts) and all Pages/Widgets are scoped to `auth()->id()`; there is no admin-only resource, and TiktokInsights stays admin-only via `canAccess`. Covered by CanAccessPanelTest. Any new admin-only resource MUST add `canViewAny()` gated on `is_admin`.
