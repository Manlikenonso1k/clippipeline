# USER-SIM report — creator with TikTok, YouTube, Facebook Page and Instagram connected

Branch `feat/parity`, 2026-10-14. Backend journeys: `tests/Feature/UserJourneyTest.php` (run `php artisan test --filter=UserJourney`).
Mobile walk: code read of `src/app/(tabs)/*`, `src/app/post/*`, `src/app/login.tsx`, `src/api/hooks.ts`, `src/components/global-sheets.tsx`.

## Verdict

**Mostly good, and it works end to end.** The main loop works: sign in → see 4 connected accounts → post one clip to all 4
platforms with the TikTok/YouTube compliance form → watch each platform go live with working links and a thumbnail →
get notified → retry a failure → sync analytics → every screen and the CSV show the same numbers for a new post → manage
account / disconnect / delete.
Validation messages are clear and a rejected upload stores nothing.

It is **not demo-ready for a TikTok reviewer yet**. The TikTok post form shows no video preview, which our own research marks MUST.
Four real bugs remain, one of them a sign-in failure. The mobile app also has a few labels that make the numbers look wrong.

## Journey results

12 journeys: 9 pass, 3 marked incomplete with `BUG:` (the suite stays green). Every platform call goes through one fake router
that answers like the real API. Any call it does not recognise fails the test, so there are no stray requests.

| # | Journey | Result | Evidence |
|---|---|---|---|
| a | First open: `/me` (plan free 0/10, sync idle, flags, prefs), `/social-accounts` 4 active in order with no tokens, overview/platforms/timeseries/top-posts/posts/notifications all zero or empty | PASS | `test_a_first_open_…` |
| b | creator-info (unaudited → `["SELF_ONLY"]`) → `POST /posts` multipart exactly like `buildPostForm()` to all 4 → 4 jobs queued, none run in the request → polling shows processing ×3 then published → YT `/shorts/ID` (private), FB `/reel/ID`, IG permalink, TikTok profile link (SELF_ONLY), thumbnail from the first platform, 4 notifications + Expo push, clips_used=1 | PASS | `test_b_post_to_all_four_…` |
| b2 | Scheduled post: status `scheduled`, nothing queued, `posts:dispatch-scheduled` after the time queues the jobs | PASS | `test_b2_scheduled_post_…` |
| c | IG container `ERROR` → distribution `failed` with "Instagram could not process the video: Error: The video aspect ratio is not supported. Use 9:16." → post `partial` → "Post failed to publish" in `/notifications` with postId → 409 on retrying a published one → retry → published, attempts=2 → another user gets 404 | PASS | `test_c_instagram_container_error_…` |
| d | Audited TikTok + public post, `analytics:sync` → overview, platforms, timeseries (all + tiktok), top-posts, `/posts/{id}`, `/posts/{id}/analytics` and CSV all agree (2,700 views, per-platform splits); sync badge turns `active`; a second day with the same lifetime counters adds 0 | PASS | `test_d_analytics_sync_numbers_agree_…` |
| d2 | Unaudited TikTok (SELF_ONLY): `external_id` = publish_id, so the sync gets no stats → TikTok analytics stay 0 | PASS (known limitation, see R-3) | `test_d2_unaudited_tiktok_…` |
| d3 | CSV for 7d vs Insights 7d for a clip published 10 days ago | **INCOMPLETE — BUG-2** | `test_d3_csv_export_…` |
| d4 | Connect tab "synced <date>" moves after a sync | **INCOMPLETE — BUG-3** | `test_d4_connect_tab_synced_date_…` |
| e | FB > 90 s, TikTok privacy not offered, music consent 0, branded + Only me, YT guidelines 0, schedule in the past, TikTok needs re-auth → clear 422 per field; no Post row created, no job queued | PASS | `test_e_validation_messages_are_clear` |
| f | Disconnect TikTok (revoke POST to TikTok with client_key + token, row gone, creator-info 404, a second tap gives 404) → reconnect start URL (correct callback, `video.publish`, no `video.upload`; Meta has `instagram_content_publish`) → preferences PATCH + 422 on a bad platform → rename + 422 on an empty name → read-all → delete post (file removed, nothing deleted on platforms) → `DELETE /me` (422 without "DELETE"; 202; Google + Meta revoked; social/device/Sanctum tokens gone; bearer token gives 401 at once; purge job removes user, posts, distributions, analytics, notifications, files) | PASS | `test_f_disconnect_reconnect_…` |
| g | Google broker: start → callback → `clippipelinemobile://auth?status=ok&code=` → exchange → token works on `/me` and `/social-accounts`; a second exchange gives 422; cancelling gives an error deep link | PASS (returning Google user) | `test_g_google_sign_in_broker_…` |
| g2 | Google sign-in when the email already has an account (signed up with email/password or Facebook) | **INCOMPLETE — BUG-1** | `test_g2_google_sign_in_links_…` |

## Bugs

| ID | Sev | Where | Repro / effect |
|---|---|---|---|
| BUG-1 | **High** | `app/Services/Auth/SocialUserResolver.php:17` (also `app/Http/Controllers/Api/AuthController.php:34`, `app/Http/Controllers/SocialAuthController.php:42`) | Create a user with email X and no google_id, then sign in with Google using email X. `firstOrCreate(['google_id'=>…])` inserts a second row with the same email, the unique index fails, and the app gets `clippipelinemobile://auth?status=error&message=Could not sign you in with Google.` Facebook already links by email; Google must do the same. A creator who registered on the web cannot use "Continue with Google" on the phone. |
| BUG-2 | Medium | `app/Services/Analytics/ExportService.php:34` | The export only includes distributions **published inside the window** and writes **lifetime** counters. Insights shows views **gained** in the window across all posts. A clip published 10 days ago that gained 500 views this week: Insights 7d = 500, CSV 7d = 0 rows. The file you export from Insights does not match Insights. |
| BUG-3 | Low | `app/Services/Analytics/AnalyticsSync.php:216` (only `BaseConnector.php:39` sets `last_synced_at`) | The sync updates `follower_count` but never `social_accounts.last_synced_at`. The Connect tab (`connect.tsx:388`) keeps showing "synced <connect date>" forever, which contradicts "Auto-sync active" on Home. |
| BUG-4 | Low | `app/Services/Account/MeService.php:47` vs `app/Services/PostUploadService.php:35` | `plan.clips_limit` (free = 10/month) is shown on Profile but never enforced by `POST /posts`. Deleting posts also lowers `clips_used`. Either enforce it with a 422 that has a clear message, or stop showing it as a limit. |

