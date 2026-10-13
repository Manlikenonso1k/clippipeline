# ClipPipeline — Handoff

**For:** an agent/developer picking this up cold.
**What ClipPipeline is:** a "post once, everywhere" tool for short-form creators. A creator's TikTok clip is ingested and auto-syndicated to **YouTube Shorts** and **Instagram Reels**, with unified cross-platform analytics. Tagline: *Post once. Everywhere.*

---

## Repos & environments

| Piece | Location (this PC) | GitHub | Branch | Live |
|---|---|---|---|---|
| Mobile app (Expo/React Native) | `Documents/clippipeline_mobile` | `Manlikenonso1k/clippipeline_mobile` | `feat/app-screens-auth-charts` | — |
| Backend (Laravel 12 + Filament) | `Documents/clippipeline` | `Manlikenonso1k/clippipeline` | `main` | `clippipeline.nonsostudio.com` |

> **Go-live update (2026-10-14):** see "Go-live: what changed" at the bottom. The API contract is `docs/CONTRACT.md` (same file in both repos) — the single source of truth for endpoints and mobile types.

- **Mobile:** Expo SDK 57, Expo Router, TypeScript. Package `com.codebynonso.clippipeline`. EAS project `@codebynonso/clippipeline-mobile`. Config is **`app.json`** (not app.config.ts).
- **Backend:** Laravel 12, Sanctum API auth, Filament admin, PHP 8.3. Hosted on Hostinger at `/home/u519226541/domains/nonsostudio.com/public_html/clippipeline`. MySQL.
- **Design system:** "Kinetic Obsidian" (dark, violet/fuchsia accents). Source specs live in the `*/DESIGN.md` + `*/code.html` + `*/screen.png` folders at the mobile repo root (one per screen).

---

## What the app CAN do today

### Mobile
- **Onboarding** — 3 animated welcome screens → login.
- **Auth** — Google login (working on web; needs native redirect verify), Facebook & TikTok (client flows wired, see gaps), **email/password** login, and **Guest/Preview mode** (no login).
- **10 screens**, all built to the design: Dashboard, Posts, Post details (`/post/[id]`), Connect channels, Insights, Profile. Plus welcome 1–3 and Login.
- **Navigation** — single persistent bottom **Tabs** bar (Ionicons outline/filled, haptics, bounce animation, tabs stay mounted).
- **Charts** via `react-native-svg`; gradients via `expo-linear-gradient`; animations via `react-native-reanimated`.
- **Live data** — react-query hooks (`src/api/hooks.ts`) hit the backend; **Guest mode shows demo data** (`src/api/demo.ts`) so every screen is explorable signed-out.
- **Push notifications** (`src/lib/notifications.ts`):
  - Android channels: `sync`, `published`, `alerts` (HIGH importance → user-configurable sound in Android settings).
  - **Welcome** local notification on first launch (no login/server).
  - Device **push-token registration** (works signed-out *and* attaches to user on login) → `POST /api/device-tokens`.
  - **Tap-to-open** routes to the relevant post.

### Backend (API, all under `clippipeline.nonsostudio.com/api`)
- `POST /auth/login`, `/auth/google/token`, `/auth/facebook/token`, `/auth/tiktok/token` → returns a Sanctum token.
- `GET /me`, `/social-accounts`, `/analytics/overview?period=7d|30d|90d`, `/posts?filter=`, `/posts/{id}`.
- `POST /device-tokens` (public — guests allowed), `DELETE /device-tokens` (auth).
- **Push:** `ExpoPushService` sends via Expo. Auto-fires on `PostDistribution` status change (**failed** → alerts, **published** → published). Commands: `php artisan push:test <email>`, `php artisan push:broadcast "<title>" "<body>" {--channel=}`.
- **Pipeline:** TikTok ingest (`PollTikTokFeed`), cross-posting jobs, Filament admin for posts/distributions/analytics.
- **Models:** `User`, `Post`, `PostDistribution`, `PostAnalytic`, `SocialAccount`, `DeviceToken`.
- **Billing:** Paystack/Flutterwave/TGiPay integration exists in `BillingController` (not yet surfaced in the mobile UI).

---

## What the app SHOULD do (final product)

