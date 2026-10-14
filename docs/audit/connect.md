# Audit P3 - Connect (+ Login)

Files: mobile `src/app/(tabs)/connect.tsx`, `src/app/login.tsx`, `src/api/hooks.ts`, `src/api/demo.ts`, `src/app/_layout.tsx`. Web: `app/Filament/Pages/ConnectAccounts.php`, `resources/views/filament/pages/connect-accounts.blade.php`, `routes/web.php` (integrations.*).
Guest = no token (DEMO_ACCOUNTS shown). Status keys: works / broken / dead / demo-data-when-logged-in / missing-on-web.

| # | Element | Mode | Should do | Now (file:line) | Mobile status | Web equivalent / status | Fix needed |
|---|---|---|---|---|---|---|---|
| 1 | Connect button per provider (YouTube/Meta/TikTok; not connected or partial) | Logged-in | GET /connect/{p}/start?platform=mobile, open auth_url, return, refetch | connect.tsx `connect()` ~L125-147, ConnectButton; hooks.ts useConnectStart L190 | works | Connect link -> integrations.redirect (works) | None. Optional: handle expires_at / timeout |
| 2 | Connect button | Guest | "Sign in to do this" sheet | connect.tsx `connect()` ~L127: native Alert with Sign in -> /login | works (Alert, not a sheet) | n/a (web requires login) | Swap Alert for shared SignInSheet if one exists; behaviour OK |
| 3 | Reconnect link (all rows active) | Logged-in | Re-run OAuth | connect.tsx ~L300 -> connect() | works | Reconnect link (works) | None |
| 4 | Reconnect link | Guest | Sign-in sheet | same Alert | works | n/a | As #2 |
| 5 | "Reconnect Provider" button (partial / needs_reauth) | Logged-in | Re-run OAuth | connect.tsx ~L312 ConnectButton label | works | Reconnect (works) | None |
| 6 | Disconnect (all active) | Logged-in | DELETE /social-accounts/{id} after confirm | connect.tsx `disconnect()` ~L150-175; hooks.ts L200 | works, but sends only `first` account id; Meta relies on backend removing both FB+IG rows (unverified) | Per-row Disconnect Filament action with confirm (ConnectAccounts.php:87-106) | Verify SocialAccountController::destroy deletes sibling meta row; else delete each row. No in-flight guard (double tap) |
| 7 | Disconnect (not all active) | Logged-in | same | connect.tsx ~L320 | works (same caveat) | same | same |
| 8 | Disconnect | Guest | hidden (`!guest`) | connect.tsx L300/L320 | works (hidden) | n/a | Optional: show and open sign-in sheet |
| 9 | Per-platform status rows (Active/Re-auth/Off, followers, last synced, error) | Logged-in | Real GET /social-accounts | connect.tsx ~L255-280; hooks.ts L86 | works | Rows show handle, followers, token expiry, last synced, error (works) | Token expiry not shown on mobile; optional |
| 10 | Status rows | Guest | Preview demo data | hooks.ts L92 placeholderData DEMO_ACCOUNTS (instagram needs_reauth) | works (guest only; with token, real API only) | n/a | None; "Preview" pill shown |
| 11 | "N of 3 Connected" / re-auth pill | Both | derive from accounts | connect.tsx ~L205-230 | works | not present | None |
| 12 | OAuth return via openAuthSessionAsync(auth_url, 'clippipelinemobile://connect') | Logged-in | parse provider/status/message, show notice, refetch | connect.tsx L137-141, parseConnectUrl | works (always refetches on cancel/dismiss) | Web: flash status on /admin/connect-accounts (works) | None |
| 13 | OAuth return via deep link /connect?provider&status&message (Android, cold start) | Logged-in | notice + refetch + clear params | connect.tsx useEffect ~L108-118; scheme app.json L8; no custom Linking handler in _layout.tsx (Expo Router resolves it) | works | n/a | None |
| 14 | Notice banner (InlineMessage ok/error) | Both | show result | connect.tsx ~L235 | works | session('status') / errors callouts (works) | Success tone uses 'muted'; cosmetic, UI frozen |
| 15 | Push-tap -> /connect (account_needs_reauth) | Logged-in | open Connect | _layout.tsx L60-61 | works | n/a | None |
| 16 | Pull-to-refresh | Both | refetch accounts | connect.tsx RefreshControl + useRefresh | works | page reload | None |
| 17 | Top-bar avatar | Both | decorative, not pressable | connect.tsx ~L190 | works (static, not a dead pattern) | Filament user menu | Optional: tap -> /profile |
| 18 | Pipeline Engine Rules rows (Watermark Active / 1080p / OAuth) + "Defaults" badge | Both | read-only info | connect.tsx PIPELINE_RULES L50 hard-coded | demo-data-when-logged-in (static copy claims "Active" regardless of backend) | missing-on-web | Source from real setting or leave labelled Defaults; no interaction |
| 19 | Meta IG row with status=error (no IG business account linked) | Logged-in | show error text, explain | connect.tsx row maps any non-active to "Re-auth" / "Authorization expired" fallback | broken: 'error' mislabelled as re-auth; reconnect will not fix a missing IG link | Web badge "Error" distinct | Map status 'error' to own pill label "Error" and show account.error |
| 20 | Loading state | Logged-in | skeleton | connect.tsx L226 (one Skeleton line); buttons hidden | works (minimal) | n/a | None |
| 21 | Error state (load fail) | Logged-in | message + retry | connect.tsx L224 "Pull to retry" | works | n/a | None |
| 22 | Empty state | Logged-in | message | connect.tsx L228 | works | n/a | None |
| 23 | Help links | Both | none exist on mobile | n/a | n/a | none on web | none |
| 24 | Web: Filament disconnect action | Web | confirm + delete | ConnectAccounts.php:97 has `->action` | n/a | works | None |
| 25 | Web: Connect anchor `connect_url` | Web | OAuth start | blade L21 -> integrations.redirect | n/a | works (no href="#") | None |

