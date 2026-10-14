# Meta (Facebook + Instagram) Compliance Guide for ClipPipeline

Researched 2026-10-14 from official Meta pages only. Each requirement carries its source. Anything not confirmed on an official page is marked **UNVERIFIED**. Meta docs change often; re-check before submission.

Key sources:
- S1 Permissions reference: https://developers.facebook.com/docs/permissions
- S2 App Review submission guide: https://developers.facebook.com/docs/resp-plat-initiatives/individual-processes/app-review/submission-guide
- S3 Access levels: https://developers.facebook.com/docs/graph-api/overview/access-levels
- S4 Data deletion callback: https://developers.facebook.com/docs/development/create-an-app/app-dashboard/data-deletion-callback
- S5 IG content publishing: https://developers.facebook.com/docs/instagram-platform/content-publishing
- S6 IG container/Reels reference: https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/reference/ig-user/media
- S7 FB Page Reels publishing: https://developers.facebook.com/docs/video-api/guides/reels-publishing
- S8 IG media insights: https://developers.facebook.com/docs/instagram-platform/reference/instagram-media/insights/
- S9 Platform Terms: https://developers.facebook.com/terms/
- S10 Developer Policies: https://developers.facebook.com/devpolicy/
- S11 IG Platform requirements (Facebook Login): https://developers.facebook.com/docs/instagram-platform/instagram-api-with-facebook-login/overview
- S12 Business Verification: https://developers.facebook.com/docs/development/release/business-verification
- S13 App roles: https://developers.facebook.com/docs/development/build-and-test/app-roles
- S14 Facebook video insights: https://developers.facebook.com/docs/graph-api/reference/video/video_insights/
- S15 Facebook Login manual flow (deauthorize): https://developers.facebook.com/docs/facebook-login/guides/advanced/manual-flow
- S16 IG Login overview: https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login
- S17 App Review overview: https://developers.facebook.com/docs/resp-plat-initiatives/individual-processes/app-review

---

## 1. Scopes / permissions: minimum set and App Review justifications

### 1.1 Login type decision (Facebook Login vs Instagram Login)

| | Instagram API with Facebook Login (Facebook Login for Business) | Instagram API with Instagram Login (Business Login for Instagram) |
|---|---|---|
| Publishing perms | `instagram_basic`, `instagram_content_publish`, `pages_read_engagement` | `instagram_business_basic`, `instagram_business_content_publish` |
| Insights perms | `instagram_basic`, `instagram_manage_insights`, `pages_read_engagement` | `instagram_business_basic`, `instagram_business_manage_insights` |
| Facebook Page needed | Yes, IG account must be linked to a Page; user needs Page task (Content / Full control for publish, Insights for analytics) | No |
| Host | graph.facebook.com (+ rupload.facebook.com for resumable video) | graph.instagram.com |
| Resumable upload | Yes | No (Facebook Login only) |
| Insights webhooks | Yes | Not supported |
| Also publishes to Facebook Page Reels | Yes (same login) | No, Facebook Pages need Facebook Login anyway |

Sources: S5, S8, S11, S16.

**Recommendation: stay on Facebook Login.** ClipPipeline must publish Facebook Page Reels too, which requires Facebook Login and Page permissions (S7). One OAuth connect then yields both Page and linked IG account. Instagram Login would add a second OAuth flow, a second token type, and a second set of permissions to review, and could not do Facebook. Trade-off: users must have an IG professional account linked to a Facebook Page (S11). Instagram Login could be added later as an option for IG-only users (inference).

### 1.2 Minimum permission set

| Permission | Keep? | Why | Dependencies (S1) |
|---|---|---|---|
| `pages_show_list` | MUST | Page picker; dependency for all others | none |
| `pages_read_engagement` | MUST | Required by FB Reels publishing (S7) and by IG publish/insights with Facebook Login (S5, S8); reads Page content | pages_show_list |
| `pages_manage_posts` | MUST | Publish Reels to Page (S7) | pages_read_engagement, pages_show_list |
| `read_insights` | MUST | Page/reel insights (S14 lists `read_insights` + `pages_manage_engagement` for `/video_insights`) | pages_read_engagement, pages_show_list |
| `instagram_basic` | MUST | IG account ID/username, media list | pages_show_list (S1 also lists pages_read_user_content, see note) |
| `instagram_content_publish` | MUST | Publish IG Reels | instagram_basic, pages_read_engagement, pages_show_list |
| `instagram_manage_insights` | MUST | Per-post and account IG insights | instagram_basic, pages_read_engagement, pages_show_list |
| `business_management` | **DROP (recommended)** | See 1.3 | |
| `pages_manage_engagement` | CHECK | S14 says `/{video-id}/video_insights` needs `pages_manage_engagement` AND `read_insights`. Test whether you really need it; if the call fails without it, add it and a review entry | |

