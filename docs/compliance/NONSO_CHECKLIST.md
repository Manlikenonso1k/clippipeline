# Nonso's console checklist

Everything here is done by you in the platform consoles. The code side is in `docs/COMPLIANCE.md`.
Ready-to-paste justification text for each scope/permission: section 1 of `tiktok.md`, `meta.md`, `youtube-google.md` in this folder.
Demo-video shot lists: section 5 of the same files.

## URLs to enter (all on clippipeline.nonsostudio.com)
| Purpose | URL |
|---|---|
| Homepage / website | `https://clippipeline.nonsostudio.com/` |
| Privacy policy | `https://clippipeline.nonsostudio.com/privacy` |
| Terms of service | `https://clippipeline.nonsostudio.com/terms` |
| Data deletion instructions | `https://clippipeline.nonsostudio.com/data-deletion` |
| Account deletion (Google Play, works logged-out) | `https://clippipeline.nonsostudio.com/delete-account` |
| Meta data deletion callback | `https://clippipeline.nonsostudio.com/api/meta/data-deletion` |
| Meta deauthorize callback | `https://clippipeline.nonsostudio.com/api/meta/deauthorize` |
| TikTok webhook callback | `https://clippipeline.nonsostudio.com/api/webhooks/tiktok` |
| OAuth redirect: Google login (existing) | `https://clippipeline.nonsostudio.com/auth/callback/google` |
| OAuth redirect: YouTube (existing) | `https://clippipeline.nonsostudio.com/integrations/callback/youtube` |
| OAuth redirect: Meta (existing) | `https://clippipeline.nonsostudio.com/integrations/callback/instagram` |
| OAuth redirect: TikTok (existing) | `https://clippipeline.nonsostudio.com/integrations/callback/tiktok` |

No new redirect URIs are needed. Mobile sign-in and connect both go through these existing callbacks.

## TikTok (developers.tiktok.com)
1. App name without "TikTok"; compliant icon; description and category.
2. Website, Terms and Privacy URLs from the table; complete URL verification for each.
3. Products: **Login Kit** (Web redirect URI = TikTok callback above, https, no query), **Content Posting API** with **Direct Post** enabled, **Display API**.
4. Webhooks: callback URL above; subscribe to `authorization.removed` and `post.publish.*`.
5. Android platform: package `com.codebynonso.clippipeline` + signing certificate (from EAS credentials); a Play listing (even internal/closed testing) helps review.
6. Scopes: `user.info.basic`, `user.info.stats`, `video.publish`, `video.list`. **Deselect `video.upload`.**
7. Sandbox: add target test users; record the demo in sandbox (≤ 5 files, ≤ 50 MB each).
8. Paste scope justifications; submit for review.
9. After approval: file the **Content Posting API audit**. Until it passes: posts are SELF_ONLY, accounts private, max 5 users / 24h. Then set `TIKTOK_AUDITED=true` in `.env`.

## Meta (developers.facebook.com/apps)
1. App type Business; Development mode until review passes, then **Live**.
2. Settings › Basic: 1024×1024 icon, name "ClipPipeline", category, contact email, App domain `clippipeline.nonsostudio.com`, Privacy + Terms URLs, **Data Deletion Request URL** = Meta data deletion callback above.
3. Facebook Login for Business › Settings: Valid OAuth Redirect URI = Meta redirect above (unchanged); Client + Web OAuth login on; Enforce HTTPS; **Deauthorize Callback URL** = Meta deauthorize callback above.
4. **Business Verification** in Business Manager (required for Advanced Access) before App Review.
5. Roles: add testers; each needs a Facebook Page with a linked Instagram **professional** account.
6. Make one successful call per permission in dev mode within 30 days before submitting.
7. App Review: request `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`, `read_insights`, `instagram_basic`, `instagram_content_publish`, `instagram_manage_insights` — plus `business_management` only if your Page is owned through Business Manager (otherwise set `META_SCOPES` without it). One screencast per permission.
8. Give reviewers a ClipPipeline test login and a Page + IG test setup (no personal Meta credentials).
9. After approval switch to Live; check Advanced › User Data Deletion Requests every 21 days; yearly Data Use Checkup.

## Google / YouTube
Google Cloud Console:
1. Enable **YouTube Data API v3** and **YouTube Analytics API**.
2. Verify `nonsostudio.com` in Google Search Console with the project owner account; add it as an Authorized domain.
3. OAuth consent screen (External): name, logo, support email, Homepage/Privacy/Terms URLs from the table.
4. Scopes: `openid`, `email`, `profile`, `youtube.upload`, `youtube.readonly`, `yt-analytics.readonly`; paste justifications.
5. Web OAuth client redirect URIs: Google login + YouTube redirects above (already registered).
6. While in **Testing**, add test users (refresh tokens expire after 7 days). Then **Publish app → Prepare for verification**, upload the demo video, submit.

YouTube API Services:
7. Submit the **Audit and Quota Extension** form (https://support.google.com/youtube/contact/yt_api_form) with homepage, privacy, terms and demo video. Until it passes, uploads are private-locked; then set `YOUTUBE_AUDITED=true`.

Google Play Console:
8. Data safety: name, email, user IDs; photos and videos (uploads); app activity (titles/captions); YouTube/Meta/TikTok stats under app activity. Purposes: app functionality, analytics, account management. Encrypted in transit: yes. Deletion supported: yes.
9. Account deletion: in-app path (Profile › Delete account) **and** web link `https://clippipeline.nonsostudio.com/delete-account`.
10. Privacy policy URL in App content.
11. Don't use YouTube/TikTok/Meta logos as your own branding in the listing.

## .env flags you control
`TIKTOK_AUDITED=false`, `YOUTUBE_AUDITED=false` until each audit passes. `SUPPORT_EMAIL=` for the support links. `META_SCOPES=` only to override the default Meta scope list.
