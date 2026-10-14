# ClipPipeline API Contract (v1, go-live)

Single source of truth for the backend API (`clippipeline`) and the mobile app (`clippipeline_mobile/src/api/types.ts`).
A copy lives at `clippipeline_mobile/docs/CONTRACT.md`. Change both together or not at all.

- Base URL: `https://clippipeline.nonsostudio.com/api`
- Auth: `Authorization: Bearer <sanctum token>` on every route marked **auth**.
- JSON is snake_case. Times are ISO-8601 with offset (`2026-10-13T09:00:00+00:00`). Dates are `YYYY-MM-DD` (UTC).
- Counts are integers, never strings, never null. A metric a platform does not report is `0`.
- Lists are plain JSON arrays (no `data` wrapper) unless shown otherwise.
- Errors: `401 {"message":"Unauthenticated."}`, `404 {"message":"Not found."}`, `422 {"message":"...","errors":{"field":["..."]}}` (Laravel default), `409 {"message":"..."}`.
- **Scoping:** every query is scoped to the authenticated user. Another user's id returns `404`.
- **No sample data:** a logged-in user with no rows gets zeros / empty arrays, never demo numbers.

---

## 1. Shared enums

```ts
type Platform        = 'youtube' | 'facebook' | 'instagram' | 'tiktok';   // publish + analytics target
type ConnectProvider = 'youtube' | 'meta' | 'tiktok';                      // OAuth flow (meta => facebook + instagram rows)
type AccountStatus   = 'active' | 'needs_reauth' | 'error';
type DistributionStatus = 'queued' | 'uploading' | 'processing' | 'published' | 'failed';
type PostStatus      = 'scheduled' | 'processing' | 'published' | 'partial' | 'failed';
type Period          = '7d' | '30d' | '90d';
```

`PostStatus` is derived from the post's distributions (server-side, `Post::overallStatus()`):
1. `scheduled` — `scheduled_at` is in the future and every distribution is `queued`.
2. `processing` — any distribution is `queued` / `uploading` / `processing`.
3. `published` — every distribution is `published`.
4. `failed` — every distribution is `failed`.
5. `partial` — otherwise (mix of `published` and `failed`).
A post with zero distributions is `processing`.

```ts
interface Metrics {
  views: number;
  likes: number;
  comments: number;
  shares: number;
  saves: number;
  watch_time_seconds: number;
}

// Percentage change vs the previous equal-length window, rounded to 1 decimal.
// null when the previous value is 0.
type MetricsChange = { [K in keyof Metrics]: number | null };
```

---

## 2. Auth (existing, unchanged)

| Method | Path | Auth | Notes |
|---|---|---|---|
| POST | `/auth/login` | – | `{email,password}` → `{token,user}` |
| POST | `/auth/google/token` | – | existing |
| POST | `/auth/facebook/token` | – | existing |
| POST | `/auth/tiktok/token` | – | existing |
| GET | `/me` | auth | `{user:{id,name,email,avatar}, server_time}` |
| POST | `/device-tokens` | optional | existing |
| DELETE | `/device-tokens` | auth | existing |

---

## 3. Connect & accounts  (owner: CONNECT agent)

### GET `/connect/{provider}/start?platform=web|mobile`  — auth
`provider` ∈ `ConnectProvider`. `platform` defaults to `mobile` on the API.

200:
```json
{
  "provider": "meta",
  "platform": "mobile",
  "auth_url": "https://www.facebook.com/v26.0/dialog/oauth?client_id=...&state=...",
  "expires_at": "2026-10-13T09:10:00+00:00"
}
```
`state` is an encrypted, signed payload `{uid, provider, platform, nonce, exp, pkce?}` valid 10 minutes.
422 for an unknown provider/platform.

### OAuth callbacks (web routes, already registered on production — DO NOT change the URLs)

| Provider | Callback URL (existing) |
|---|---|
| youtube | `GET /integrations/callback/youtube` |
| meta | `GET /integrations/callback/instagram` (value of `FACEBOOK_REDIRECT`) |
| tiktok | `GET /integrations/callback/tiktok` (value of `TIKTOK_REDIRECT`) |

The callback resolves the user from `state` (falls back to the web session for legacy links), stores tokens with
`encrypted` casts, and redirects:
- `platform=web` → `/admin/connect-accounts` with a flash `status` or error.
- `platform=mobile` → `clippipelinemobile://connect?provider=<youtube|meta|tiktok>&status=ok`
  or `clippipelinemobile://connect?provider=<...>&status=error&message=<urlencoded>`.

