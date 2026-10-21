# Web panel click test (clippipeline.nonsostudio.com/admin)

Mobile click-test lives in the mobile repo: `docs/QA_MOBILE.md`.
Prereq: sign in as an `is_admin` user (`php artisan app:make-admin <email>`); ideally one connected account per platform. Use a test account: TikTok posts stay private while TikTok is unaudited.

## Navigation
- [ ] Sidebar order is Home, Posts, Connect, Insights, Profile. TikTok Insights is not in the sidebar.
- [ ] Topbar bell shows the unread count; click opens Notifications; "Mark all read" clears the count.
- [ ] Footer shows Privacy, Terms, Support and the non-affiliation line.

## Home
- [ ] Period filter 7d/30d/90d changes KPIs, chart, platform breakdown, top posts; default is 7d.
- [ ] "Auto-sync active/idle" badge matches the mobile Home badge.
- [ ] KPI numbers equal the mobile Home numbers for the same period.

## Posts
- [ ] List shows only your posts; search by caption; Platform filter works.
- [ ] Open a post: per-platform status, links, metrics. Retry a failed distribution; it re-queues.
- [ ] Delete a post: confirm text says platforms are untouched; post and files go away.
- [ ] Scheduled post: "Cancel" removes it before it goes out.
- [ ] New post: step 1 upload mp4/mov + title; step 2 pick platforms. YouTube shows ToS link, guidelines checkbox, privacy locked to private while unaudited.
- [ ] TikTok selected: avatar and "Posting to @name"; privacy dropdown starts empty and lists only TikTok's options; unaudited notice shows.
- [ ] Comment/Duet/Stitch start off and are disabled when your TikTok settings disabled them.
- [ ] Disclose toggle: needs Your brand and/or Branded content; Branded disables "Only me"; labels appear.
- [ ] Over-max-duration video is rejected with a clear message.
- [ ] Step 3: video preview plays, summary lists destinations/caption/settings, music consent line (plus Branded Content Policy when branded) and the "few minutes" notice show; Publish creates the post.

## Connect
- [ ] Each provider card shows rationale and status; the YouTube card shows the ToS note.
- [ ] Connect/Reconnect returns with a success banner; Disconnect confirms and explains tokens are revoked; Meta disconnect removes Facebook and Instagram together.
- [ ] needs_reauth shows a reconnect state.

## Insights
- [ ] Period and platform filters change charts and top posts; numbers equal mobile Insights.
- [ ] "Last updated" subheading is present. Export downloads the CSV matching the mobile export.

## Profile
- [ ] Edit name and save. Toggle notifications/auto-sync, set default platforms/hashtags; they pre-fill new posts and appear on mobile.
- [ ] Plan, clips used and Upgrade link show; no SOC2 claim.
- [ ] Download CSV works. Privacy / Terms / Delete-account links open.
- [ ] Delete account: type DELETE, account and data are removed, you land on /delete-account.
- [ ] Sign out returns to the login page.

## Public pages (logged out)
- [ ] /privacy, /terms, /data-deletion, /delete-account load; /data-deletion/status?code= shows status.