Notes:
- S1 states: "Only select permissions that your app needs to function as intended" (unneeded permissions are a common rejection reason). If a permission is unused for 90 days the user must re-grant it.
- S1 lists `pages_read_user_content` as a dependency of `instagram_basic`. UNVERIFIED whether it must be requested explicitly; test in dev mode.
- S5/S8: if the app user has a Business Manager role on the connected Page, Facebook Login apps also need `ads_management` and `ads_read` for publishing and insights calls. This is a real trap: users whose Page is owned by a Business portfolio may hit permission errors. Test with such an account. If it is a problem, you would need to request those two permissions as well (heavier review). Mark as risk.

### 1.3 Is `business_management` needed?

Official Allowed Usage (S1): "Manage business assets such as ad accounts, and claim ad accounts." Its screencast example is ad performance (impressions, spend, clicks). ClipPipeline does neither. Nothing in the publishing docs (S5, S7) or insights docs (S8, S14) lists `business_management` as required. **Conclusion: not needed per official docs; remove it** (requesting it invites rejection because the screencast cannot show ad-account use).

Caveat, UNVERIFIED on an official page: some developers report that Pages owned by a Business portfolio are not returned by `/me/accounts` without `business_management`. Test with a Page owned by a Business portfolio in dev mode (with the current scope set including it, then without). If Page listing breaks, you can fall back to Business-asset endpoints, but that needs this permission and an honest justification (listing Pages the user's business owns). Default plan: drop it.

### 1.4 Ready-to-paste App Review justification text (unique per permission; do not reuse wording, see S2)

**pages_show_list**
> ClipPipeline is a short-video publishing tool for creators and social media managers. After a user logs in with Facebook, we call /me/accounts to list the Facebook Pages they manage so they can choose which Page(s) to connect. Without this permission the user cannot select a destination Page and the app cannot function. We also use it to verify the user manages the chosen Page. It is a dependency of pages_read_engagement and pages_manage_posts.

**pages_read_engagement**
> After the user connects a Page, ClipPipeline reads the Page's basic metadata and its published videos/Reels so we can show a "Published" history inside the app, confirm that a Reel we uploaded went live, and display its status. We only read content of Pages the logged-in user administers. Meta also lists this permission as required for Reels publishing and for Instagram publishing/insights with Facebook Login. Without it the user would have no confirmation of what was posted.

**pages_manage_posts**
> ClipPipeline lets a user upload one vertical video and publish it as a Reel to the Facebook Page they connected. We call POST /{page_id}/video_reels (start, upload, finish) on the user's behalf only when the user presses "Publish" or has scheduled a post. We do not post anything automatically or without the user's explicit action. Without this permission the app's core feature (publishing to Facebook Page Reels) is impossible.

**read_insights**
> ClipPipeline shows each connected Page owner how their published Reels perform: views/plays, watch time, reach, and engagement, per post and over time, in an in-app analytics dashboard. We call the Page/video insights endpoints for Pages the user administers and display the returned metrics to that user. We do not combine this data across users or share it with third parties. Without it users could not see how their content performed.

**instagram_basic**
> When the user connects their Facebook Page, ClipPipeline looks up the Instagram professional account linked to it and reads the account ID, username, profile picture and the list of the account's media. We show the username/avatar in the account picker so the user can confirm which Instagram account they are publishing to, and we match published Reels to their insights. This is a prerequisite for instagram_content_publish and instagram_manage_insights.

**instagram_content_publish**
> ClipPipeline publishes a video that the user uploaded as an Instagram Reel on the user's own linked professional account. We create a media container (media_type=REELS), wait for status FINISHED, then call media_publish, only after the user explicitly taps Publish or at the time they scheduled. We respect the 100-posts/24h limit via content_publishing_limit. Without this permission the user cannot post to Instagram from ClipPipeline.

**instagram_manage_insights**
> ClipPipeline shows creators performance of their own Instagram Reels (views, reach, likes, comments, shares, saves, average watch time) and account-level metrics such as followers, in a per-post and per-account analytics dashboard. We request these insights only for the account the logged-in user connected, and display them only to that user. Without this permission the analytics feature cannot work.

Also required in the submission (S2): unique usage description per permission, one screen recording per permission, a successful API call per permission within the last 30 days, data handling questions, app icon 1024x1024, privacy policy URL, category, contact email, access instructions.

---

## 2. MUST-have UI elements

Meta publishes few mandatory UI strings for this use case. Exact wording requirements found:

- **Facebook Login button / connect action**: UNVERIFIED exact wording. Use "Continue with Facebook" or "Connect Facebook" (Meta's login-button guidance page was not retrievable). Do not alter the Facebook logo.
- **User control of publishing**: S10 sec 1.7: "Obtain consent before publishing content or acting on people's behalf." So: an explicit Publish button with a confirmation showing destination account(s) and the content; scheduled posts must be clearly shown and cancellable. No auto-posting.
- **Support contact**: S10 sec 1.8: provide an easily accessible way for people to request support or report issues. Add Help/Support link + email in app (web and Android).
- **Data deletion path**: in-app "Delete my data / Delete account" action plus the privacy policy explaining how (S9 sec 3.d.i.1 and 4.b: give Users an easy way to request deletion; available to all Users who can access the app).
- **Disconnect**: per-account "Disconnect" button that revokes/deletes stored tokens.
- **Reviewer access** (S2): reviewers need to reach the app; provide a test account / instructions in Platform Settings. Do NOT give personal Meta credentials; reviewers use Meta test accounts (S2). Make sure a freshly created account can sign up without a paywall or invite wall.
- **Permission rationale before login** (SHOULD; S9 3.b says make the reason for restricted data clear to the user): short text on the connect screen explaining what each permission is used for.
- **AI disclosure** (SHOULD): IG API supports `is_ai_generated` (S5); if ClipPipeline offers AI-generated clips, expose a toggle.
- **UI language**: S2 prefers English UI for the review recordings.
- **Branding in UI**: see section 7.

---

## 3. Required privacy policy / terms content

Sources: S9 sec 4 (Privacy Policy), S4, S1.

Privacy policy MUST (S9 sec 4):
- Be public, easily accessible, not geoblocked; URL listed in App Dashboard > Settings > Basic (4.a).
- Explain what data you process, how, and why (4.b).
- Explain how Users can request deletion of their data; this right must be offered to all Users who can access the app (4.b, S4).
- Not conflict with the Platform Terms; you may process data only as the policy describes (4.c-d).

Practical content list (derived):
- Data collected via Meta: Facebook user ID (app-scoped), name, Page IDs/names/tokens, IG account ID/username/profile picture, media IDs, insights metrics, follower count.
- Data from the user: uploaded videos, captions, schedules.
- Purposes: publish on user's behalf, show analytics, operate the service. State you do not sell, license or purchase Platform Data (S9 3.a.iv) and do not use it for profiling/ads/eligibility decisions (3.a.i-vi).
- Sharing: only service providers (hosting, storage, queue) and as legally required (S9 3.c). Access tokens must not be shared with anyone except a service provider that helps run the app (S9 6.a.iv).
- Retention periods and deletion process, including Meta-initiated deletion callback.
- Contact (email) for privacy and deletion requests.
- How to revoke access: in ClipPipeline (Disconnect) and in Facebook Settings > Apps and Websites (S4), Instagram Settings > Apps and Websites.
- Security: S9 6.a.i requires industry-standard safeguards; state them (encryption in transit; encrypt tokens at rest).
- Incident reporting: S9 6.b obliges you to report unauthorized processing "as soon as practicable". Have an internal procedure.

Terms of Service: Meta does not mandate specific ToS text beyond not conflicting with the Platform Terms (S9 4.d is about the privacy policy; ToS content for apps is UNVERIFIED). Recommended: user is responsible for content/rights (copyright), compliance with Instagram/Facebook terms, may disconnect any time, you may suspend publishing.

URLs to publish (on clippipeline.nonsostudio.com): `/privacy`, `/terms`, `/data-deletion` (instructions page), `/data-deletion/status?code=...`.

---

## 4. Data deletion, revocation, retention; what we must build

### 4.1 Data deletion request callback (S4)
- Trigger: user removes the app in Facebook Settings > Apps and Websites and uses "Send Request".
- Request: HTTPS POST with form param `signed_request` = `<encoded_sig>.<payload>`, both base64url.
- Parse/verify:
  1. Split on first `.` into `encoded_sig` and `payload`.
  2. base64url-decode both (`-`->`+`, `_`->`/`, pad).
  3. JSON-decode payload: `{ "algorithm": "HMAC-SHA256", "expires": ..., "issued_at": ..., "user_id": "<ASID>" }`.
  4. Compute HMAC-SHA256 over the raw `payload` string (the encoded one) with App Secret; compare (constant-time) to decoded sig. Mismatch: log and reject.
  5. `user_id` is the app-scoped ID. Look up users by their stored Facebook ASID.
- Required JSON response (HTTP 200): `{ "url": "https://clippipeline.nonsostudio.com/data-deletion/status?code=<code>", "confirmation_code": "<alphanumeric>" }`.
- The status URL must give a human-readable explanation of the request state; if you refuse, give a legitimate justification (S4).
- Begin deleting all of the app's data about that user (S4). No fixed deadline is stated: "promptly" (S4 FAQ, S9 3.d). Proof of deletion is not required (S4 FAQ) but retain internal logs of the deletion job.
- Meta also emails/alerts every 21 days with IDs to delete; list downloadable in app's advanced settings "User Data Deletion Requests" for 60 days (S4 FAQ). Process any IDs from the list too (backstop in case callback failed).
- IDs not found in your records may be ignored (S4 FAQ).

### 4.2 Callback URL vs instructions URL
The Dashboard requires one of the two (S4 FAQ: "you must specify either a data deletion callback instruction URL or a callback URL in Basic Settings"). The exact request format of the "instructions URL" is not defined on the page: it is simply a human page telling users how to delete. **Recommendation: implement the callback** (Data Deletion Request URL) AND publish an instructions page that the privacy policy links to. The privacy policy must explain deletion either way (S4, S9 4.b). Whether providing both fields at once is accepted by the Dashboard: UNVERIFIED; if only one allowed, use the callback.

### 4.3 Deauthorize callback (S15)
- Meta pings the Deauthorize Callback URL when a user uninstalls/removes the app from Facebook, configured in the App Dashboard.
- S15 does not document the payload. UNVERIFIED on official docs; historically it is a POST with the same `signed_request` format (user_id), no response body required. Implement it using the same verifier.
- Action: mark the Facebook connection revoked, delete/invalidate stored tokens, stop scheduled publishes for that user, show "reconnect" in the UI. Deauthorize is NOT a deletion request; content/analytics may be kept until the user (or deletion callback) asks, unless your policy says otherwise.

### 4.4 Retention rules
- S9 3.d.i.2: delete Platform Data when no longer needed for a legitimate purpose, when the user requests deletion or leaves, when you stop operating the product, or Meta requests. No fixed retention period is given by Meta (UNVERIFIED that any "90 days" rule exists; none found).
- Aggregated/de-identified data is excluded from deletion duty (S9 3.d.i.2.d).
- Insights data: Meta serves up to 2 years history (S8, S14); data may lag 48h (S8). Cache only what you need; refresh rather than hoard.
- Unused-permission cleanup: S9 7.e.iii permissions unused 28 days may be removed; S1 says 90 days unused requires re-grant. Keep calling each permission regularly so it is not auto-removed (inference).
- Annual Data Use Checkup required for Advanced Access (S3).
- Audit: Meta may audit with 10 business days' notice (S9 7.c).
- Tokens: treat as secrets; encrypt at rest; delete on disconnect/deauth/deletion.

### 4.5 What we must build
1. `POST /api/meta/data-deletion` (public, CSRF-exempt): verify signed_request, create deletion job with confirmation_code, return `{url, confirmation_code}`.
2. `GET /data-deletion/status?code=` page: statuses Received / In progress / Completed (+ date), with no login required and no PII.
3. `POST /api/meta/deauthorize` (public, CSRF-exempt): verify signed_request, revoke connection.
4. Deletion job: delete tokens, Page/IG account links, stored Meta profile data, insights snapshots, post records tied to that FB user, and (per policy) uploaded media. Keep minimal audit record (code, hashed ASID, timestamps).
5. In-app account deletion + per-connection disconnect.
6. Operational: periodic check of the "User Data Deletion Requests" list (every 21 days) as backstop.

---

## 5. Review process and screencast shot list

### 5.1 Step-by-step (S2, S3, S12, S17)
1. App type: Business. Business apps get Standard Access automatically (S3).
2. Complete Business Verification: Settings > Basic > Verification, connect app to a business portfolio; a Business Admin completes verification in Business Manager (S12). Required for Advanced Access (needed because other businesses/users will connect; S3, S12). Documents: not listed on S12 (see Meta Business Help Center "About Business Verification"; UNVERIFIED). No timeline given.
3. Fill in Settings > Basic: 1024x1024 app icon, privacy policy URL, data deletion callback or instructions URL, app category, contact email, terms URL (S2).
4. In dev mode, with roles (Admin/Developer/Tester) make at least one successful API call per permission within 30 days of submission (S2). Run the full flows.
5. App Review > Permissions and Features: add each permission and feature.
6. Answer data handling questions.
7. App Settings and App Verification sections: state whether users sign in with Meta authentication; in Platform Settings describe how reviewers can access the app (S2). Provide test account that works with the reviewers' own Meta test accounts. Make sure the Page and IG professional account used for demo are ready (S2: do not give personal credentials).
8. Per permission: unique usage description (section 1.4) + screen recording.
9. Submit; accept Platform Onboarding Terms. Decision usually within about a week (S2).
10. Do not change app settings after submission (may trigger re-review) (S2).
11. After approval switch app to **Live** mode (S2; S3 says Live needed before non-role users get Advanced permissions).
12. Annually: Data Use Checkup (S3).

If reviewers cannot access your app, the entire submission is rejected; if they cannot test a permission, that permission is not approved (S17).

### 5.2 Recording rules (S2)
- 1080p or better; monitor width 1440 or less.
- Show a user granting each permission, then how the app uses each.
- English UI if possible; add captions and tooltips explaining non-obvious UI.
- Record only the app (full screen or window); use the mouse rather than keyboard; enlarge cursor.
- No audio.
- Any permission without a recording is not approved.
- For Android app: record the device (screen mirror) in the same spirit; web is easier for reviewers, so make sure the web flow works end to end (reviewers must reach the app; web is the safest). UNVERIFIED whether the mobile app alone is acceptable; the web app URL is the recommended review target.

### 5.3 Shot list (per S1 screencast guidance)
Shared opening (use once, referenced in all): Open clippipeline.nonsostudio.com logged out, click "Connect Facebook", show the Facebook Login dialog with every requested permission listed, click Continue, grant; return to the app via the callback; show the Page and Instagram account picker.

- **pages_show_list**: Full login flow; the list of the user's Pages in the picker; select one; show it saved as connected.
- **pages_read_engagement**: Connected Page screen showing Page metadata; Published history listing the Page's Reels read from the API; click one and show its content loading.
- **pages_manage_posts**: Upload a vertical video, caption, tick Facebook Page, click Publish, show progress, then open the Page on facebook.com showing the new Reel. Optionally show delete or edit if you offer them.
- **read_insights**: Open a published Reel's analytics, show FB metrics (plays, watch time, reach), and Page-level dashboard.
- **instagram_basic**: Show login with selecting the IG account; show username, avatar, and media list inside the app.
- **instagram_content_publish**: Same upload, tick Instagram, Publish; show container processing then success; open Instagram app/web profile showing the Reel. Caution: S1 example shows a photo post; the reviewer wants a feed-publishing demo, but ours is Reels; say clearly in captions "Reel (media_type=REELS)". Mention share_to_feed value.
- **instagram_manage_insights**: Per-post IG insights (views, reach, likes, comments, shares, saves, avg watch time) and account dashboard (followers); S1 additionally shows "insights for another professional account" only if you use Business Discovery. We do not, so state in notes that we only show the user's own account.
- Closing: Disconnect button, deletion instructions page, privacy policy link.

---

## 6. Limits that affect design

### Instagram (Facebook Login)
- Publishing cap: 100 API-published posts per IG account per rolling 24h; enforced at `media_publish` (S5, S6). The carousel section of the same page says 50; treat 50 as the conservative number for carousels only (conflict unreconciled by Meta). Check `GET /<IG_ID>/content_publishing_limit` before scheduling and enforce in-app (S5 recommends).
- Container creation: 400 containers per rolling 24h per account (S6). Containers expire after 24h (S5, S6).
- Poll `GET /<container>?fields=status_code` about once a minute, max 5 minutes; statuses EXPIRED, ERROR, FINISHED, IN_PROGRESS, PUBLISHED (S5). Videos can take longer; fail gracefully and retry later.
- General rate limit: Business Use Case limit, 4800 x number of impressions per 24h per app/app-user pair (S11).
- Media must be reachable at a public URL when publishing (Meta fetches it) or use resumable upload via rupload.facebook.com (Facebook Login only) (S5). Use resumable upload or signed public URLs with a lifetime of at least the container processing time.
- Reels video specs (S6): MP4/MOV, moov atom at front, no edit lists; H.264 or HEVC, progressive, closed GOP, 4:2:0; AAC audio max 48 kHz mono/stereo, 128 kbps; frame rate 23-60 fps; max width 1920; aspect 0.01:1 to 10:1 (9:16 recommended); duration 3 s to 15 min; file 300 MB max; video bitrate VBR max 25 Mbps.
- Cover: `cover_url` JPEG, 8 MB max, sRGB; or `thumb_offset` ms (S6).
- Caption max 2,200 chars, 30 hashtags, 20 mentions (S6).
- `share_to_feed` true/false; no guarantee of Reels tab placement (S6).
- Reels cannot be in carousels (S6). Page needing Page Publishing Authorization (PPA) cannot publish until complete (S5).
- Permission need: user must have Page task Content or Full control to grant instagram_content_publish; Insights task for instagram_manage_insights (S11).

### Facebook Page Reels (S7)
- Flow: `POST /{page_id}/video_reels upload_phase=start` -> upload to rupload.facebook.com (`file_url` header or binary with offset/file_size) -> `GET /{video_id}?fields=status` -> `POST ... upload_phase=finish` with `video_state` PUBLISHED / DRAFT / SCHEDULED.
- Cap: 30 API-published posts per Page per rolling 24h (S7).
- Scheduling: more than 10 min ahead and within 29 days (S7).
- Specs: MP4 recommended; 9:16 (supported 16:9 to 9:16); 1080x1920 recommended, minimum 540x960; 24-60 fps fixed progressive; duration 3-90 s; H.264/H.265 (VP9/AV1 also), 4:2:0, closed GOP 2-5 s; AAC-LC 48 kHz stereo 128 kbps+; file size not stated (S7).
- Audience: Public only. Reels only to Pages (S7).
- Errors: 1363040 aspect, 1363127 resolution, 1363128 duration, 1363129 frame rate (S7).
- Hosted file URLs must not block the "Facebook external hit" user agent, must not be fbcdn, must not need auth (S7).
- Common-denominator transcode: 1080x1920, 30 fps CFR, H.264 High, AAC-LC 48 kHz stereo 128k+, MP4 faststart, 3-90 s (so one file works for both; IG Reels allow longer, but FB caps at 90 s, so clips over 90 s cannot go to Facebook Reels). Validate in UI before upload and show per-platform errors.

### Insights
- IG media metrics for REELS (S8): `views`, `reach`, `likes`, `comments`, `shares`, `saved`, `total_interactions`, `ig_reels_avg_watch_time`, `ig_reels_video_view_total_time` (marked "in development"), plus `crossposted_views`, `reels_skip_rate` (in development), `reposts`, `facebook_views`, `total_views`.
- `impressions` deprecated for media created after 2 Jul 2024 (S8). Older `plays`, `clips_replays_count` deprecated (search result of S8, partial verification).
- Empty data set (not 0) when unavailable; data may lag up to 48h; stored up to 2 years; reach estimated; organic only (S8). Request metrics separately if they differ in breakdown support (S8).
- Account-level IG insights (follower count, reach, etc.): official reference page returned 404 during research; metric names and the 100-follower minimum are UNVERIFIED here. Re-check https://developers.facebook.com/docs/instagram-platform/reference/instagram-user/insights
- Facebook video/Reel insights (S14): `/{video-id}/video_insights` with `read_insights` (+ `pages_manage_engagement` per page text); Reel metrics include `blue_reels_play_count`, `fb_reels_total_plays`, `fb_reels_replay_count`, `post_impressions_unique`, `post_video_avg_time_watched`, `post_video_retention_graph`; most are lifetime-only; two years of data; need Page token from a user with ANALYZE task. Page follower metrics: UNVERIFIED (not fetched).
- Metric names change; centralize in a config map and handle missing metrics gracefully.

### Tokens
- Long-lived user tokens (about 60 days) and Page tokens derived from them: UNVERIFIED on a page fetched during this research. Design for expiry: detect error code 190, flag "reconnect required", notify the user.

### Dev mode
- Apps in development mode only work for users with roles (S13). Tester role can test permissions but cannot edit settings or see insights; testers are intended to be your employees or people acting for you (S13). Limits: up to 50 testers for most unlinked apps; up to 500 combined analytics users/testers when linked to a verified Business (S13). Add via App Dashboard > App roles > Roles. Consumer testers accept under Facebook Settings > Apps and Websites > Requests (S13).
- Instagram test accounts must also be added as Instagram Testers or have an app role (UNVERIFIED on a fetched page).

---

## 7. Branding rules

- App name/icon/description must follow Meta Advertising Standards (S10 sec 1.3). Trademark/logo violations in app name or icon are a listed rejection reason (S2). Do not put "Facebook", "Instagram", "Insta", "FB", "Meta" in the app name or icon. "ClipPipeline" is fine.
- Logos: follow Meta Brand Resource and Permissions Center guidelines and Logos and Trademarks requirements (S10 sec 2.4). The brand pages could not be fetched during this research (404), so exact rules are UNVERIFIED. General practice: use official unmodified logo assets only, no altered colors, no implying endorsement, use text like "Facebook" / "Instagram" nominatively (e.g., "Publish to Instagram Reels").
- Be honest about your relationship with Meta; no implication of partnership or endorsement (S10 sec 2.5).
- Do not state you are "official". Add footer: "Facebook and Instagram are trademarks of Meta Platforms, Inc. ClipPipeline is not affiliated with or endorsed by Meta." (recommended; not required wording; UNVERIFIED).
- Do not scrape or display Meta data in ways implying it is from Meta.

---

## 8. Checklists

### 8.A CODE checklist (build agents)

| # | Item | Level | Area |
|---|---|---|---|
| 1 | Request scopes: pages_show_list, pages_read_engagement, pages_manage_posts, read_insights, instagram_basic, instagram_content_publish, instagram_manage_insights. Remove business_management (verify in dev). Keep scope list in one config | MUST | backend |
| 2 | Callback route stays exactly `/integrations/callback/instagram` (do not change) | MUST | web/backend |
| 3 | `POST /api/meta/data-deletion`: verify signed_request HMAC-SHA256 with app secret (constant-time), parse `user_id`, enqueue deletion, return `{url, confirmation_code}` | MUST | backend |
| 4 | Public status page `/data-deletion/status?code=` human-readable, no auth | MUST | web/backend |
| 5 | `POST /api/meta/deauthorize`: verify signed_request, revoke connection, delete tokens, cancel scheduled publishes | MUST | backend |
| 6 | Exempt both callbacks from CSRF/auth middleware; accept form-encoded body; HTTPS only | MUST | backend |
| 7 | Deletion job removes tokens, Meta IDs, account links, insights, post records, media; audit log w/o PII | MUST | backend |
| 8 | In-app: per-connection Disconnect and Delete account / Delete my data | MUST | web + mobile |
| 9 | Explicit Publish confirmation showing destination accounts; no silent auto-posting; scheduled-post visibility and cancel | MUST | web + mobile |
| 10 | Public pages: /privacy, /terms, /data-deletion (instructions), support contact link in footer/menu; link privacy policy from login/connect screens and in the mobile app | MUST | web + mobile |
| 11 | Pre-login permission rationale text on connect screen | SHOULD | web + mobile |
| 12 | Encrypt Meta tokens at rest; never log tokens, app secret, or signed_request; never send tokens to third parties | MUST | backend |
| 13 | Pre-publish video validation/transcode to common spec (1080x1920, 24-60 fps (use 30), H.264/AAC 48 kHz, MP4 faststart, 3-90 s FB / 3 s-15 min IG, IG <=300 MB); per-platform errors for the FB 90s cap | MUST | backend |
| 14 | IG flow: create container (REELS) -> poll status_code ~1/min up to 5 min (continue async beyond) -> media_publish; handle EXPIRED/ERROR; reuse containers within 24h | MUST | backend |
| 15 | FB flow: video_reels start -> upload -> poll status -> finish; resumable via offset on interrupt; map error 1363040/1363127/1363128/1363129 to friendly messages | MUST | backend |
| 16 | Enforce publishing caps in app: IG 100/24h (query content_publishing_limit), FB 30/24h per Page; 400 containers/24h; schedule time >10 min and <29 days (FB) | MUST | backend |
| 17 | Handle token expiry/invalid (code 190): mark "reconnect needed", surface in UI | MUST | backend + web + mobile |
| 18 | Insights: metric map in config; handle empty data sets (not zero); 48h lag; no `impressions` for new media; fallback when metric errors; request incompatible metrics separately | MUST | backend |
| 19 | Handle Page with Business Manager role: detect permission errors and show actionable message (ads_management/ads_read caveat) | SHOULD | backend + web |
| 20 | Regular API calls so each permission is exercised (avoid unused-permission removal at 28/90 days) | SHOULD | backend |
| 21 | Poll the "User Data Deletion Requests" list every 21 days (manual runbook or script) | SHOULD | backend |
| 22 | `is_ai_generated` toggle for AI-created clips | SHOULD | web + mobile + backend |
| 23 | Use only official, unmodified Facebook/Instagram logos; add non-affiliation footer | SHOULD | web + mobile |
| 24 | Review-friendly demo mode: stable test account path, English UI, seeded sample video, no paywall | MUST | web |
| 25 | Cover image / `thumb_offset` selection and `share_to_feed` option | SHOULD | web + mobile |
| 26 | Incident response contact/runbook (S9 6.b) | SHOULD | backend (ops) |

### 8.B CONSOLE checklist (Nonso, Meta App Dashboard: developers.facebook.com/apps)

1. App type Business; set app mode: Development until review done, then **Live**.
2. Settings > Basic: add 1024x1024 icon; app name "ClipPipeline" (no Meta trademarks); category; contact email.
3. Settings > Basic URLs:
   - Privacy Policy URL: `https://clippipeline.nonsostudio.com/privacy`
   - Terms of Service URL: `https://clippipeline.nonsostudio.com/terms`
   - App domain: `clippipeline.nonsostudio.com`
   - Data Deletion Request URL (callback): `https://clippipeline.nonsostudio.com/api/meta/data-deletion` (proposed path; match what the backend builds)
   - (if instructions field offered instead/in addition) Data deletion instructions URL: `https://clippipeline.nonsostudio.com/data-deletion`
   - Deauthorize Callback URL (Facebook Login > Settings): `https://clippipeline.nonsostudio.com/api/meta/deauthorize` (proposed)
4. Facebook Login (for Business) > Settings: Valid OAuth Redirect URI: `https://clippipeline.nonsostudio.com/integrations/callback/instagram` (exact, unchanged). Turn on Client OAuth login and Web OAuth login. Enforce HTTPS.
5. Settings > Basic > Verification: connect to Business portfolio; Business Admin completes Business Verification in Business Manager (S12).
6. Roles: add Nonso as Admin; add testers/devs; each tester needs a Page with IG professional account linked. Accept IG tester invites if needed (UNVERIFIED).
7. Prepare reviewer test setup: a Page + linked IG professional account, a ClipPipeline test user with instructions in Platform Settings (no personal Meta credentials).
8. In dev mode, run the whole flow and confirm one successful call per permission (within 30 days before submit).
9. App Review > Permissions and Features: request the 7 permissions in 1.2 (not business_management); for each paste the text from 1.4 and upload a screen recording per 5.3.
10. Answer data handling questions; App Verification section; submit; accept Platform Onboarding Terms.
11. After approval: switch to Live; verify with a non-role user; add Data Use Checkup to the yearly calendar.
12. Advanced settings > User Data Deletion Requests: check every 21 days for IDs.
13. Add the Android package/key hashes only if using native Facebook SDK login (UNVERIFIED; web OAuth flow does not need them).