Meta creates/updates **two** rows: `facebook` (Page access token, `external_id` = page id) and `instagram`
(`external_id` = `instagram_business_account.id`, uses the same Page token). If no IG business account is linked,
the `instagram` row is stored with `status: "error"` and `error` explaining why.

The legacy web entry `GET /integrations/redirect/{provider}` (session auth) still works and uses the same service with `platform=web`
(`provider` accepts `youtube|meta|instagram|tiktok`; `instagram` is an alias for `meta`).

### GET `/social-accounts` — auth
200: `SocialAccount[]` (one per connected platform, ordered youtube, facebook, instagram, tiktok)
```json
[
  {
    "id": 12,
    "platform": "instagram",
    "provider": "instagram",
    "connect_provider": "meta",
    "handle": "@clippipeline",
    "display_name": "ClipPipeline",
    "avatar_url": "https://...",
    "external_id": "17841400000000000",
    "follower_count": 1520,
    "status": "active",
    "connected": true,
    "expires_at": "2026-12-12T09:00:00+00:00",
    "last_synced_at": "2026-10-13T06:00:00+00:00",
    "error": null
  }
]
```
`provider` duplicates `platform` (back-compat). `connected` is `status === "active"`.

### DELETE `/social-accounts/{id}` — auth
Deletes the account. For a `facebook` or `instagram` row, both Meta rows of that user are deleted (they share one token).
200: `{"deleted_ids":[12,13]}` · 404 if not the user's.

---

## 4. Posts & publishing  (owner: PUBLISH agent)

```ts
interface PostDistribution {
  id: number;
  post_id: number;
  platform: Platform;
  status: DistributionStatus;
  external_id: string | null;
  url: string | null;            // open-on-platform link, null until published
  error_message: string | null;  // real platform error text when failed
  privacy: string | null;        // e.g. "private" (YouTube unaudited), "SELF_ONLY" (TikTok unaudited)
  attempts: number;
  published_at: string | null;
  updated_at: string;
}

interface PostAnalytic {           // latest snapshot per platform for this post
  platform: Platform;
  distribution_id: number;
  views: number; likes: number; comments: number; shares: number; saves: number;
  watch_time_seconds: number;
  captured_on: string;            // YYYY-MM-DD
  recorded_at: string;            // ISO time of the snapshot
}

interface Post {
  id: number;
  title: string;
  caption: string | null;
  hashtags: string[];             // without '#'
  video_url: string;              // absolute https URL
  thumbnail: string | null;       // platform thumbnail URL once published
  tiktok_id: string | null;
  source: 'upload' | 'tiktok_ingest';
  status: PostStatus;
  scheduled_at: string | null;
  published_at: string | null;    // first successful distribution
  created_at: string;
  distributions: PostDistribution[];
  analytics: PostAnalytic[];
  totals: Metrics;                // sum of `analytics`
}
```

### GET `/posts?filter=all|published|processing|failed|scheduled` — auth
200: `Post[]` newest first. `filter` matches `Post.status` (`all` = no filter); `failed` matches `failed` **or** `partial` (any platform failed).

### GET `/posts/{id}` — auth
200: `Post`.

### POST `/posts` — auth, `multipart/form-data`
| Field | Rules |
|---|---|
| `video` | required file, mimetypes `video/mp4,video/quicktime,video/webm`, max `config('clippipeline.max_upload_mb')` MB |
| `title` | required string, max 100 |
| `caption` | nullable string, max 2200 |
| `hashtags` | nullable; either `hashtags[]` array or one comma/space separated string; stored without `#` |
| `platforms[]` | required array, min 1, each in `Platform`, each must be an `active` account of the user (422 otherwise) |
| `scheduled_at` | nullable ISO date, must be in the future |

Flow: store on `public` disk → absolute HTTPS `video_url` → HEAD check it is publicly reachable (422 `video` error if not)
→ one `PostDistribution` (status `queued`) per platform → dispatch one job per platform, unless `scheduled_at` is set
(then the scheduler dispatches at that time). Uploads never run inside the request.

201: `Post`.

### POST `/distributions/{id}/retry` — auth
Only when status is `failed`. Resets `error_message`, sets `queued`, dispatches the job.
200: `PostDistribution` · 409 `{"message":"Only failed distributions can be retried."}`.

---

## 5. Analytics  (owner: ANALYTICS agent)

All numbers come from `App\Services\AnalyticsService`, used by BOTH the API controllers and the Filament widgets.
Controllers return the service arrays unchanged.