## UX issues (ranked)

1. **No video preview before posting** (`src/app/post/new.tsx:354-372`, confirm sheet `:779-790`). The form shows only the file name, size and duration. TikTok CSG requires a preview (docs/compliance/tiktok.md:98). Add a thumbnail or inline player in the TikTok section and in the confirm sheet.
2. **Publish is not disabled when disclosure is on but no box is ticked** (`new.tsx:766-768`). The check only runs on tap (`:263`). TikTok expects the button to be disabled in that state.
3. **A partial failure is hidden from the "Failed" tab** (`src/app/(tabs)/posts.tsx:105-145`). The IG failure post appears only under All, and the Failed count stays 0. This matches the contract, but the creator looking for "what failed" sees nothing. Count `partial` under Failed, or add a "Needs attention" chip.
4. **Labels make numbers look wrong.** Profile "Syndicated" and "Total Views" (`profile.tsx:123-125`) and Home "Total Views" (`dashboard.tsx:215`) are 7-day values with no period label. A creator with 20 clips sees "Syndicated 1". Connected count reads "4 of 4 channels" (Home), "3 of 3 Connected" (Connect) and "Platforms 4" (Profile).
5. **"Re-authorize" is offered for every failure** (`src/app/post/[id].tsx:349-360`), including the IG aspect-ratio error, where re-auth cannot help. Pressing Retry on the same file will fail again. Show re-auth only for auth errors, and add a hint for format errors.
6. **TikTok analytics never appear while unaudited.** `[id].tsx:404` says "No analytics yet — stats appear after the first sync", which is never true for SELF_ONLY posts. Say "TikTok stats are available once posts are public."
7. **No unread badge on the bell** (`dashboard.tsx:167`, `posts.tsx:189`). Unread only shows inside the sheet. A 4-platform post also creates 4 separate "Clip published" notifications and pushes (`app/Models/PostDistribution.php:52-83`), which is noisy.
8. **Confirm sheet copy:** a Facebook Page name gets an "@" prefix ("@Nonso Clips Page", `new.tsx:797`). The TikTok row prints the handle twice ("@nonso • @nonso", `:797-800`).
9. **The privacy note blames the audit even when the creator chose private** (`src/components/post-utils.ts:80`).
10. **Wrong identity on Home and Profile:** they show the handle of whichever account comes first (`dashboard.tsx:91`, `profile.tsx:114`), so the header changes when accounts change.
11. **"Loading your TikTok settings…" appears as a red error** if the creator taps Publish early (`new.tsx:260`).
12. **TikTok sign-in creates a separate user.** TikTok returns no email, so a Google-registered creator who taps "Continue with TikTok" on a new phone lands in an empty account with no connections. Explain this on the login screen, or offer "link accounts" in Profile.
13. **Home shows 0 followers before the first sync** while Connect shows 10,400 (overview `followers.total` comes only from snapshots). Not shown on mobile today, but it shows on any screen that uses `followers`.

## Reviewer-risk notes (watching a demo)

- **TikTok:**
  - Covered: creator nickname and avatar, privacy with no default, interactions start off and are disabled when the creator turned them off, disclosure strings, the Music/Branded consent line in the confirm step, "may take a few minutes", the unaudited SELF_ONLY notice, and server enforcement (T-09).
  - **Risk:** the missing video preview (UX-1) and the Publish button not being disabled for disclosure (UX-2) are both items reviewers check.
  - **R-3:** SELF_ONLY posts have no public id, so TikTok analytics and "Open on TikTok" stay at profile level during the demo. Say so in the demo script.
  - The "Imported from TikTok" source label and the "Cross-Post Boost" card frame TikTok as the origin. That is fine.
- **YouTube:**
  - Covered: privacy choice, guidelines checkbox (server-enforced), ToS and Google Privacy links on Connect and the post form, forced private with the notice.
  - **Risk:** the Insights "Cross-Post Boost" (`insights.tsx:231`, `:486-497`) is a ratio built from YouTube views, which is a derived metric. COMPLIANCE A-07 allows only sums and averages. Hide it or exclude YouTube. Its chevron (`:497`) also suggests a tap that does nothing.
- **Meta:**
  - Covered: disconnect explains that both FB and IG go and that tokens are revoked, the revoke is verified, data deletion and deauthorize callbacks exist, and the IG publish cap is checked.
  - Demo risk: an IG account without a linked business account shows "Error" with a reason. Make sure the demo account is linked.
- **General:**
  - Delete account uses typed DELETE, and the token stops working at once.
  - Privacy and Terms links are present.
  - The non-affiliation line is on Profile.
  - No SOC2 claim.

## Not covered / notes

- Real OAuth consent screens, real uploads and Expo push delivery are faked by design. Nothing touched production, and no seeding or migrations ran on a real database (in-memory sqlite only).
- Other agents' test runs share `storage/framework/testing/disks/public`, so the journeys check specific file paths instead of `allFiles()`.
