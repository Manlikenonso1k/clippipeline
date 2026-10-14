# ClipPipeline: YouTube / Google / Google Play compliance

Researched 2026-10-14 from official pages only. Source URL after every requirement. "UNVERIFIED" = not confirmed on an official page I could read. Policy pages change; re-check before submission. Fetch tool summarised pages, so quoted wording marked (paraphrase) is not verbatim.

Key sources:
- DP = YouTube API Services Developer Policies: https://developers.google.com/youtube/terms/developer-policies
- DPG = Complying with Developer Policies: https://developers.google.com/youtube/terms/developer-policies-guide
- DMP = Derived metrics policy: https://developers.google.com/youtube/terms/derived-metrics-policy
- UDP = Google API Services User Data Policy: https://developers.google.com/terms/api-services-user-data-policy
- VR = Verification requirements: https://support.google.com/cloud/answer/13464321
- RMF = https://developers.google.com/youtube/terms/required-minimum-functionality
- PLAY-DEL = https://support.google.com/googleplay/android-developer/answer/13327111
- PLAY-DS = https://support.google.com/googleplay/android-developer/answer/10787469

---

## 1. Scopes / permissions

Minimum set (request only these; unused or "future" scopes cause rejection - VR https://support.google.com/cloud/answer/13464321):

| Scope | Why | Sensitivity |
|---|---|---|
| openid, email, profile | Google sign-in. Non-sensitive basic scopes. | Non-sensitive (https://support.google.com/cloud/answer/13463073: only non-sensitive apps skip verification). Basic profile scopes are also exempt from the 7-day Testing token expiry (https://developers.google.com/identity/protocols/oauth2). |
| https://www.googleapis.com/auth/youtube.upload | videos.insert upload ("Manage your YouTube videos", https://developers.google.com/identity/protocols/oauth2/scopes) | Almost certainly **sensitive**. UNVERIFIED: no official page I could read lists per-scope sensitivity; Cloud Console labels each scope when you add it (https://support.google.com/cloud/answer/13463073). Check the label in the console. |
| https://www.googleapis.com/auth/youtube.readonly | channels.list (channel id, subscriber count), videos.list (statistics) ("View your YouTube account") | Likely sensitive. UNVERIFIED, same reason. |
| https://www.googleapis.com/auth/yt-analytics.readonly | reports.query per-account/per-video analytics ("View Analytics reports for your content") | Likely sensitive. UNVERIFIED. |

I found no evidence that any of the three is "restricted" (restricted triggers the annual third-party security assessment, ~6 weeks, https://support.google.com/cloud/answer/13463817). Confirm in the console; if any shows "restricted", the plan changes (annual CASA assessment per UDP).

Possible reduction: if videos.list/channels.list with `mine=true` are all you need, `youtube.readonly` stays. Do NOT add `youtube` or `youtube.force-ssl` (broader). Request scopes at connect time, not at sign-in (incremental auth, UDP: "request them in context"); sign-in asks only openid/email/profile. Use `include_granted_scopes=true` (https://developers.google.com/youtube/v3/guides/auth/server-side-web-apps).

### Ready-to-paste scope justifications (for the verification form)

**youtube.upload**
> ClipPipeline lets a creator upload a video once and publish it to their own YouTube channel (including as a YouTube Short) from our web and Android apps. We use youtube.upload only to call videos.insert (resumable upload) on the channel the user connected, with the title, description and privacy status the user chose in our publish screen. A narrower scope is not available for uploading videos; without it the core "publish to YouTube" feature cannot work. We never modify or delete videos after upload.

**youtube.readonly**
> After the user connects their channel, we call channels.list (mine=true) to show the connected channel name, avatar and subscriber count, and videos.list to read the status and statistics (views, likes, comments) of videos published through ClipPipeline, so the user sees live post status and performance in their dashboard. We read only the user's own channel and videos. Without this scope we cannot confirm a published video's processing status or show its statistics.

**yt-analytics.readonly**
> We call YouTube Analytics reports.query for the connected channel and for videos published through ClipPipeline to show the creator watch time, views, likes, comments and subscriber changes in their analytics dashboard. Data is shown only to the channel owner who authorised it, is stored under their account, refreshed at least every 30 days and deleted when they disconnect or delete their account. The Data API alone does not provide watch time, so this scope is required.

(Adjust to match the actual features; justification must match the demo video. VR.)

---

## 2. MUST-have UI elements

1. **Google sign-in / connect consent screen disclosure in-product**: before launching the YouTube OAuth flow, show what is requested, by whom and why (UDP: "accurate, honest disclosure of who is requesting data, what data, and why"; in-context at the right time).
2. **Link to YouTube Terms of Service** `https://www.youtube.com/t/terms` shown in the app/site, and our own Terms must state that using ClipPipeline's YouTube features means the user agrees to be bound by the YouTube Terms of Service (DP III.A.1). Exact wording suggestion: "By using ClipPipeline's YouTube features, you agree to be bound by the YouTube Terms of Service (https://www.youtube.com/t/terms)."
3. **Privacy policy** prominently displayed and always accessible (DP III.A.2.a): link on landing page, login, connect screen, in app settings. Landing page cannot be only a login page (VR).
4. **Link to Google Privacy Policy** `http://www.google.com/policies/privacy` inside our privacy policy (DP III.A.2.c; use https://policies.google.com/privacy as the live URL, UNVERIFIED that the https variant is accepted but it redirects).
5. **Revoke access statement** in privacy policy: users can revoke ClipPipeline's access via the Google security settings page `https://security.google.com/settings/security/permissions` (DP III.A.2.h). Newer equivalent: https://myaccount.google.com/permissions (UNVERIFIED as policy-named URL; list both).
6. **Disconnect YouTube** button in app settings that programmatically revokes the token at `https://oauth2.googleapis.com/revoke` immediately (DP III.D.2 Revocation item 1; endpoint: https://developers.google.com/youtube/v3/guides/auth/server-side-web-apps).
7. **Delete my data / delete account** control (DP III.E.4.g; Play: in-app path, PLAY-DEL).
8. **Publish form** must let the user set title (required, max 100 chars, no `<` `>`), description (required, max 5000 bytes, no `<` `>`), and privacy status (public / private / unlisted) - RMF https://developers.google.com/youtube/terms/required-minimum-functionality and DP III.E.3.f. Do not change an existing video's privacy status without express user instruction.
9. **Multi-platform selection**: user must be able to select/deselect each platform being uploaded to (RMF).
10. **Community Guidelines certification** for user-uploaded content: DPG says if the service lets users upload videos, users must certify their content complies with YouTube Community Guidelines (https://developers.google.com/youtube/terms/developer-policies-guide). Exact wording not given (UNVERIFIED); suggested: "I confirm this video complies with the YouTube Community Guidelines." Checkbox required before publish to YouTube.
11. **Stats displayed as of a date**: show "Last updated <timestamp>" next to stats; historical data must be presented accurately in context of time (DP III.E.4.f).
12. **YouTube attribution / logo** where YouTube data is shown (section 7).
13. **Limited Use statement** visible on homepage or privacy policy (section 3).
14. If the app ever shows our own computed metrics next to YouTube metrics, label them as ours, not from YouTube (DP III.E.4.h; DMP).

---

## 3. Privacy policy / terms content

Privacy policy MUST (UDP https://developers.google.com/terms/api-services-user-data-policy, VR https://support.google.com/cloud/answer/13464321, DP III.A.2):
- Be hosted on the same verified domain as the homepage (clippipeline.nonsostudio.com) and linked from the homepage and the OAuth consent screen (same URL in both).
- Describe how the app accesses, uses, stores and shares Google user data; list all data accessed (channel id/title/avatar, subscriber count, video ids/titles/status, view/like/comment/watch-time statistics, email/name/profile picture), what is stored, retention, deletion, and actions taken on the user's behalf (uploading videos).
- Say data is secure in transit and at rest (UDP).
- Reference/link the Google Privacy Policy (DP III.A.2.c).
- Explain revocation via Google security settings (DP III.A.2.h).
- Explain deletion rights and how to request (DP III.E.4.g; Play).
- State retention and any retained data after deletion and why (PLAY-DEL).
- Notify users and get consent before changing how data is used (UDP).

### Limited Use disclosure (put on homepage or privacy policy)
Google's own example wording (https://support.google.com/cloud/answer/13463817): "{App}'s use of information received from Google APIs will adhere to Google API Services User Data Policy, including the Limited Use requirements."
Paste:
> ClipPipeline's use and transfer to any other app of information received from Google APIs will adhere to the [Google API Services User Data Policy](https://developers.google.com/terms/api-services-user-data-policy), including the Limited Use requirements.

Note: the policy page itself does not mandate an exact string (verified: fetch of UDP says wording is not specified), the sentence above is Google's FAQ template. The leading "use and transfer to any other app" form is the widely used long form; UNVERIFIED on an official page (the FAQ shows the shorter form). Using the long form is a superset.

Also add plain-language Limited Use commitments (UDP): we use Google user data only to provide/improve user-facing features; we do not transfer or sell it to advertising platforms, data brokers or resellers; no ads (including retargeting/personalised); no credit/lending decisions; no human reads the data except with consent, for security, legal compliance, or aggregated internal operations; employees/contractors bound.

Terms of Service MUST: state use of YouTube features = agreement to YouTube ToS (DP III.A.1).

---

## 4. Deletion, revocation, retention (what we must build)

Definitions (DP Section IV https://developers.google.com/youtube/terms/developer-policies): **Authorized Data** = API data the user authorised us to access with their credentials (everything we pull with the user's OAuth token, including Analytics API). **Non-Authorized Data** = data obtained without user credentials (API key only, e.g. public stats of videos the user did not connect).

Rules (DP III.E.4 and III.D.2):
| Rule | Requirement |
|---|---|
| Tokens (III.E.4.a) | May be kept as long as necessary for the purposes of the consent the active user gave. |
| Authorized analytics/reporting data and statistics, e.g. view counts (III.E.4.b) | May be stored longer than 30 days, **but** at least every 30 days we must re-confirm the user still authorises access to it (and that the video still exists). |
| Non-Authorized statistics (III.E.4.b) | Must not be stored more than 30 days. |
| Other Authorized Data, e.g. titles, descriptions, thumbnails (III.E.4.c) | Max 30 calendar days, then delete or refresh. |
| Non-Authorized Data (III.E.4.d) | Limited amounts, max 30 calendar days, then delete or refresh. |
| Consistency (III.E.4.e) | Make reasonable efforts to keep stored data consistent with YouTube. |
| Display (III.E.4.f) | Show most current data available; historical may be shown if accurate in context of time. |
| User deletion request (III.E.4.g) | Provide a way to request deletion; delete ASAP, within 7 calendar days. Account deletion in our app: delete associated data in the same window. |
| Revoke in our app (III.D.2) | Programmatically revoke token right away; delete Authorized Data within 7 calendar days. |
| Revoke at Google (III.D.2) | Delete related API data within 30 calendar days; periodically check tokens are still valid. |
| Derived data (III.E.4.h) | Must not create new/derived metrics or replace API data with independently calculated data; DPG says simple math (sums, averages, ratios) and raw-metric graphs over time are acceptable examples. Do not use likes to calculate other metrics (paraphrase, UNVERIFIED exact text). |
| DPG | Deletion on user request or inability to verify authorization must happen within 30 days (https://developers.google.com/youtube/terms/developer-policies-guide); the stricter DP 7-day figure applies to user/in-app requests. |

### Verdict for our daily snapshots
- Snapshots of views/likes/comments/watch time/subscribers fetched with the user's OAuth token are **Authorized Data statistics/analytics** under III.E.4.b: they may be kept beyond 30 days **provided** we verify at least every 30 days that the user still authorises access (a successful token refresh / API call each cycle is the practical proof) and that the video still exists. Our daily job naturally does this.
- If a refresh fails (invalid_grant / revoked), stop collecting and delete that account's YouTube data within 30 days (Google-side revoke) - we recommend 7 days to be safe.
- Titles/descriptions/thumbnails/channel names cached from YouTube: refresh at least every 30 days or delete (III.E.4.c).
- Anything fetched with only an API key (public data for videos not owned by a connected user): never keep more than 30 days.
- New wrinkle (since June 1, 2026): the derived-metrics page says audited analytics developers who apply through the quota extension form (Analytics & Reporting use case) may store statistics from statistical endpoints (views, likes, subscriber, comment counts) and derived metrics **up to 36 calendar months** (DMP https://developers.google.com/youtube/terms/derived-metrics-policy); everything else stays on 30 days. DPG states that without that approval you should not create derived metrics or store data beyond what the Developer Policies permit. UNVERIFIED: whether an unapproved project may keep unlimited-duration authorized statistics under III.E.4.b. Conservative design: (a) cap snapshot history at 36 months, (b) apply for the Analytics & Reporting use case in the audit form, (c) avoid derived metrics (growth scores, engagement-rate "scores") until approved, show only raw numbers, sums, averages and time graphs, (d) label any computed number as ClipPipeline's.
- Watch-time/analytics data from the Analytics API is "Analytics data ... authorized" under III.E.4.b (listed with statistics).

### Things to build
1. Daily snapshot job stores `fetched_at`, `source` (authorized/public) per row.
2. Authorization-health check at least every 30 days per connected account (daily is fine); on `invalid_grant` mark account `revoked`, stop jobs, schedule purge.
3. Purge job: Authorized Data of revoked accounts deleted within 30 days of Google-side revoke (target 7); within 7 days of in-app disconnect / deletion request.
4. Refresh-or-delete job for cached titles/descriptions/thumbnails/channel names older than 30 days.
5. Non-authorized cache TTL 30 days max (prefer not to store).
6. Disconnect endpoint calls `https://oauth2.googleapis.com/revoke` for the refresh token, then deletes tokens immediately, then queues data purge.
7. Account deletion (web + app) deletes user row, tokens, snapshots, uploaded media, derived data, backups on rolling schedule; tell users what we retain (e.g. billing/fraud records) and why (PLAY-DEL).
8. Encrypt refresh tokens at rest; TLS in transit (UDP).
9. Existing published YouTube videos are NOT deleted by us on disconnect (deleting our data does not affect YouTube's, DP III.E.4.g); say so in the UI.

---

## 5. Review / audit / verification, step by step

### A. OAuth app verification (Google Cloud)
Source: https://support.google.com/cloud/answer/13461325, https://support.google.com/cloud/answer/13464321, https://support.google.com/cloud/answer/13463817
1. Own and verify the domain in Google Search Console (project owner/editor) and add it to Authorized domains.
2. Homepage on that domain describing the app (not just a login page), linking to the privacy policy; privacy policy on same domain, same URL as on consent screen.
3. Configure consent screen: name, support email, logo, home page, privacy policy, terms, authorized domains, developer contact, scopes.
4. Add only the 3 YouTube scopes + basic scopes; write justification per scope (section 1).
5. Click **Publish App** (Testing apps cannot be verified). Then "Prepare for verification", paste justifications, add demo video link (unlisted YouTube), submit.
6. Review: brand verification ~2-3 business days; sensitive scopes ~10 business days; restricted ~6 weeks incl. security assessment (estimates, https://support.google.com/cloud/answer/13463817). Respond to emails from the OAuth team to project owners/editors.
7. Until verified: unverified-app warning and a lifetime **100 new-user cap** for sensitive/restricted scopes (same FAQ).
8. In Testing status, refresh tokens expire after **7 days** (unless only openid/email/profile) - test users must reconnect weekly (https://developers.google.com/identity/protocols/oauth2). Other invalidations: user revoke, 6 months unused, 100 tokens per client per account (oldest dropped).
9. Re-verification needed after material changes (and annually for restricted scopes) (https://support.google.com/cloud/answer/13463073).

### B. YouTube API Services compliance audit + quota extension
Source: https://developers.google.com/youtube/v3/guides/quota_and_compliance_audits, https://support.google.com/youtube/answer/7300965
1. Submit "YouTube API Services - Audit and Quota Extension Form" (https://support.google.com/youtube/contact/yt_api_form). Include a clear summary of the service and end users (DPG). Choose use case; for stored history select Analytics & Reporting and accept the amendment (DMP).
2. A YouTube API team member contacts you; answer questions with the service demo.
3. Why it matters: videos uploaded via videos.insert by **unverified** API projects created after 28 July 2020 are locked to **private**; lifting it requires an audit (https://developers.google.com/youtube/v3/revision_history, https://support.google.com/youtube/answer/7300965; locked videos cannot be appealed, must be re-uploaded via a verified service or YouTube app). CONFLICT: the videos.insert reference page reportedly says otherwise; treat the lock as real until proven. The Google OAuth verification and the YouTube API audit are separate things; you need both.
4. Do not shard across multiple projects to get quota (DPG). Separate keys per environment are fine.
5. Failed audit: Appeals Form. Periodic audits: Periodic Audit Form if contacted. Change of control: Change of Control Form. Audited in last 12 months: use the same form for more quota (quota page above).
6. Private-upload practical rule: until audited, test uploads will be private; tell testers.

### C. Demo video shot list (one video can serve OAuth verification and YouTube audit; unlisted, English)
Requirements (VR): show end-to-end flow incl. OAuth grant, same app/name/branding as submitted, the **full consent screen with exactly the requested scopes, English**, and each scope's feature in use.
1. Show clippipeline.nonsostudio.com homepage and click the privacy policy link; scroll to Limited Use statement and YouTube ToS / Google Privacy Policy links.
2. Show browser URL bar on the OAuth screen (client ID / project visible if possible) .
3. Sign in with Google (basic scopes), show consent for name/email.
4. In the app, tap "Connect YouTube". Show in-app disclosure explaining why.
5. Show the **full Google consent screen** listing the three YouTube permissions; zoom/pause; click Allow.
6. Back in ClipPipeline: connected channel name, avatar, subscriber count (youtube.readonly).
7. Upload a video, fill title/description, choose privacy (public/private/unlisted), tick Community Guidelines box, publish; show upload progress, then the video on YouTube (youtube.upload).
8. Show post status updating and view/like/comment stats (youtube.readonly).
9. Show analytics dashboard with watch time etc. and "last updated" (yt-analytics.readonly).
10. Settings: Disconnect YouTube (revokes), show Google permissions page `myaccount.google.com/permissions`, then Delete Account and data-deletion explanation.
11. Android app: repeat connect + publish quickly (Play reviewers/OAuth reviewers may check mobile).
For the audit, also be ready to explain data storage, retention jobs and deletion (section 4).

---

## 6. Limits affecting design

| Item | Value | Source |
|---|---|---|
| Default quota | 10,000 units/day per project, resets midnight Pacific | https://developers.google.com/youtube/v3/determine_quota_cost |
| videos.insert | Since Dec 4 2025 cost listed ~100 units (was ~1,600); since June 1 2026 moved to its **own granular bucket, 100 uploads/day default** (table: "100 quota per day. Each call costs 1 quota"). The page contradicts itself (summary still says 1600). Check console Quotas page. | https://developers.google.com/youtube/v3/revision_history ; https://developers.google.com/youtube/v3/determine_quota_cost ; https://developers.google.com/youtube/v3/guides/quota_and_compliance_audits |
| videos.list, channels.list | 1 unit/call | determine_quota_cost |
| videos.batchGetStats (new Jun 3 2026) | 1 unit, own bucket, 10,000/day default; useful for bulk snapshots | revision_history |
| Analytics reports.query | Has a quota cost evaluated per query; **exact cost UNVERIFIED** (page only says server evaluates each query) | https://developers.google.com/youtube/analytics/quickstart/quota |
| Daily uploads | 100/day across ALL users of our project by default: design a per-user cap and queue; request more through the extension form | quota guide |
| Title / description | Title max 100 chars; description max 5000 bytes; no `<` `>` | RMF |
| Shorts | Square or vertical aspect ratio, up to 3 minutes (videos uploaded on/after Dec 8 2025 that meet this are classified Shorts) | https://support.google.com/youtube/answer/15424877 ; https://support.google.com/youtube/answer/12779649 |
| Shorts thumbnail | 9:16 recommended; thumbnails.set limit raised to 50 MB (Sep 2026) | support 15424877 (search summary); revision_history |
| File size/format limits | Not stated on pages I read. UNVERIFIED (YouTube help states 256 GB / 12 h for standard uploads; not confirmed here). | |
| Resumable upload | Retry 500/502/503/504 with exponential backoff; resume where left off | https://developers.google.com/youtube/v3/guides/uploading_a_video |
| Shorts view counts | Views count on start/replay (from Mar 31 2025); Aug 27 2026 counting aligned across formats; do not compare old and new numbers naively | revision_history |
| Refresh tokens | 100 per user per client ID; unused 6 months expires; Testing = 7 days | https://developers.google.com/identity/protocols/oauth2 |

Design implications: queue uploads against the 100/day bucket; snapshot via batched videos.list (50 ids per call) or videos.batchGetStats, not per-video calls; keep reports.query to one call per channel/day plus per-video as needed; log quota use; no multiple projects to dodge quota.

---

## 7. Branding rules (https://developers.google.com/youtube/terms/branding-guidelines)
- Do not put "YouTube", "YT" or variants in the app name, logo or domain. "Works with YouTube" phrasing is OK.
- Use the official YouTube logo or icon (feature integration) or "Developed with YouTube" only if the product depends wholly on YouTube. For us: use the YouTube logo/icon for the YouTube feature.
- Show the logo next to YouTube-sourced content (stats, channel info); each logo must be clickable and link to YouTube or a YouTube component in the app.
- Do not recolour; place on a solid contrasting background; keep fully visible; don't make it the most prominent element; respect minimum sizes from the YouTube brand site (https://www.youtube.com/howyoutubeworks/resources/brand-resources/ UNVERIFIED not fetched).
- Do not imply endorsement; don't present non-YouTube content as YouTube (DP III.F.2).
- "Sign in with Google" button: Google Identity branding guidelines (https://developers.google.com/identity/branding-guidelines) UNVERIFIED not fetched; use the official button assets. The YouTube branding page does not address "Sign in with" naming.

---

## 8. Checklists

### CODE (MUST/SHOULD, [B]=backend, [M]=mobile, [W]=web)
- MUST [B] Request offline access for YouTube connect with `access_type=offline`, `include_granted_scopes=true`, scopes limited to the 3 YouTube + basic; sign-in requests basic scopes only.
- MUST [B] Encrypt refresh tokens at rest; TLS everywhere.
- MUST [B] Disconnect endpoint: call `https://oauth2.googleapis.com/revoke`, delete tokens, queue data purge in <= 7 days.
- MUST [B] Handle `invalid_grant`: mark revoked, stop jobs, purge Authorized Data within 30 days (target 7).
- MUST [B] Authorization re-check at least every 30 days per account (daily job qualifies); record `last_authorized_check_at`.
- MUST [B] Refresh-or-delete cached titles/descriptions/thumbnails/channel names within 30 days; never keep API-key-only (non-authorized) data > 30 days.
- MUST [B] Account deletion + data deletion API, completing within 7 days incl. YouTube data, uploads, snapshots; send confirmation.
- MUST [B] Do not compute derived metrics from YouTube data beyond simple sums/averages/raw graphs; label any computed value as ours.
- MUST [B] Never use Google user data for ads or share with brokers; no human access to data by staff without consent (build admin tooling accordingly).
- MUST [B] Queue/rate-limit uploads to the 100/day project bucket; per-user caps; surface "quota exceeded" gracefully.
- MUST [B] Enforce title <=100 chars, description <=5000 bytes, strip `<` `>`; require privacy status choice.
- MUST [W][M] "Disconnect YouTube" and "Delete account" in settings; deletion UX describes what is deleted/retained.
- MUST [W][M] Publish form: title, description, privacy (public/private/unlisted), platform select/deselect, Community Guidelines checkbox.
- MUST [W][M] Show YouTube ToS link and privacy policy link on connect/consent and settings screens; in-context disclosure before OAuth.
- MUST [W] Public pages: homepage (not login-only) , /privacy, /terms, /delete-account (public deletion request page, works without the app), Limited Use sentence, YouTube ToS + Google Privacy Policy links, revoke-access instructions.
- MUST [W][M] "Last updated" timestamps on stats; YouTube logo/icon (clickable) next to YouTube data.
- MUST [M] In-app account deletion path in account settings (Play).
- SHOULD [B] Cap snapshot history at 36 months unless Analytics & Reporting use case approved; store `source` and `fetched_at`.
- SHOULD [B] Batch videos.list (50 ids) or videos.batchGetStats; log quota usage per endpoint.
- SHOULD [B] Resumable upload with exponential backoff (500/502/503/504), resume on failure.
- SHOULD [B] Validate Shorts: aspect ratio square/vertical and duration <= 3 min before upload; warn otherwise.
- SHOULD [W][M] Tell user that unaudited uploads may be private-locked until the project passes the audit.
- SHOULD [B] Deletion audit log, retained-data policy documented.

### CONSOLE (Nonso does these)
Google Cloud Console (APIs & Services):
1. Create/choose production project; enable YouTube Data API v3 and YouTube Analytics API.
2. Verify clippipeline.nonsostudio.com in Google Search Console (same Google account as project owner); add as Authorized domain.
3. OAuth consent screen: External; app name ClipPipeline; logo; support email; Homepage `https://clippipeline.nonsostudio.com/`; Privacy policy `https://clippipeline.nonsostudio.com/privacy`; Terms `https://clippipeline.nonsostudio.com/terms`; authorized domain `nonsostudio.com` (verify the exact registrable domain; the subdomain host is clippipeline.nonsostudio.com). (URL paths are suggested; match whatever the web agent builds.)
4. Add scopes: openid, email, profile, youtube.upload, youtube.readonly, yt-analytics.readonly; paste justifications (section 1); note scope sensitivity labels shown.
5. OAuth client(s): Web client with redirect URI on clippipeline.nonsostudio.com (exact match, https); Android client (package + SHA-1) if native sign-in used.
6. Add yourself and testers as Test users while Testing (tokens expire in 7 days); then Publish App -> Prepare for verification -> upload demo video -> Submit. Watch the OAuth user cap page.
7. Enable quota monitoring; check videos.insert bucket value on the Quotas page.

YouTube API Services:
8. Submit Audit and Quota Extension form (https://support.google.com/youtube/contact/yt_api_form): describe service, link homepage/privacy/terms, attach demo video, request Analytics & Reporting use case and amendment, request videos.insert uploads/day increase after audit.
9. Calendar: re-audit may come; keep Periodic Audit form handy.

Google Play Console (PLAY-DEL, PLAY-DS):
10. App content -> Data safety: declare (collected) Personal info: name, email address, user IDs; Photos and videos (user uploads); App activity: other user-generated content (titles/descriptions), in-app actions; App info and performance: crash logs/diagnostics if used; Device or other IDs only if used; Google user data fetched from YouTube (channel info, statistics) falls under Personal info/App activity as applicable, purposes App functionality, Analytics, Account management. Mark data as shared only if sent to third parties that are not service providers (processors acting on our behalf are excepted). Declare encryption in transit (TLS) only if true for all data. Mark optional vs required correctly (https://support.google.com/googleplay/android-developer/answer/10787469).
11. Data safety: "Does your app let users request that data be deleted?" Yes; enter the web deletion URL `https://clippipeline.nonsostudio.com/delete-account` (must reference app/developer name as on the store listing; if linking to the policy, anchor directly to the deletion section) (PLAY-DEL). Optional: automatic deletion within 90 days also counts.
12. Provide privacy policy URL `https://clippipeline.nonsostudio.com/privacy` in store listing / App content.
13. Declare Account deletion: both in-app path and web link; account creation apps are in scope even if sign-up happens via web flow.
14. Do not name app "YouTube..." and keep listing free of YouTube logos as your own branding.
15. Test the web delete link works logged-out.

---

## UNVERIFIED summary
- Per-scope sensitive/restricted labels for the 3 YouTube scopes (check console).
- Demo video exact spec pages beyond VR summary (fetched summary only).
- reports.query exact quota cost.
- Whether unapproved projects may keep authorized stats indefinitely (III.E.4.b says yes with 30-day auth check; DPG hints approval needed); conservative approach above.
- Official wording of Community Guidelines certification, Limited Use long form, upload file size caps, YouTube brand sizes, Google Sign-In button rules.
- videos.insert quota cost conflicts between docs (1600 vs 100 vs 1).
- Private-lock status on current videos.insert reference page (conflicting).