```php
AnalyticsService::overview(User $user, string $period): array      // §5.1
AnalyticsService::platforms(User $user, string $period): array     // §5.2
AnalyticsService::timeseries(User $user, string $period, string $platform = 'all'): array  // §5.3
AnalyticsService::post(Post $post): array                          // §5.4
AnalyticsService::topPosts(User $user, string $period, int $limit = 5): array             // §5.5
```

### Math (snapshots are lifetime counters)
`post_analytics` holds one row per distribution per day (`captured_on`) with **lifetime** totals as reported by the platform.
- `value(d, day)` = latest snapshot of distribution `d` with `captured_on <= day`, else 0.
- Window `[start, end]`: `end` = today (UTC), `start` = today − (days − 1). Previous window = the `days` days before `start`.
- Period total for a metric = Σ_d max(0, value(d, end) − value(d, start − 1)).
- Daily point = Σ_d max(0, value(d, day) − value(d, day − 1)).
- `change` = `round((cur − prev) / prev * 100, 1)`, `null` when `prev == 0`.
- Followers: latest `account_follower_snapshots` per account ≤ end, summed; `change` vs the value at `start − 1` (absolute delta, int).

### 5.1 GET `/analytics/overview?period=7d|30d|90d` — auth (default `7d`)
```json
{
  "period": "7d",
  "start": "2026-10-07",
  "end": "2026-10-13",
  "totals":   {"views":1200,"likes":80,"comments":12,"shares":5,"saves":3,"watch_time_seconds":5400},
  "previous": {"views":1000,"likes":40,"comments":12,"shares":0,"saves":0,"watch_time_seconds":3600},
  "change":   {"views":20.0,"likes":100.0,"comments":0.0,"shares":null,"saves":null,"watch_time_seconds":50.0},
  "followers": {"total": 3400, "change": 120},
  "posts_published": 4,
  "connected_accounts": 3,
  "total_views": 1200,
  "total_likes": 80,
  "total_comments": 12,
  "total_shares": 5,
  "trend": [{"date":"2026-10-07","views":150}]
}
```
`total_*` and `trend` are kept for back-compat and equal `totals.*` and the timeseries views.
`posts_published` = distinct posts with a distribution published inside the window.
`connected_accounts` = accounts with `status = active`.

### 5.2 GET `/analytics/platforms?period=` — auth
Always returns all four platforms in order youtube, facebook, instagram, tiktok.
```json
{
  "period": "30d",
  "platforms": [
    {
      "platform": "youtube",
      "connected": true,
      "followers": 1200,
      "posts_published": 3,
      "totals": {"views":0,"likes":0,"comments":0,"shares":0,"saves":0,"watch_time_seconds":0},
      "change": {"views":null,"likes":null,"comments":null,"shares":null,"saves":null,"watch_time_seconds":null}
    }
  ]
}
```

### 5.3 GET `/analytics/timeseries?period=&platform=all|youtube|facebook|instagram|tiktok` — auth
One point per day in the window, zero-filled.
```json
{
  "period": "7d",
  "platform": "all",
  "points": [
    {"date":"2026-10-07","views":150,"likes":10,"comments":1,"shares":0,"saves":0,"watch_time_seconds":600}
  ]
}
```

### 5.4 GET `/posts/{id}/analytics` — auth
Lifetime numbers (latest snapshot) per platform plus a 30-day daily series per platform.
```json
{
  "post_id": 7,
  "totals": {"views":900,"likes":70,"comments":9,"shares":4,"saves":2,"watch_time_seconds":4000},
  "platforms": [
    {
      "platform": "youtube",
      "distribution_id": 21,
      "status": "published",
      "url": "https://www.youtube.com/watch?v=abc",
      "captured_on": "2026-10-13",            // null until the first snapshot exists
      "metrics": {"views":500,"likes":40,"comments":5,"shares":0,"saves":0,"watch_time_seconds":3000},
      "points": [{"date":"2026-10-12","views":100,"likes":5,"comments":1,"shares":0,"saves":0,"watch_time_seconds":400}]
    }
  ]
}
```

### 5.5 GET `/analytics/top-posts?period=&limit=5` — auth (limit 1–20)
Posts ranked by views gained in the window.
```json
[
  {"post_id":7,"title":"My clip","thumbnail":"https://...","platforms":["youtube","tiktok"],
   "totals":{"views":900,"likes":70,"comments":9,"shares":4,"saves":2,"watch_time_seconds":4000}}
]
```

---

## 6. Database (target schema after go-live migrations)

- `social_accounts`: `provider`/`platform_name` are strings (`youtube|facebook|instagram|tiktok`), `access_token`/`refresh_token`
  use `encrypted` casts, plus `status` (default `active`), `external_id`, `display_name`, `avatar_url`, `last_synced_at`, `last_error`.
  Unique `(user_id, provider)`.