1. **End-to-end syndication that actually works** — ingest a TikTok clip, transcode, and publish to YouTube Shorts + Instagram Reels with real media, retries, and re-auth flows. (Backend jobs exist; verify the full happy path on real accounts.)
2. **Real data in the app** — screens currently show demo data (guest) or empty states (logged-in with no rows). Needs real posts/analytics flowing. Add **post thumbnails** (DB column exists on `posts.thumbnail`; populate it from the ingest pipeline — the mobile UI already renders it).
3. **Scheduling** — schedule posts for a future time; "scheduled post due" notifications.
4. **Per-platform analytics** pulled from each platform's API (views/likes/comments/shares/watch-time), not sample numbers.
5. **Billing surfaced in-app** — Pro/Agency tiers, upgrade flow (profile screen has static plan UI; wire to the billing backend + a `/plan` endpoint).
6. **Full social login** — Facebook/TikTok login completed end-to-end (redirect URIs registered, sandbox → production).
7. **iOS** — build + App Store submission (Android is the only target built so far).
8. **Notification depth** — account disconnected alerts, digest/summary pushes, in-app notification center.

---

## Known gaps / TODO (pick-up list)

- **Facebook/TikTok login:** backend token endpoints exist, but each provider needs the mobile redirect `clippipelinemobile://oauth` (and web origin) registered in its console; TikTok is on a **sandbox** key (whitelisted testers only). Native Google also needs `clippipelinemobile://oauth` in the Google console.
- **Dynamic 3-state app icon** (active/inactive/sleeping) — spec in `prompt.md`; deferred. Needs a native Android `activity-alias` + WorkManager + Expo config plugin. Assets are in `assets/expo.icon/Assets/`.
- **Swipe-between-tabs** — deferred (would need `react-native-pager-view` / material-top-tabs).
- **EAS Update (OTA)** not set up — every JS change currently requires a full rebuild. Adding `expo-updates` would let `eas update` ship JS instantly.
- **Scheduler cron** (install in hPanel → Cron Jobs):
  ```
  * * * * * cd /home/u519226541/domains/nonsostudio.com/public_html/clippipeline && /opt/alt/php83/usr/bin/php artisan schedule:run >> /dev/null 2>&1
  ```

---

## Gotchas (read before building)

- **Expo changes every SDK** — check `expo` version in `package.json`, then the matching `https://docs.expo.dev/versions/v<major>.0.0/`. See `AGENTS.md`. Never answer Expo APIs from memory.
- **Dependency installs:** `npx expo install` is blocked here by an `--allow-scripts` npm policy. Fallback: get the SDK-pinned version from `node_modules/expo/bundledNativeModules.json`, then `npm install <pkg>@<version>`.
- **`package-lock.json` is gitignored** — local npm (v11/Node 24) resolved versions EAS's `npm ci` rejected (`hermes-parser` drift). Without a lockfile EAS runs `npm install` and resolves the SDK-pinned versions. Don't re-commit the lockfile.
- **Native modules** (reanimated, svg, linear-gradient, notifications, haptics, auth-session, secure-store) require a **dev/EAS build** — not Expo Go.
- **Push delivery** needs the **FCM V1** service-account key in EAS (already configured) *and* `google-services.json` in the build (committed; it also lists the Hospi packages — harmless, Android matches by package).
- **Backend safety rules:** never run `composer update`, `migrate:fresh`, or `db:seed`, and never touch the production DB. Create migration *files*; let deploy run `php artisan migrate --force`. After pulling new classes on the server, run `composer dump-autoload`.
- **Secrets:** mobile env in `.env.local` (gitignored); Firebase service-account keys and `*-firebase-adminsdk-*.json` are gitignored — never commit them. `google-services.json` is safe to commit.

---

## Build & test quick reference

```bash
# Mobile (from clippipeline-mobile)
npx tsc --noEmit                         # typecheck
npx expo lint                            # lint
npx expo start --web --port 8081         # local dev (web)
npx eas-cli build -p android --profile preview --non-interactive --no-wait   # APK (bump app.json android.versionCode first)

# Backend (on the server, from the project dir)
git pull origin main
/opt/alt/php83/usr/bin/php artisan migrate --force
/opt/alt/php83/usr/bin/php artisan optimize:clear
/opt/alt/php83/usr/bin/php artisan push:test <email>        # per-user test (device must be registered)
/opt/alt/php83/usr/bin/php artisan push:broadcast "Title" "Body"   # to all devices
```

