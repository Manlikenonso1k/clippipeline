# P5 Profile audit

Mobile: `clippipeline_mobile/src/app/(tabs)/profile.tsx` (P = that file). Only `GET /me` exists for the user (CONTRACT.md:61, returns id,name,email,avatar). No plan, prefs, PATCH/DELETE /me. Filament panel (`AdminPanelProvider.php`) has `->login()` and AccountWidget only; no profile page, no billing page. Web legal views exist: `/terms`, `/privacy` (routes/web.php:12-13). Billing POST routes exist (web.php:21-24) but no GET billing/pricing page. Guest mode: no gating exists; screen shows name "Guest" (P:83), initials "G", and still shows Pro plan, toggles and Log Out. "Sheet" = the guest "Sign in to do this" sheet (new, wiring only).

| # | Element | Mode | Should do | Now (file:line) | Mobile status | Web equivalent / status | Fix needed |
|---|---|---|---|---|---|---|---|
| 1 | Back arrow | both | router.back | P:146-153 works; on a tab with no history it may no-op | works (edge: no stack) | n/a | Fallback to tabs home when `!router.canGoBack()` |
| 2 | Header bell "Notifications" | both | Open notifications / alerts prefs | P:159-165 no onPress | dead | none | Wire to notification-prefs sheet (see #17) or remove; guest: sign-in sheet |
| 3 | Avatar (initials, glow) | logged-in | Show `user.avatar`, tap to change | P:177-195 initials only, never uses `user.avatar`; not pressable | demo (ignores real avatar) | Filament avatar via AccountWidget | Render `user.avatar` when present; change-avatar needs `POST /me/avatar` (missing) |
| 4 | Name / verified tick / email | logged-in | Real user; tap to edit name | P:83,198-202 real from `useMe`; verified tick shown for any user (P:199) | works (tick cosmetic); no edit | Filament user menu shows name, no edit (no `->profile()`) | Edit Profile sheet -> `PATCH /me` (missing); drop/derive verified tick |
| 5 | Handle `@...` | logged-in | Show handle | P:91-92 first connected account handle | works (derived) | none | none |
| 6 | Guest header state | guest | "Sign in" CTA instead of "Guest" + fake data | P:83 "Guest" label, no CTA | broken | n/a | Detect no token (authStore) -> Sign in button -> `/login`; hide plan/usage/logout |
| 7 | Plan pill "Pro Tier Active" | both | Real plan | P:207-211 hardcoded | demo-data (even guest) | none (no plan model on API) | Needs plan in `/me` or `GET /billing/subscription` (missing); until then show "Free" or hide |
| 8 | Stats strip (Syndicated / Views / Platforms) | logged-in | Real 30d overview | P:75-100 `useOverview('30d')`, skeleton while loading | works; error not shown | Filament AnalyticsOverview widget | Add error InlineMessage for overviewQ; guest: dashes |
| 9 | Subscription text "Pro Plan ($29/mo) - Renews Nov 14, 2024" + "Active" pill | both | Real plan / renewal | P:240-247 hardcoded, date in the past | demo-data | none | As #7; remove hardcoded date |
| 10 | Usage meter "x / 100 clips", "Resets in 16 days", "% remaining" | logged-in | Real quota | P:50,93-94,256-273 `CLIP_LIMIT=100` const; posts_published 30d; "16 days" hardcoded | demo-data (partly real) | none | Needs quota + period from API (missing) |
| 11 | "Ingest & Transcode 1080p / Unlimited" | both | Plan feature | P:276-289 static, not pressable | demo-data | none | Remove or bind to plan |
| 12 | Upgrade to Agency ($79/mo) button | both | Open web billing/pricing in browser | P:292 AnimatedPressable, no onPress | dead | Billing POST routes only (web.php:21); no GET pricing page | `Linking.openURL(web billing page)` (page missing); guest: sheet |
| 13 | Compare All Plans toggle | both | Expand tier matrix | P:312 togglePlans works | works (content static TIERS P:52-68) | none | Tier data hardcoded; ok until plan API |
| 14 | Tier "Upgrade" chip (Agency) | both | Same as #12 | P:343 Pressable, no onPress | dead | as #12 | Same handler as #12 |
| 15 | Toggle Auto-Syndication Engine | logged-in | Persist per-user preference | P:363 local `useState` only, resets on leave, never sent | dead (local-only) | none | `PATCH /me/preferences` (missing); guest: sheet |
| 16 | Toggle Watermark Removal | logged-in | Same | P:370 local useState only | dead (local-only); plan-gated | none | As #15 |
| 17 | Toggle Push Notifications | logged-in | Enable/disable push, register/unregister token | P:377 local useState only; `notifications.ts` has `syncPushTokenToBackend` (:74) and `DELETE /device-tokens` exists, both unused here | dead | none | On: `requestNotificationPermissions` + `syncPushTokenToBackend`; off: `DELETE /device-tokens`; persist in settingsStore (only has `alertSound`, no UI) |
| 18 | Row Default Hashtags & Captions | logged-in | Edit presets | P:380 chevron, no onPress | dead | none | New sheet + prefs endpoint (missing) or remove; guest: sheet |
| 19 | Row Connected Accounts | both | Open connect flow | P:385-400 `router.push('/connect')`; badge + platform icons real | works (guest should get sheet) | `/integrations` routes (web.php:45+) | Guest gate |
| 20 | Row Download Analytics Data (CSV) | logged-in | Export CSV | P:402 no onPress | dead | none; no export endpoint in CONTRACT | `GET /analytics/export.csv` (missing) then share; or hide |
| 21 | Row Creator Community & Help Center | both | Open support URL | P:407 no onPress | dead | none | `Linking.openURL(support/Discord/mailto)`; URL decision needed |
| 22 | Row Terms of Service | both | Open /terms | P:409 no onPress | dead | `/terms` exists (web.php:12) | `Linking.openURL(origin + '/terms')` or expo-web-browser |
| 23 | Row Privacy Policy | both | Open /privacy | P:411 no onPress | dead | `/privacy` exists (web.php:13) | same as #22 |
| 24 | Log Out of Pipeline | logged-in | Revoke token, clear, go to login | P:131-137,415 clears local token, `router.replace('/login')` | works locally; no server revoke, no react-query cache clear, device token not unregistered | Filament user menu Sign out | `DELETE /device-tokens` before clear, `queryClient.clear()`; optional `POST /auth/logout` (missing); hide for guest |
| 25 | Delete account | logged-in | Required by store policy | not present | missing | none | Row + confirm sheet -> `DELETE /me` (missing) |
| 26 | Edit profile / change password | logged-in | Edit name | not present | missing | none | `PATCH /me` (missing) |
| 27 | App version footer | both | Real version | P:421 hardcoded "v3.8.4 (Build 882)" (real versionCode is 10) | demo-data | n/a | Use `expo-application` / `Constants.expoConfig.version` |
| 28 | Footer "Encrypted Cloud Storage - SOC2 Certified" | both | Claim | P:422 static | demo-data (unverified claim) | n/a | Remove claim |
| 29 | Dead style leftovers (tabBar, pressedUpgrade, pressedLogout) | n/a | none | P:679,752,759-776 | n/a | n/a | cosmetic cleanup |

Counts (29 rows): works 7 (#1,4,5,8,13,19,24), dead 13 (#2,12,14,15,16,17,18,20,21,22,23 plus bell/avatar-edit overlap counted once), demo-data 7 (#3,7,9,10,11,27,28), broken 1 (#6), missing 2 (#25,26), n/a 1 (#29). Guest-gating applies to every account-only row; none implemented.

## States & data source
- Real API: `useMe` (user; loading skeleton P:198; error InlineMessage P:203), `useOverview('30d')` (stats, skeleton), `useSocialAccounts` (connected count, handle, icons; error subtitle P:390).
- Missing states: overview error not shown; no pull-to-refresh; no empty state beyond "No accounts linked yet"; no guest state; 401 handling not verified here.
- Fake even when logged in: plan name/price/renewal, 100-clip limit, reset days, ingest/transcode, tier matrix, version, SOC2 line, all three toggles (local state only). settingsStore.ts holds only `alertSound` (hydrated in _layout, no UI here); authStore handles only the token.
- notifications.ts has permission, token registration and sync helpers plus a local test notification; none are called from the profile toggles.

## Missing endpoints
- `PATCH /me` (name; avatar upload `POST /me/avatar`)
- `DELETE /me` (account deletion)
- Preferences: `GET/PATCH /me/preferences` (push, auto-syndicate, watermark, default hashtags/captions)
- Plan/subscription/quota: `GET /billing/subscription` or fields on `/me` (plan, renews_at, clip_limit, period reset)
- Web GET billing/pricing page for the Upgrade buttons (only POST billing routes exist)
- `POST /auth/logout` (token revoke), optional
- Analytics CSV export endpoint
- Support URL (content decision) and a public web origin constant for /terms and /privacy
- Web: Filament has no `->profile()` page, billing page, or preferences; add if parity wanted.