- `posts`: plus `hashtags` json, `scheduled_at`, `source` (default `tiktok_ingest`), `video_path`.
- `post_distributions`: `platform` string, `status` string, plus `attempts` (int, default 0), `privacy`, `dispatched_at`.
- `post_analytics`: `platform` string, plus `distribution_id`, `saves`, `watch_time_seconds`, `avg_view_duration_seconds`,
  `captured_on` (date). Unique `(distribution_id, captured_on)`.
- `account_follower_snapshots`: `id, social_account_id, platform, followers, captured_on, timestamps`, unique `(social_account_id, captured_on)`.

## 7. Push payloads (existing, extended)
`{type: 'distribution_published' | 'distribution_failed' | 'account_needs_reauth', screen: 'post' | 'connect', postId?: number, provider?: ConnectProvider}`.
The mobile app invalidates `['posts']`, `['post', postId]` and `['analytics']` queries on `distribution_*`, and `['social-accounts']` on `account_needs_reauth`.

## 8. Scheduler (routes/console.php)
| Entry | Cadence | Owner |
|---|---|---|
| `queue:work --stop-when-empty --max-time=50 --tries=3` | every minute, withoutOverlapping | PUBLISH |
| `posts:dispatch-scheduled` | every minute, withoutOverlapping | PUBLISH |
| `accounts:refresh-tokens` | hourly, withoutOverlapping | CONNECT |
| `analytics:sync` | every 3 hours, withoutOverlapping | ANALYTICS |
| `analytics:sync --recent` (distributions published 55–120 min ago, not yet synced after publish) | every 10 minutes | ANALYTICS |
| `tiktok:poll-latest` | every 15 minutes (existing, optional) | – |

---

# Part 2 — Parity & compliance additions (feat/parity)

Owners: **BE** backend agent. Mobile types (`src/api/types.ts`) and Filament use exactly these shapes.
Fix list: `docs/PARITY.md`. Compliance rules: `docs/COMPLIANCE.md` (IDs referenced below).

## 9. Mobile sign-in broker (fixes mobile Google/Facebook/TikTok login; no new redirect URIs)

### GET `/auth/{provider}/start?platform=mobile` — public, provider ∈ `google|facebook|tiktok`
200 `{"provider":"google","auth_url":"https://accounts.google.com/o/oauth2/v2/auth?...&state=...","expires_at":"..."}`
The signed state carries `{purpose:"login", provider, platform:"mobile", nonce, exp}`.
Callbacks used (already registered): google → `/auth/callback/google`; facebook → `/integrations/callback/instagram`; tiktok → `/integrations/callback/tiktok`
(the integration callbacks branch on `state.purpose === "login"`; basic login scopes only).
On success the callback finds-or-creates the user (same matching as `/auth/*/token`), stores a one-time code (5 min, single use)
and redirects to `clippipelinemobile://auth?status=ok&code=<code>`; on failure `clippipelinemobile://auth?status=error&message=<urlencoded>`.

### POST `/auth/exchange` — public
Request `{"code":"<one-time code>","device_name":"android"}` → 200 `{"token":"<sanctum>","user":{"id":1,"name":"...","email":"...","avatar":null}}` · 422 invalid/expired/used code.

## 10. Me, preferences, account deletion

```ts
interface Plan { name: 'free' | 'pro' | 'agency'; clips_used: number; clips_limit: number | null; renews_at: string | null; billing_url: string }
interface SyncState { status: 'active' | 'idle' | 'attention'; last_synced_at: string | null }
// active = ≥1 active account and last analytics sync < 4h; attention = any account needs_reauth/error; idle otherwise
interface Preferences { notifications_enabled: boolean; auto_sync: boolean; default_platforms: Platform[]; default_hashtags: string[] }
interface AppFlags { tiktok_audited: boolean; youtube_audited: boolean; support_email: string; privacy_url: string; terms_url: string; delete_account_url: string }
interface Me { user: User; server_time: string; plan: Plan; sync: SyncState; preferences: Preferences; flags: AppFlags }
```
`clips_used` = posts created in the current calendar month. `clips_limit` from config per plan (`null` = unlimited). `billing_url` = absolute URL of the web billing page.

