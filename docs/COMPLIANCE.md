# Compliance build checklist (merged CODE items)

Merged from `docs/compliance/{tiktok,meta,youtube-google}.md` §8 (sources and wording live there).
Console work for Nonso is in `docs/compliance/NONSO_CHECKLIST.md`.
Owners: **BE** backend (API/services/public pages), **MOB** mobile, **WEB** Filament. Every MUST needs a file:line in the QA report.

## Lead decisions (where research conflicts with constraints)
- **No ffmpeg on Hostinger**: no transcoding. Validate instead: container/codec by MIME, size, and duration read from the MP4 `mvhd` atom in pure PHP (BE), plus client-side duration check from the picker (MOB) / browser (WEB). Reject per platform with a clear message.
- **TikTok upload**: Direct Post with chunked `FILE_UPLOAD` only (no PULL_FROM_URL, no domain verification). Scopes: `user.info.basic, user.info.stats, video.publish, video.list` (drop `video.upload`). OAuth stays the web-server flow through the backend callback (PKCE only if TikTok rejects the web flow).
- **Meta scopes**: keep `business_management` behind config `services.meta.scopes` (default ON) because Business-Manager-owned Pages often don't list without it; NONSO_CHECKLIST says to remove it if the Page is personally owned.
- **Retention**: daily snapshots are "Authorized Data"; keep them while the account stays authorized and re-check authorization at least every 30 days (daily refresh job counts). Cap history at 36 months. Purge within 7 days of disconnect/revoke/account deletion.
- **Platform audits**: `TIKTOK_AUDITED=false` and `YOUTUBE_AUDITED=false` in `.env` drive the "stays private until review" notices and force SELF_ONLY / private.