**Three notification types that exist:** welcome/local (automatic, first launch), broadcast (`push:broadcast`, all devices, no login), per-user (`push:test` + automatic failed/published alerts, user must be logged in).

---

## Go-live: what changed (2026-10-14)

Built on `feat/go-live` in both repos, merged into backend `main` and mobile `feat/app-screens-auth-charts`.

**Backend**
- **Connect** (`app/Services/Connect/*`): `GET /api/connect/{youtube|meta|tiktok}/start?platform=web|mobile` returns an auth URL with an encrypted signed state. It reuses the production callbacks `/integrations/callback/{youtube|instagram|tiktok}`; the callback no longer needs a web session. Mobile is sent back to `clippipelinemobile://connect?provider=..&status=ok|error`.
  - Meta creates `facebook` (Page token) and `instagram` (IG business id in `external_id`) rows.
  - Tokens use `encrypted` casts; a migration encrypts the existing tokens.
  - `TokenManager::freshAccessToken()` refreshes tokens. A failed refresh marks the account `needs_reauth` and sends a push.
  - Graph API pinned to v26.0 (`META_GRAPH_VERSION`).
  - Commands: `accounts:refresh-tokens` (hourly) and `app:backfill-accounts`.
- **Publish:** `POST /api/posts` (multipart) stores on the public disk, HEAD-checks the URL, then creates one distribution and one job per platform.
  - Jobs: YouTube resumable, IG Reels, FB Page Reels, TikTok direct post (FILE_UPLOAD).
  - Status flow `queued → uploading → processing → published | failed`. Jobs are idempotent and unique, with 3 real failures max.
  - `POST /api/distributions/{id}/retry`, plus a Filament Retry action.
  - `posts:dispatch-scheduled` publishes posts when `scheduled_at` is due.
  - Thumbnails come from each platform's own thumbnail URL (no ffmpeg).
  - TikTok's official API can't download videos, so upload-once is the main flow and the scraper ingest is optional.
- **Analytics:** `App\Services\AnalyticsService` is the only source for both the API and the Filament widgets.
  - Daily lifetime snapshots go in `post_analytics` (unique per distribution per day) and `account_follower_snapshots`.
  - `analytics:sync` runs every 3h, plus `--recent` about 1h after a publish.
  - Endpoints: `/api/analytics/{overview,platforms,timeseries,top-posts}` and `/api/posts/{id}/analytics`.
- **Web (Filament):** Connected Accounts page (connect/reconnect/disconnect with status and expiry). The Posts resource is scoped to the owner, with upload + platforms on create and a distributions table that polls and retries. Dashboard widgets with a 7d/30d/90d filter; the old sample-data widgets were removed.
- **QA commands:** `app:smoke {email}` (refresh + one call per platform) and `app:test-crosspost {email} {path}` (real post, YouTube private / TikTok SELF_ONLY).
- **Scheduler** (`routes/console.php`): `queue:work --stop-when-empty --max-time=50` every minute (no supervisor on Hostinger), scheduled dispatch, token refresh, analytics sync.

**Mobile**
- `src/api/types.ts` mirrors CONTRACT.md, and `src/api/hooks.ts` has a hook per endpoint. Demo data only appears in guest mode (no token).
- Connect screen uses `WebBrowser.openAuthSessionAsync`, with a deep-link fallback.
- Create post at `src/app/post/new.tsx`: `expo-image-picker@~57.0.20`, XHR upload with a progress bar, choice of active platforms, optional schedule.
- Post details: polls every 5s while any platform is publishing, Retry, per-platform metrics, open-on-platform link.
- Dashboard and Insights show real data for 7d/30d/90d, with pull-to-refresh and refetch on focus. A push invalidates the post and analytics queries.

**Left to do**
- `/admin` is still limited to `is_admin` users (`User::canAccessPanel`). Promote users with `app:make-admin <email>`, or open the panel to all users (resources and widgets are already scoped to the owner).
- `TiktokInsights` Filament page still renders placeholder values. Hide it or rewire it to AnalyticsService.
- Not yet verified on a device: upload progress, Android `openAuthSessionAsync` result handling.
- Not yet run against the real platforms: `app:test-crosspost` and `app:smoke`. Run both on the server after deploy.
- IG/FB/TikTok thumbnail URLs expire. Consider copying them to storage.
- TikTok follower counts need the `user.info.stats` scope.
- Out of scope today: billing UI, iOS build, dynamic icon, swipe tabs, digest notifications.
