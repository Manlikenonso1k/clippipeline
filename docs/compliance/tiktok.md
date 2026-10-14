# TikTok compliance brief for ClipPipeline (R1)

Researched 2026-10-14 from official TikTok pages only. Each requirement carries its source. Anything not on an official page is marked UNVERIFIED. TikTok pages change often; re-check the Content Sharing Guidelines before the audit submission.

Source key:
- [CSG] https://developers.tiktok.com/doc/content-sharing-guidelines
- [DP-START] https://developers.tiktok.com/doc/content-posting-api-get-started
- [DP-REF] https://developers.tiktok.com/doc/content-posting-api-reference-direct-post
- [CI] https://developers.tiktok.com/doc/content-posting-api-reference-query-creator-info
- [UP] https://developers.tiktok.com/doc/content-posting-api-reference-upload-video
- [MEDIA] https://developers.tiktok.com/doc/content-posting-api-media-transfer-guide
- [STATUS] https://developers.tiktok.com/doc/content-posting-api-reference-get-video-status
- [VLIST] https://developers.tiktok.com/doc/tiktok-api-v2-video-list
- [VOBJ] https://developers.tiktok.com/docs/en/tiktok-api-v2-video-object
- [UINFO] https://developers.tiktok.com/doc/tiktok-api-v2-get-user-info
- [SCOPES] https://developers.tiktok.com/doc/tiktok-api-scopes
- [LOGIN] https://developers.tiktok.com/docs/en/login-kit-web
- [TOKEN] https://developers.tiktok.com/doc/oauth-user-access-token-management
- [RATE] https://developers.tiktok.com/doc/tiktok-api-v2-rate-limit
- [HOOKS] https://developers.tiktok.com/doc/webhooks-overview
- [EVENTS] https://developers.tiktok.com/doc/webhooks-events
- [REVIEW] https://developers.tiktok.com/docs/en/app-review-guidelines
- [BRAND] https://developers.tiktok.com/doc/getting-started-design-guidelines
- [DEVTERMS] https://www.tiktok.com/legal/page/global/tik-tok-developer-terms-of-service/en

---

## 1. Scopes and permissions

### Minimum set
| Scope | Needed? | Why |
|---|---|---|
| `user.info.basic` | MUST | Connect flow: open_id, display_name, avatar_url. [UINFO] |
| `video.publish` | MUST | Direct Post, and also required to call creator_info query. [DP-REF][CI] |
| `video.list` | MUST (for analytics) | Lists the user's public videos with per-video counts. [VLIST] |
| `user.info.stats` | MUST if we show follower counts | `follower_count`, `following_count`, `likes_count`, `video_count` live ONLY under this scope. Not currently requested. [UINFO][SCOPES] |
| `video.upload` | REMOVE unless we ship an inbox/draft fallback | Upload (inbox) flow only. [UP] |

Review rule: "Request only the permissions your app needs. Don't request extra scopes", and every selected scope must appear in the demo video or review is delayed. [REVIEW]

Recommendation: drop `video.upload` (we use Direct Post; not demonstrating it risks delay/rejection), add `user.info.stats`. If product wants an "send to TikTok inbox as draft" option for unaudited/failed cases, keep `video.upload` and demo it.

### Analytics: what the API gives and does not
Video object fields: `id, create_time, cover_image_url (expires 6h), share_url, video_description, duration, height, width, title, embed_html, embed_link, like_count, comment_count, share_count, view_count, is_aigc`. [VOBJ]
- NOT available on any page checked: saves/collects, watch time, average watch time, reach, follower growth history. Do not promise these for TikTok. (Absence from the official field list; UNVERIFIED that no other endpoint exists.) Follower history must be built by us by snapshotting `follower_count` daily.
- `/v2/video/list/`: `max_count` default 10, max 20, sorted by create_time desc, cursor is ms UTC timestamp. [VLIST]
- Video list returns public videos only. [SCOPES]

### Ready-to-paste scope justifications
**user.info.basic**: "ClipPipeline lets creators connect their TikTok account. We use the user's open_id, display name and avatar to show which TikTok account is connected, to identify the account for later posts, and to show the creator which account will receive a post before they publish."