| Method | Path | Body | Response |
|---|---|---|---|
| GET | `/me` | – | `Me` (extends §2; old fields unchanged) |
| PATCH | `/me` | `{"name":"New name"}` (1–100 chars) | `Me` |
| GET | `/me/preferences` | – | `Preferences` |
| PATCH | `/me/preferences` | any subset of `Preferences` | `Preferences` |
| DELETE | `/me` | `{"confirm":"DELETE"}` | 202 `{"status":"scheduled","completes_by":"<ISO, ≤7 days>"}`; tokens revoked and Sanctum tokens deleted immediately (A-02) |

`auto_sync=false` excludes the user from scheduled `analytics:sync` and `tiktok:poll-latest`. `notifications_enabled=false` stops pushes to the user's devices.

## 11. Notifications (bell)

```ts
interface AppNotification { id: string; type: 'distribution_published' | 'distribution_failed' | 'account_needs_reauth'; title: string; body: string; data: { postId?: number; provider?: ConnectProvider }; read_at: string | null; created_at: string }
```
| Method | Path | Response |
|---|---|---|
| GET | `/notifications?limit=30` | `{"unread_count":2,"items":AppNotification[]}` newest first |
| POST | `/notifications/read-all` | 204 |

Every push sent by `ExpoPushService::sendToUser` also stores a Laravel database notification with the same title/body/data (sent even when push is disabled).

## 12. Posts additions

- `GET /posts` adds optional `q` (matches title/caption, case-insensitive) and `platform` (`Platform`, posts with a distribution on it).
- `DELETE /posts/{id}` → 204. Deletes the post, its distributions, analytics and stored video file; cancels queued/scheduled jobs. Does **not** delete anything already published on platforms (UI says so).
- `POST /posts` adds (multipart, all optional unless noted):
  - `video_duration_seconds` (number) — client-measured; server re-checks with the MP4 header when possible (P-02).
  - `platform_options[youtube][privacy_status]` = `public|private|unlisted` (required when youtube selected; forced `private` while `youtube_audited=false`), `platform_options[youtube][guidelines_confirmed]=1` (required when youtube selected).
  - `platform_options[tiktok][privacy_level]` (required when tiktok selected; must be in the last creator-info `privacy_level_options`), `[disable_comment]`, `[disable_duet]`, `[disable_stitch]` (0/1, default 1 = off), `[brand_organic_toggle]`, `[brand_content_toggle]` (0/1), `[is_aigc]` (0/1), `[music_consent]=1` (required).
  - 422 field errors use keys like `platform_options.tiktok.privacy_level` and `video` (for duration/size caps per platform, P-02/P-03).
- `PostDistribution` adds `options: object | null` (the saved platform_options for that platform).

## 13. TikTok creator info (T-01)

### GET `/tiktok/creator-info` — auth (user must have an active tiktok account, else 404)
```json
{
  "creator_nickname": "Nonso",
  "creator_username": "nonso",
  "creator_avatar_url": "https://...",
  "privacy_level_options": ["SELF_ONLY"],
  "comment_disabled": false,
  "duet_disabled": false,
  "stitch_disabled": true,
  "max_video_post_duration_sec": 600,
  "can_post": true,
  "reason": null,
  "audited": false
}
```
While `audited=false`, `privacy_level_options` is filtered to `["SELF_ONLY"]`. `can_post=false` (with `reason`) when TikTok reports the creator cannot post now.

## 14. Analytics export

### GET `/analytics/export?period=7d|30d|90d` — auth
`200 text/csv`, `Content-Disposition: attachment; filename="clippipeline-analytics-<period>.csv"`.
Columns: `post_id,title,platform,url,published_at,views,likes,comments,shares,saves,watch_time_seconds,captured_on,lifetime_views,lifetime_likes,lifetime_comments,lifetime_shares,lifetime_saves,lifetime_watch_time_seconds`.
One row per distribution that gained metrics in the window or was published in it. `views…watch_time_seconds` are the **gains in the window**
(same math as §5, so the columns sum to `/analytics/overview` `totals`); `lifetime_*` and `captured_on` come from the latest snapshot.

## 15. Compliance callbacks & public pages (C-01 … C-08)

| Method | Path | Notes |
|---|---|---|
| POST | `/api/meta/data-deletion` | form field `signed_request`; 200 `{"url":"https://clippipeline.nonsostudio.com/data-deletion/status?code=X","confirmation_code":"X"}`; 400 bad signature |
| POST | `/api/meta/deauthorize` | form field `signed_request`; 200 `{"ok":true}` |
| POST | `/api/webhooks/tiktok` | JSON event; signature header verified; 200 `{"ok":true}` |
| GET | `/privacy`, `/terms`, `/data-deletion`, `/data-deletion/status?code=`, `/delete-account` | public web pages (Blade) |

## 16. Defaults
Period default is `7d` everywhere (API, web, mobile).