Counts (mobile elements 1-22): works 20, broken 1 (#19), demo-static 1 (#18), dead 0. Dead-pattern grep (`onPress={() => {}}`, TODO/FIXME, "coming soon", console.log-only) in connect.tsx: none. Mobile-only (missing-on-web): #11, #14, #18.

## States & data source
- Logged-in data comes only from GET /social-accounts (`enabled: !!token`); `placeholderData: DEMO_ACCOUNTS` applies only with no token (hooks.ts L86-94). queryClient is cleared on token change (_layout.tsx ~L41), so demo data cannot leak into an account.
- Same endpoints as web: mobile `GET /connect/{provider}/start?platform=mobile` and web `integrations.redirect` end in the same callbacks (`/integrations/callback/*`); callback redirects to `clippipelinemobile://connect?...` for mobile (CONTRACT section 3). Disconnect: mobile DELETE /social-accounts/{id} vs web DisconnectService - confirm both share the same service.
- Loading: Skeleton, buttons hidden. Error: InlineMessage. Empty: InlineMessage. Mutation errors surface in the notice.
- Gaps: no handling of 10-minute state expiry; disconnect has no pending state.

## Login screen
| Button | Does now | Can it work? |
|---|---|---|
| Continue with Google (login.tsx signInGoogle) | AuthSession code+PKCE flow with redirect `makeRedirectUri({scheme:'clippipelinemobile',path:'oauth'})`, then POST /auth/google/token {code, redirect_uri} | **Broken in production.** (1) Google "Web application" OAuth clients reject custom-scheme redirect URIs (https or localhost only); Android/iOS clients use a reverse-client-id scheme, so `EXPO_PUBLIC_GOOGLE_CLIENT_ID` would have to be that client. (2) Backend `googleToken` uses Socialite with the web client id+secret (config/services.php) and `redirectUrl($redirect_uri)`; a code issued to an iOS/Android client cannot be redeemed with the web client. (3) `usePKCE: true` but the client never sends `code_verifier` to the backend, so the exchange fails with invalid_grant. (4) In Expo Go the redirect becomes `exp://`. |
| Continue with Facebook | code flow, same redirect, POST /auth/facebook/token | Plausible: Facebook accepts a custom-scheme redirect if listed in Valid OAuth Redirect URIs and the same string reaches the Graph exchange (backend does). Needs EXPO_PUBLIC_FACEBOOK_CLIENT_ID. No `state` check. |
| Continue with TikTok | manual authorize URL, same custom redirect, POST /auth/tiktok/token | Likely broken: TikTok Login Kit web flow requires https redirect URIs registered in the portal. No state check, no PKCE. |
| Sign in with email | POST /auth/login {email,password}; replace('/dashboard') | Works. No forgot-password or sign-up path. |
| Continue in Guest / Preview | router.replace('/dashboard') | Works. |
| Back arrow | router.back() | Works; fails silently with no history (use replace to /welcome). |
| Terms of Service / Privacy Policy | styled Text, no onPress | **Dead.** Fix: Linking.openURL to web legal pages. |

Google fix (recommended): bounce through an https backend route (e.g. `/auth/mobile/google/callback` that 302s to `clippipelinemobile://oauth?code=...`) registered on the web client and send `code_verifier` in the token body; OR use native Google Sign-In to obtain an `id_token` and add `POST /auth/google/token {id_token}` verifying the audience. The same https-bounce pattern fixes TikTok.