**video.publish**: "ClipPipeline lets a creator upload one video and publish it to each social account they have connected. For TikTok we use the Content Posting API Direct Post flow. Before each post we query creator info and show the creator's nickname and avatar, privacy options from the response (no default), comment/duet/stitch toggles (off by default, disabled if the creator disabled them in TikTok), a commercial content disclosure toggle, and the Music Usage Confirmation. The post is only sent after the creator reviews a preview and taps Post. We poll the publish status and show the result."

**video.list**: "After posting, ClipPipeline shows the creator how each of their TikTok videos is performing (views, likes, comments, shares) in an analytics dashboard, so they can compare performance across platforms. We read only the connected creator's own videos and display only to that creator."

**user.info.stats**: "ClipPipeline shows the creator's follower count and total video/like counts on their analytics dashboard, to track account growth over time next to their other connected platforms. Only the connected creator sees their own data."

**video.upload (only if kept)**: "If Direct Post is unavailable for a creator, ClipPipeline sends the video to the creator's TikTok inbox as a draft so they can finish and publish it in the TikTok app."

---

## 2. MUST-have UI elements (Direct Post "Export to TikTok" screen)

All from [CSG] unless stated.

### Creator info (call on every render of the post screen)
- MUST call `POST /v2/post/publish/creator_info/query/` (scope video.publish) each time the post page is rendered, to get fresh options. [CI][CSG]
- MUST display the creator's nickname (`creator_nickname`) so the user knows which account receives the post. Show avatar too (`creator_avatar_url`, URL valid 2h, so do not cache it). [CI][CSG]
- MUST stop the attempt and "prompt users to try again later" if the creator cannot post now. Creator info signals this with `spam_risk_too_many_posts`, `spam_risk_user_banned_from_posting`, or `reached_active_user_cap` (these arrive as intentional 200-level error codes). [CI][CSG]
- MUST enforce `max_video_post_duration_sec`; block videos longer than it (no numeric value on the guideline page; the doc example shows 300). [CI][CSG]
- Rate limit: 20 creator_info requests/min per access token. [CI]

### Metadata fields
- Title: user-editable; max 2200 UTF-16 runes. [DP-REF]
- Privacy status: dropdown populated ONLY from `privacy_level_options`; user MUST select manually; "no default value". Values: `PUBLIC_TO_EVERYONE`, `MUTUAL_FOLLOW_FRIENDS`, `FOLLOWER_OF_CREATOR`, `SELF_ONLY`. Public accounts get PUBLIC_TO_EVERYONE/MUTUAL_FOLLOW_FRIENDS/SELF_ONLY; private accounts get FOLLOWER_OF_CREATOR/MUTUAL_FOLLOW_FRIENDS/SELF_ONLY. [CI][CSG]
- Interactions: Allow Comment, Allow Duet, Allow Stitch. NONE checked by default. Grey out and disable any the creator turned off in TikTok settings (`comment_disabled`, `duet_disabled`, `stitch_disabled`). For photo posts only show Comment. Map to `disable_comment/duet/stitch` in init. [CSG][CI][DP-REF]
- Cover frame: optional `video_cover_timestamp_ms`. [DP-REF]
- AI-generated flag: `is_aigc` adds an AI label; offer a toggle if we ever generate/alter content with AI (SHOULD). [DP-REF]

### Commercial content disclosure (exact wording from [CSG])
- Toggle, OFF by default. Purpose: indicate "whether this content promotes yourself, a brand, product or service."
- When on, two options, multi-select: "Your brand" and "Branded content". At least one required; if toggle is on with none selected, the Publish button MUST be disabled, with hover text: **"You need to indicate if your content promotes yourself, a third party, or both."**
- Label prompts to show:
  - Your brand: **"Your photo/video will be labeled as 'Promotional content'"**
  - Branded content: **"Your photo/video will be labeled as 'Paid partnership'"**
  - Both: **"Your photo/video will be labeled as 'Paid partnership'"**