## Public pages and callbacks (BE)
| ID | Item | Level |
|---|---|---|
| C-01 | `/privacy`: what we store (videos, tokens, analytics, profile), why, retention, sharing (none for ads), how to delete; Google **Limited Use** disclosure sentence; links to YouTube Terms (https://www.youtube.com/t/terms) and Google Privacy Policy (https://policies.google.com/privacy); revoke instructions (https://myaccount.google.com/permissions, Facebook/Instagram app settings, TikTok Manage app permissions); TikTok/Meta data use; "Last updated" | MUST |
| C-02 | `/terms`: service terms; users agree to YouTube ToS when using YouTube features; TikTok Music Usage Confirmation and Branded Content Policy references; Meta Platform Terms note; non-affiliation statement | MUST |
| C-03 | `/data-deletion`: human instructions (in-app, web, email) for deleting data per platform | MUST |
| C-04 | `/delete-account`: public page that works logged-out — explains what gets deleted, sign-in-to-delete button (Google login) and a support email; mentions "ClipPipeline" as on the store listing | MUST |
| C-05 | Footer on home + public pages: Privacy, Terms, Data deletion, Delete account, Support (mailto `SUPPORT_EMAIL`); homepage is not login-only | MUST |
| C-06 | `POST /api/meta/data-deletion`: verify `signed_request` (HMAC-SHA256, app secret, constant-time), queue purge for that Meta user id, return `{"url":".../data-deletion/status?code=X","confirmation_code":"X"}`; `GET /data-deletion/status?code=` public status page | MUST |
| C-07 | `POST /api/meta/deauthorize`: verify signed_request, delete Meta rows + tokens, cancel queued Meta distributions | MUST |
| C-08 | `POST /api/webhooks/tiktok`: respond 200 fast, verify signature (`TikTok-Signature` per docs), idempotent; `authorization.removed` → purge tokens, mark account needs_reauth/removed; `post.publish.*` → update distribution | MUST |

## Account, tokens, retention (BE)
| ID | Item | Level |
|---|---|---|
| A-01 | Disconnect revokes at the platform where supported: Google `oauth2.googleapis.com/revoke`, TikTok `/v2/oauth/revoke/`, Meta `DELETE /{user-id}/permissions` (user token) — then deletes rows | MUST |
| A-02 | `DELETE /api/me` (and web): revoke all platform tokens, delete social accounts, posts, distributions, analytics, snapshots, stored video files, device tokens, Sanctum tokens, notifications, then the user; queued, completes ≤ 7 days (target: immediately); audit log without PII | MUST |
| A-03 | `invalid_grant` / Meta code 190 / TikTok revoked → status needs_reauth, stop jobs; purge that account's authorized data after 30 days still unauthorized | MUST |
| A-04 | Daily job: re-check authorization per account (token refresh counts), record `last_authorized_check_at`; refresh-or-delete cached titles/thumbnails/channel names within 30 days; prune snapshots older than 36 months | MUST |
| A-05 | TikTok token refresh before 24h expiry; store rotated refresh token | MUST |
| A-06 | Never log tokens, secrets or signed_request; tokens encrypted at rest (done) | MUST |
| A-07 | YouTube data: no derived metrics beyond sums/averages; no ads use | MUST |

## Publishing rules (BE validates; MOB + WEB show the UI)
| ID | Item | Level | Owner |
|---|---|---|---|
| P-01 | Explicit publish: confirm step listing destination accounts, caption, and per-platform settings; no one-click / silent posting | MUST | MOB, WEB |
| P-02 | Per-platform validation: YouTube title ≤ 100, description ≤ 5000 bytes, strip `<` `>`; Shorts ≤ 180 s vertical/square (warn); FB Reels 3–90 s; IG Reels 3 s–15 min, ≤ 300 MB; TikTok ≤ creator max duration, ≤ 4 GB | MUST | BE (+ client pre-check MOB, WEB) |
| P-03 | Caps: IG 100 posts/24h (query `content_publishing_limit`), FB 30 Reels/24h per Page, YouTube project upload quota → friendly "limit reached" error | MUST | BE |
| P-04 | YouTube: privacy choice (public/private/unlisted) on the form, plus "I confirm this follows YouTube's Community Guidelines" checkbox; YouTube ToS link near the YouTube toggle; while `YOUTUBE_AUDITED=false` force private and show "YouTube uploads stay private until ClipPipeline passes YouTube's audit" | MUST | BE, MOB, WEB |
| P-05 | Scheduled posts visible and cancellable (delete / cancel) | MUST | BE, MOB, WEB |
| P-06 | Status display + "may take a few minutes to process" notice after submit | MUST | MOB, WEB |

## TikTok post form (content sharing guidelines) — MOB + WEB, backed by BE
| ID | Item | Level |
|---|---|---|
| T-01 | `GET /api/tiktok/creator-info` called every time the TikTok form opens; show creator nickname + avatar ("Posting to @nickname") | MUST |
| T-02 | Privacy dropdown built only from `privacy_level_options`, **no default**; Publish disabled until chosen | MUST |
| T-03 | "Allow comments", "Allow Duet", "Allow Stitch" start **off**; disabled + labelled when creator_info says the creator turned them off | MUST |
| T-04 | Block videos longer than `max_video_post_duration_sec`; block when `can_post` is false (show reason) | MUST |
| T-05 | "Disclose video content" toggle (off). When on: "Your brand" and "Branded content" checkboxes; at least one must be checked to publish; Branded content disables "Only me" (SELF_ONLY) | MUST |
| T-06 | Labels after selection: Your brand → "Your photo/video will be labeled as 'Promotional content'"; Branded content → "Your photo/video will be labeled as 'Paid partnership'" | MUST |
| T-07 | Consent line above Publish: "By posting, you agree to TikTok's Music Usage Confirmation" (+ " and Branded Content Policy" when Branded content is checked), with links | MUST |
| T-08 | While `TIKTOK_AUDITED=false`: "TikTok posts stay private until ClipPipeline passes TikTok's review" and only SELF_ONLY is selectable | MUST |
| T-09 | BE rejects: privacy not in last creator_info options, branded + SELF_ONLY, duration > max, missing consent | MUST |
| T-10 | Optional "AI-generated content" toggle → `is_aigc` | SHOULD |

## In-app UI (MOB + WEB)
| ID | Item | Level |
|---|---|---|
| U-01 | Privacy + Terms links on the login screen, Connect screen and Profile (mobile) / footer + profile (web) | MUST |
| U-02 | Per-connection Disconnect with explanation that tokens are revoked | MUST (done; verify revoke text) |
| U-03 | Delete account in Profile with typed confirmation, explaining what is deleted; also link to `/delete-account` | MUST |
| U-04 | Connect screen rationale before OAuth: what we access and why, plus YouTube ToS link on the YouTube card | MUST |
| U-05 | "Last updated" time on analytics (from `captured_on`/`last_synced_at`); platform logo next to its data | MUST |
| U-06 | Reconnect-needed state surfaced (needs_reauth) | MUST (done) |
| U-07 | Non-affiliation line ("ClipPipeline is not affiliated with TikTok, Meta or YouTube") on Profile/About and web footer | SHOULD |
| U-08 | Remove unverifiable claims (e.g. "SOC2") | MUST |
