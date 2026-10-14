# Parity table (mobile ↔ web)

Per-element detail (file:line, current behaviour) lives in `docs/audit/{home,posts,connect,insights,profile}.md`.
This file is the merged fix list: every row has a mobile status, a web status and an owner.
Owners: **BE** = backend agent, **MOB** = mobile agent, **WEB** = Filament agent. Status after fix must be `works` or carry a written reason.

Legend: works · dead · broken · demo (demo/hard-coded data when logged in) · missing

## Cross-cutting

| # | Item | Mobile | Web | Fix | Owner |
|---|---|---|---|---|---|
| X1 | "Sign in to do this" sheet for guest-only actions | missing | n/a | One reusable sheet built from existing ui-kit components; used by every guest-gated action below | MOB |
| X2 | Notification bell (Home, Posts, Insights, Profile headers) | dead | missing | Notifications sheet listing recent events from `GET /api/notifications` (distribution published/failed, account needs re-auth); guest → X1 | BE, MOB, WEB (Filament database notifications or a simple list) |
| X3 | Header avatar (all tabs) | dead | n/a | Pressable → Profile tab; renders `me.user.avatar`; guest → X1 | MOB |
| X4 | Error states for analytics queries (KPIs/platforms silently show 0) | broken | works | Existing error/empty component with Retry | MOB |
| X5 | Default period | 30d | 7d | Both default to **7d** (API default) | MOB |

## Home (`docs/audit/home.md`)

| # | Item | Mobile | Web | Fix | Owner |
|---|---|---|---|---|---|
| H1 | KPI cards, period pills, chart, recent posts, View all, pull-to-refresh | works | works | — | — |
| H2 | "New" quick action | broken (guest lands on disabled CTA) | works | Guest → X1; logged-in → /post/new | MOB |
| H3 | "Auto-sync active" badge | demo (static) | missing | Derive from `GET /api/me` → `sync.status` (active when ≥1 active account and last analytics sync < 4h) | BE, MOB, WEB |
| H4 | Platform breakdown / top posts widgets | n/a | works | — (web-only, same AnalyticsService) | — |

## Posts + details + create (`docs/audit/posts.md`)

| # | Item | Mobile | Web | Fix | Owner |
|---|---|---|---|---|---|
| P1 | Filter tabs, list items, details, status chips, retry, open-on-platform, polling | works | works | — | — |
| P2 | Search button | dead | works (table search) | Search field/sheet → `GET /api/posts?q=` | BE, MOB |
| P3 | Platform filter button | dead | missing | Platform sheet → `GET /api/posts?platform=` ; web table filter | BE, MOB, WEB |
| P4 | Post "…" menu | dead | n/a | Action sheet: Share (`Share.share` url), Open on platform, Retry failed, Delete | MOB |
| P5 | Delete post | missing | works (DeleteAction, local only) | `DELETE /api/posts/{id}` (deletes local post + files; does NOT delete from platforms, says so in confirm) ; web uses same service | BE, MOB, WEB |
| P6 | Guest retry / publish | broken (raw Alert / inline text) | n/a | Guest → X1 | MOB |
| P7 | TikTok post form (creator_info, privacy w/o default, interaction toggles, disclosure, music notice, confirm step) | missing | missing | See COMPLIANCE.md T-items. `GET /api/tiktok/creator-info`, `platform_options.tiktok` on `POST /api/posts` | BE, MOB, WEB |
| P8 | Play button when `video_url` null | broken | n/a | Disabled state | MOB |
| P9 | "Re-authorize" from a failed distribution | works (generic) | works | Navigate to Connect with the provider pre-selected | MOB |
| P10 | Web create vs API create use different services | — | drift risk | Web create + cross-post go through `PostUploadService` | WEB |

## Connect + login (`docs/audit/connect.md`)

| # | Item | Mobile | Web | Fix | Owner |
|---|---|---|---|---|---|
| C1 | Connect / reconnect / disconnect / status / return from OAuth | works | works | — | — |
| C2 | Account with status `error` shown as "Re-auth" | broken | works | Own "Error" pill + `account.error` text | MOB |
| C3 | "Pipeline Rules" rows | demo (hard-coded) | missing | Back with `GET/PATCH /api/me/preferences` (`auto_sync`, `default_platforms`, `default_hashtags`); web Profile form | BE, MOB, WEB |
| C4 | Meta disconnect double tap | risk | works | Disable while pending (API already deletes both Meta rows) | MOB |
| C5 | Login: Google | broken (custom-scheme redirect, no code_verifier) | works | Backend broker via existing `/auth/callback/google` → one-time code → `clippipelinemobile://auth?code=` → `POST /api/auth/exchange` | BE, MOB |
| C6 | Login: TikTok / Facebook | broken | n/a | Same broker using the existing integration callbacks with `purpose=login` in the signed state | BE, MOB |
| C7 | Login: Terms / Privacy links | dead | works | Open `/terms`, `/privacy` | MOB |

## Insights (`docs/audit/insights.md`)

| # | Item | Mobile | Web | Fix | Owner |
|---|---|---|---|---|---|
| I1 | Period + platform filters, charts, top posts | works | partial (dashboard only) | Web: dedicated **Insights** page with the same filters/charts via AnalyticsService | WEB |
| I2 | Share / export button | dead | missing | `GET /api/analytics/export?period=` (CSV); mobile downloads + shares, web download action | BE, MOB, WEB |
| I3 | "By Views" sort header | dead | n/a | Sort sheet (views/likes/comments/shares) client-side | MOB |
| I4 | TikTok overlay curve | demo (fabricated) | n/a | Real `GET /api/analytics/timeseries?platform=tiktok` | MOB |
| I5 | `TiktokInsights` Filament page | n/a | demo (direct queries, placeholder values) | Replaced by the Insights page; old page removed from navigation | WEB (needs owner sign-off — see report) |

## Profile (`docs/audit/profile.md`)

| # | Item | Mobile | Web | Fix | Owner |
|---|---|---|---|---|---|
| R1 | Guest state | broken (shows "Guest" + fake Pro plan + Log Out) | n/a | Sign-in CTA; hide plan/usage/logout; account rows → X1 | MOB |
| R2 | Plan, renewal date, clip limit, "Resets in", SOC2 claim | demo | missing | `GET /api/me` → `plan` {name, clips_used, clips_limit, renews_at|null}; remove the SOC2 claim; Upgrade opens web billing page | BE, MOB, WEB |
| R3 | App version text | demo | n/a | Real version from expo-application / expo-constants | MOB |
| R4 | Edit profile | missing | missing | `PATCH /api/me` {name}; mobile sheet; web Profile page | BE, MOB, WEB |
| R5 | Push toggle | dead | missing | Real: register/`DELETE /api/device-tokens` + `notifications_enabled` preference | BE, MOB, WEB |
| R6 | Auto-sync / watermark toggles, default hashtags | dead | missing | `PATCH /api/me/preferences` (watermark removed if not implemented server-side) | BE, MOB, WEB |
| R7 | Download CSV | dead | missing | Same as I2 | BE, MOB, WEB |
| R8 | Terms / Privacy / Support / Upgrade links | dead | partial | Open `/terms`, `/privacy`, `mailto:` support, `/billing` web page | MOB, WEB |
| R9 | Sign out | partial | works | Unregister device token, clear token + react-query cache | MOB |
| R10 | Delete account | missing | missing | `DELETE /api/me` with confirmation + public web page `/account/delete` | BE, MOB, WEB |