- Branded content cannot be private: when Branded content is checked, the "only me" (SELF_ONLY) option MUST be disabled with hover text **"Branded content visibility cannot be set to private."** (Alternative allowed by TikTok: if user already chose private then ticks Branded, either untick/disable Branded and tell them it can't be private, or auto-switch to public and tell them.)
- Map to API: `brand_organic_toggle` (Your brand), `brand_content_toggle` (Branded content). [DP-REF]
  - UNVERIFIED mapping detail: the exact toggle-to-checkbox mapping is inferred from names; confirm in the Direct Post reference before coding.

### Consent declarations (shown before the Publish button; link text must be tappable links)
- Your brand only / no commercial content: **"By posting, you agree to TikTok's Music Usage Confirmation"** (link "Music Usage Confirmation").
- Branded content only: **"By posting, you agree to TikTok's Branded Content Policy and Music Usage Confirmation"** (both linked).
- Both selected: declaration naming Branded Content Policy and Music Usage Confirmation (CSG gives no distinct string; use the branded-content wording above. Exact wording for "both" UNVERIFIED).
- The link URLs are not stated on the guidelines page text we received. UNVERIFIED: Music Usage Confirmation URL (commonly https://www.tiktok.com/legal/page/global/music-usage-confirmation/en) and Branded Content Policy URL (commonly https://www.tiktok.com/legal/page/global/bc-policy/en). Click through from the live CSG page and copy the exact hrefs.

### Preview, consent, status
- MUST show a preview of the content before posting. [CSG][REVIEW search result]
- MUST NOT add promotional watermarks/logos/text/links to the video; users MUST be able to edit any preset text (titles, hashtags). [CSG][DEVTERMS III.3(t)]
- MUST send content only after the user has expressly consented to the upload (explicit Post tap). [CSG]
- MUST tell users processing may take a few minutes before the post appears on their profile. [CSG]
- MUST poll status (or handle webhooks) and surface it. Poll limit 30 req/min per token. [CSG][STATUS]
  - Status values: PROCESSING_UPLOAD, PROCESSING_DOWNLOAD, SEND_TO_USER_INBOX, PUBLISH_COMPLETE, FAILED. Public posts go through moderation first; `post_id` appears only after (usually ~1 minute, can be hours). [STATUS]
  - Fail reasons to map to friendly messages: file_format_check_failed, duration_check_failed, frame_rate_check_failed, picture_size_check_failed, video_pull_failed, internal (retry), auth_removed (do not retry, mark account disconnected), spam_risk_* (do not retry), publish_cancelled. [STATUS]
- Intended-use rules: app must help authentic creators post original content, not copy arbitrary content from other platforms, and be for a broad audience (not internal/test only). [CSG]
- Use FILE_UPLOAD for content on the user's device; PULL_FROM_URL requires a TikTok-verified domain/prefix and is meant for server-stored content. [CSG][DP-REF] Our model (user uploads to us, we hold it server-side) could use PULL_FROM_URL if we verify the domain; the current plan of FILE_UPLOAD from the server is acceptable per DP-REF but CSG says not to use FILE_UPLOAD for content already on your servers. UNVERIFIED which TikTok reviewers prefer for our case: consider PULL_FROM_URL with verified domain clippipeline.nonsostudio.com (or its media/CDN host) to stay inside the stated guidance.
- Client secret must stay confidential (server only, never in the Android app or open source). [CSG]

---

## 3. Privacy policy and Terms content

Rules:
- Terms of Service and Privacy Policy MUST exist, be fully working, and the links MUST be visible on the website without opening a menu. [REVIEW]
- ToS URL, Privacy Policy URL and Web/Desktop URL MUST be verified in the portal (required for apps created after 2024-09-09, and for any app using the Content Posting API upload URL). [Search snippet of https://developers.tiktok.com/docs/en/content-posting-api-get-started and register-your-app]
- Website must not be only a landing or login page; it must describe the product and be fully developed and public. [REVIEW]
- Developer Terms III.2(c): "make a complete and accurate disclosure to your End Users of the privacy practices." [DEVTERMS]

Content to include (derived from DEVTERMS; TikTok publishes no template, so exact required text is UNVERIFIED):
1. What we collect from TikTok: open_id, display name, avatar, (if approved) follower/following/likes/video counts, public video list and per-video counts, post status, access/refresh tokens.
2. Why: connecting account, publishing the user's own videos at their request, showing their analytics.
3. That posting happens only on the user's explicit action.
4. Storage and security: tokens encrypted at rest, restricted access; reference "highest industry standards" safeguards [DEVTERMS III.2(e)].
5. No sale/sharing of TikTok data; no use to build profiles/databases beyond the service [DEVTERMS III.3(h)]; TikTok info used only to develop/maintain/support the app [II.1]. Do not state or imply ML training on TikTok data.
6. How the user disconnects (in ClipPipeline and in TikTok Settings > Manage app permissions) and what we then delete (section 4).
7. Retention periods, user deletion request route (email + in-app/web account deletion), contact address.
8. Reference to TikTok's Privacy Policy and Terms (https://www.tiktok.com/legal/privacy-policy) and Music Usage Confirmation / Branded Content Policy for posted content. Add an "agrees to TikTok terms" line in our ToS.
9. No implied TikTok endorsement or affiliation [DEVTERMS III.3(o)].
- Android: if published on Google Play, TikTok requires the Android package name and app signature in portal config, and the app published on Google Play. [REVIEW]

---

## 4. Data deletion, revocation, retention

What TikTok states:
- On termination of the developer agreement, delete TikTok Information in all forms including copies on your servers. [DEVTERMS VI]
- The Developer Terms set no numeric retention period and no end-user deletion SLA. UNVERIFIED: any official "delete within X days of revocation" number. Controller-to-controller terms apply to TikTok Personal Data (DEVTERMS III.5, text not fetched; UNVERIFIED details). Treat as best practice: purge within 30 days (our own commitment, not TikTok's).
- Tokens: access token lives 24h (86,400 s); refresh token 365 days; refresh via `POST https://open.tiktokapis.com/v2/oauth/token/` with grant_type=refresh_token, refresh does not need user consent, and the returned refresh_token may change so MUST store the new one. [TOKEN]
- Revoke: `POST https://open.tiktokapis.com/v2/oauth/revoke/` with client_key, client_secret, token. [TOKEN]
- Webhook `authorization.removed` fires when a user deauthorizes (token already revoked). `content.reason`: 0 unknown, 1 user disconnected in TikTok, 2 account deleted, 3 age changed, 4 banned, 5 developer revoked. [EVENTS]
- Webhook rules: HTTPS callback URL registered in portal; reply HTTP 200 immediately; retries up to 72h exponential backoff; at-least-once so handlers MUST be idempotent. [HOOKS] Signature verification: described on a separate Verification page not fetched; UNVERIFIED details (implement per https://developers.tiktok.com/doc/webhooks-verification).
- Other webhook events for posting: post.publish.failed / complete / inbox_delivered / publicly_available / no_longer_publicaly_available. [STATUS]
- On security compromise of our systems: disconnect from TikTok services immediately and report to TikTok immediately. [DEVTERMS III.2(f)]

What we must build:
1. Webhook endpoint (HTTPS, public) for `authorization.removed`: mark TikTok connection revoked, delete stored access/refresh tokens, stop scheduled jobs, notify user; then delete or anonymize TikTok-derived profile and analytics data on a defined schedule (propose: tokens immediately, other TikTok data within 30 days).
2. In-app and web "Disconnect TikTok" that calls the revoke endpoint, then deletes tokens and (per user choice) data.
3. Account deletion flow that deletes all TikTok data and revokes.
4. Public data deletion instructions page/URL stated in the privacy policy (TikTok has no required callback URL for deletion beyond the webhook; UNVERIFIED whether a data-deletion URL field exists in the portal).
5. Refresh job: refresh tokens before the 24h expiry; handle refresh failure by flagging reconnect-needed.
6. Do not retain avatar/cover URLs (they expire: 2h and 6h); refetch.
7. Cached analytics: refresh from API rather than serving stale data indefinitely (no official caching rule; UNVERIFIED).

---

## 5. Review/audit process and demo shot list

Two separate gates [DP-START][REVIEW]:
- App review (portal): approves products and scopes. Required before video.publish works at all.
- Content Posting API audit: lifts the private-only restriction. Unaudited clients: all posts forced to `SELF_ONLY`; user account must be private at post time (`unaudited_client_can_only_post_to_private_accounts`); max 5 users can post in a 24h window. After audit: 24h active creator cap set from the usage estimate in the audit form. [CSG][DP-START][DP-REF]

Steps:
1. Create app in https://developers.tiktok.com (name must not contain "TikTok" and must not describe the function, must follow Brand guidelines; icon not confusable with other brands). [REVIEW]
2. Description: what the app does and how; must not be for private/personal use, in development, or for testing. [REVIEW]
3. Add and verify Website URL, ToS URL, Privacy Policy URL (links visible without menu). [REVIEW]
4. Configure platforms: Web redirect URI (https, absolute, static, no query/fragment, <512 chars, max 10); Android package name + signature + Google Play listing (for mobile app integration). [LOGIN][REVIEW]
5. Add products: Login Kit, Content Posting API (Direct Post), Display API (video.list, user info). Add scopes (section 1) only as needed.
6. Fill "App review information": explain each product and scope in detail (use section 1 text). [REVIEW]
7. Upload demo video(s): at least 1, max 5, each up to 50 MB, end-to-end, real UI, every selected scope shown, web domain shown matching submitted Website URL, mobile video starts with app opening, first-time apps use the Sandbox. [REVIEW]
8. Submit; fix feedback; resubmit. Review SLA: UNVERIFIED (no official time found).
9. After products approved, apply for Content Posting API audit (form includes usage estimates that set the creator cap). Until passed, test only with sandbox/target users and private accounts.
10. Verify PULL_FROM_URL domain/prefix in "Manage URL properties" if using it. [DP-REF]

Demo video shot list (web, ~3-5 min; do a second short clip starting from Android app launch):
1. Browser address bar visible on clippipeline.nonsostudio.com, landing page with visible Privacy Policy and Terms links.
2. Log in to ClipPipeline; open Connections; tap Connect TikTok.
3. TikTok authorization screen (https://www.tiktok.com/v2/auth/authorize/) showing every requested scope; approve; redirect back showing connected account name and avatar (user.info.basic).
4. Upload a video once in ClipPipeline; choose TikTok among platforms.
5. TikTok post screen: creator nickname and avatar loaded from creator_info; duration limit check (show a too-long video blocked if possible); privacy dropdown empty with no default, options from API; comment/duet/stitch unchecked, then show one disabled by creator setting; title editable.
6. Toggle commercial content: Publish disabled with hover text; select Your brand (label text); select Branded content (label text, "only me" disabled with hover text).
7. Show consent line with Music Usage Confirmation link (and Branded Content Policy when applicable); show preview; tap Post; show "may take a few minutes" notice.
8. Show status polling: processing, then complete (or sandbox equivalent); open TikTok app/profile to show the post.
9. Analytics page showing views, likes, comments, shares per video (video.list) and follower count (user.info.stats).
10. Disconnect TikTok; show it removed from TikTok's Manage app permissions; show data deleted message.
11. Repeat 1-3 and 5-7 quickly in the Android app, starting from app icon launch.

---

## 6. Limits affecting design

| Item | Limit | Source |
|---|---|---|
| Direct Post init | 6 requests/min per user access token | [DP-REF] |
| Upload (inbox) init | 6 requests/min per token; max 5 pending shares per 24h | [UP] |
| creator_info query | 20 requests/min per token | [CI] |
| Post status fetch | 30 requests/min per token | [STATUS] |
| /v2/user/info, /v2/video/list, /v2/video/query | 600 requests/min per endpoint (sliding minute window); raise via TikTok support request | [RATE] |
| Posts per creator | about 15 per 24h per creator account, shared across all apps ("may vary") | [CSG] |
| Unaudited apps | 5 posting users per 24h; SELF_ONLY; private account required | [CSG][DP-REF] |
| Audited active-creator cap | per audit estimate, error `reached_active_user_cap` | [CSG][DP-REF] |
| Title length | 2200 UTF-16 runes (Display `video_description` limited to 150 chars) | [DP-REF][VOBJ] |
| Formats | MP4 (recommended), WebM, MOV; H.264 recommended, also H.265, VP8, VP9 | [MEDIA] |
| Frame rate | 23-60 FPS | [MEDIA] |
| Resolution | 360-4096 px each dimension | [MEDIA] |
| Duration | up to 10 min via the Upload doc; per-creator maximum from `max_video_post_duration_sec` (Direct Post authoritative) | [MEDIA][CI] |
| File size | max 4 GB | [MEDIA] |
| Chunking | chunks 5-64 MB, sequential; final chunk may be up to 128 MB; files under 5 MB single chunk; total_chunk_count = floor(size/chunk_size), 1 to 1000 | [MEDIA] |
| Chunk headers | Content-Type video/mp4, video/quicktime or video/webm; Content-Length; Content-Range `bytes FIRST-LAST/TOTAL`; 206 = more expected, 416 = range mismatch, 5xx = retry same chunk, 403 = upload_url expired | [DP-REF][MEDIA] |
| upload_url validity | 1 hour from issue | [DP-REF] |
| Token life | access 24h, refresh 365d | [TOKEN] |
| Avatar URL life | 2h (creator_info); cover URL 6h (Video object) | [CI][VOBJ] |
| Video list page size | max 20 | [VLIST] |

Design consequences:
- Queue and serialize per-user TikTok calls; back off on 429 `rate_limit_exceeded`; never auto-retry spam_risk_* errors.
- Pre-validate duration, format, FPS, resolution before upload; transcode if needed.
- Resumable chunk uploader: retry the same chunk on 5xx, restart init if the 1 hour URL expires.
- Cross-platform publish must treat TikTok as its own step with a TikTok-specific form; cannot reuse generic title/privacy fields without the user confirming them in the TikTok panel.
- "Publish to all" must not default privacy or interaction settings for TikTok; user must set them.
- Do not call any endpoint per page view for analytics; sync in batch (video list up to 20 per call).

---

## 7. Branding rules

- Follow the TikTok Brand and Use Guidelines (tiktokbrandbook.com); no use of TikTok logos, icons, symbols or designs without prior written permission; the Logo and Button pack is on the design guidelines page. Never use others' marks without permission. [BRAND]
- Developer Terms: TikTok Logo license is limited and revocable; do not alter/remove proprietary notices; do not imply TikTok affiliation, sponsorship, or endorsement; no public statements about the relationship with TikTok without written consent. [DEVTERMS II.2, III.3(n), III.3(o), X]
- App name must not include "TikTok"; avoid "for TikTok" style naming. [REVIEW]
- Never superimpose a brand name, logo, watermark, link or promotional text on creators' videos. [CSG][DEVTERMS III.3(t)]
- Specific logo clear space, color, size rules: UNVERIFIED (only third-party summaries seen; read tiktokbrandbook.com). Use only the official TikTok icon from the portal Logo and Button pack, unmodified, in the connect button and platform list; the Login Kit "Continue with TikTok" button asset is in that pack.

---

## 8. Checklists

### CODE checklist (for build agents)

Backend
- MUST: Request scopes `user.info.basic,video.publish,video.list` (+ `user.info.stats` if showing followers); remove `video.upload` unless demoing an inbox flow. [section 1]
- MUST: OAuth per Login Kit Web: authorize URL `https://www.tiktok.com/v2/auth/authorize/` with client_key, scope (comma-separated), redirect_uri, state (cryptographically random, verified on return), response_type=code. [LOGIN] PKCE is required for mobile/desktop token exchange (`code_verifier`) [TOKEN]; for the Android app use PKCE.
- MUST: Token exchange/refresh at `https://open.tiktokapis.com/v2/oauth/token/` (form-encoded); store the rotated refresh token each time; scheduled refresh before 24h expiry; encrypt tokens at rest; client secret server-only.
- MUST: Endpoint `POST /tiktok/creator-info` proxy that calls creator_info query each time the post screen opens and returns nickname, avatar URL, privacy options, disabled flags, max duration, and a can-post flag.
- MUST: Direct Post init validation: reject if `privacy_level` not in last creator_info options; reject branded content + SELF_ONLY; reject duration > max; require explicit consent flag from client; map brand toggles and disable_* flags; never send default privacy.
- MUST: Chunked FILE_UPLOAD (5-64 MB chunks, sequential, correct Content-Range, final chunk absorbs remainder, retries) or PULL_FROM_URL with verified domain.
- MUST: Status polling (<=30/min/token) and/or webhook handlers for post.publish.*; persist publish_id, post_id; map fail reasons to user messages.
- MUST: Webhook endpoint (HTTPS, immediate 200, idempotent, signature verified) handling `authorization.removed` by purging tokens and flagging connection.
- MUST: Disconnect endpoint calling `/v2/oauth/revoke/`, deleting tokens; account deletion cascade; data purge job with documented schedule.
- MUST: Rate limiter / queue honoring limits in section 6 and 429 backoff; no auto-retry on spam_risk_*.
- MUST: Analytics sync using `/v2/video/list/` with `fields=` including id, title, create_time, duration, view_count, like_count, comment_count, share_count, share_url, cover_image_url; follower count via `/v2/user/info/` with `follower_count` (needs user.info.stats). Snapshot daily for history. Do not expose saves/watch time for TikTok.
- SHOULD: Pre-flight media validation/transcoding (MP4/H.264, 23-60 FPS, 360-4096 px, <=4 GB).
- SHOULD: Do not cache avatar/cover URLs longer than 2h/6h.
- SHOULD: Store `is_aigc` choice and pass it.

Web
- MUST: TikTok post panel with all items in section 2 (nickname/avatar, no-default privacy dropdown, unchecked interaction toggles with disabled states, commercial content toggle with exact strings, consent declaration with working links, preview, Post button disabled until valid, "few minutes" notice, status display).
- MUST: Site footer links to Privacy Policy and Terms visible without a menu, on the verified Website URL; homepage not just a login page.
- MUST: Privacy policy and ToS content per section 3; data deletion instructions.
- MUST: Redirect URI page https, static, matching portal.
- MUST: Disconnect TikTok control and deletion request path.
- SHOULD: Show TikTok official icon only from the approved pack.

Mobile (Android)
- MUST: Same post panel behavior as web (section 2), including per-creator disabled toggles and exact strings.
- MUST: OAuth with PKCE via system browser/App Link or Custom Tab redirect to an https redirect URI; no client secret in the app.
- MUST: App published on Google Play (or at least a listing) with package name + signature entered in the portal for review of the mobile integration. [REVIEW]
- MUST: Chunk uploads go through the backend (or use upload_url directly with correct headers), resumable on poor networks.
- SHOULD: Handle app backgrounding during processing by relying on webhook/poll, push notify on completion.
- SHOULD: Disconnect and delete account from within the app (also useful for Play data safety).

### CONSOLE checklist (Nonso, TikTok developer portal)
1. Register/verify the developer account and create the app at https://developers.tiktok.com (name without "TikTok", not descriptive; compliant icon).
2. Fill description, category, Website URL (clippipeline.nonsostudio.com), Terms URL, Privacy Policy URL; complete URL verification for each.
3. Add Login Kit (Web redirect URIs, https, no query), Content Posting API (enable Direct Post), Display API; webhook callback URL (HTTPS) and subscribe to authorization.removed and post.publish events.
4. Add Android platform: package name + signature (and Google Play listing live).
5. Select scopes: user.info.basic, video.publish, video.list, user.info.stats; deselect video.upload unless demoing it.
6. Verify domain/URL prefix for PULL_FROM_URL if used.
7. Enable Sandbox, add target test users, run the demo flow inside sandbox.
8. Record and upload demo videos (shot list section 5; <=5 files, <=50 MB each).
9. Paste scope justifications from section 1; submit for review.
10. After approval, file the Content Posting API audit application with realistic active-creator estimates; wait for approval before inviting real users (until then posts are private-only and 5 users/24h).
11. If 600/min Display API limits become tight, request higher limits via TikTok support.
12. Download Logo and Button pack; read tiktokbrandbook.com.
13. Store client key/secret in the secrets manager; rotate if exposed.

---

## UNVERIFIED summary
- Exact wording of the "both selected" consent declaration and the live hrefs for Music Usage Confirmation and Branded Content Policy.
- Mapping of "Your brand"/"Branded content" to `brand_organic_toggle`/`brand_content_toggle` (inferred).
- Review and audit turnaround times; whether the portal has a dedicated data-deletion URL field.
- Webhook signature verification procedure (page not fetched).
- Numeric retention/deletion SLAs after revocation (none found in Developer Terms; Controller-to-Controller terms not read).
- Whether any TikTok endpoint exposes saves, watch time or follower history (none found; Video object lacks them).
- Full brand-book rules (logo spacing, colors).
- Whether reviewers prefer PULL_FROM_URL over FILE_UPLOAD for server-hosted media.
- Several pages (app-review, login-kit PKCE detail) were fetched via an intermediate summarizer; verify critical strings against the live page before submission.
