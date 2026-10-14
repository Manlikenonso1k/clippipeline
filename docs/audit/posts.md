# Audit P2: Posts, Post details, Create post

Mobile: `src/app/(tabs)/posts.tsx` (P), `src/app/post/[id].tsx` (D), `src/app/post/new.tsx` (N), `src/api/hooks.ts`, `src/api/demo.ts`.
Web: `app/Filament/Resources/Posts/**`. Post endpoints that exist: `GET /posts`, `POST /posts`, `GET /posts/{id}`, `POST /distributions/{id}/retry`, `GET /posts/{id}/analytics`. There is NO `DELETE /posts/{id}` endpoint (web has DeleteAction / DeleteBulkAction through Eloquent directly) and no update/edit endpoint.
"Guest" = no token (demo dataset via placeholderData). The "Sign in to do this" sheet does not exist yet: guest mutations currently throw `Sign in to do this.` from `requireToken()` (hooks.ts:66) or show an Alert.

| # | Element | Mode | Should do | Now (file:line) | Mobile status | Web equivalent / status | Fix needed |
|---|---|---|---|---|---|---|---|
| 1 | Bell icon "Notifications" (P header) | Guest + logged-in | Open notifications, or sign-in sheet for guest | `posts.tsx:155` Pressable, no onPress | dead | none | Add handler: guest opens sign-in sheet; logged-in routes to a notifications / push settings screen (product decision) |
| 2 | Avatar "Account" (P header) | Guest + logged-in | Go to Account/Settings tab; guest: sign-in sheet | `posts.tsx:158` no onPress | dead | Filament user menu (works) | `router.push` to settings tab (logged-in) / sign-in sheet (guest) |
| 3 | Search icon (P) | Both | Search posts by title | `posts.tsx:173` no onPress | dead | `title` column `searchable()` in PostsTable (works) | Handler that reveals a search input and filters client-side, or add `?q=` to `GET /posts` |
| 4 | Filter channels icon (P) | Both | Filter by platform | `posts.tsx:176` no onPress | dead | No platform filter on web table (missing-on-web) | Existing-component sheet with platform chips, filter client-side on `distributions[].platform`; optional web SelectFilter |
| 5 | Filter chips All/Published/Processing/Scheduled/Failed (P) | Both | Filter list, show counts | `posts.tsx:185` setFilter -> `usePosts(filter)` -> `GET /posts?filter=`; counts from second `usePosts('all')` (`:121`) | works (guest: demo filter) | No status filter on web (status column only) = missing-on-web | Optional web SelectFilter. Verify backend `filter` values match contract |
| 6 | Pull-to-refresh (P) | Both | Refetch | `posts.tsx:152` useRefresh | works | Table polls 5s while in flight | none |
| 7 | Post card (P) | Both | Open post details | `posts.tsx:241` router.push `/post/[id]` | works (guest opens demo detail) | ViewAction in recordActions (works) | none |
| 8 | "More options" (...) on card (P) | Both | Menu: Share, Open on platform, Retry, Delete | `posts.tsx:255` no onPress | dead | Row actions: crossPost + View; delete only via bulk or ViewPost header | Add action sheet. Share = RN Share; Delete: guest sign-in sheet, logged-in needs NEW `DELETE /posts/{id}` + `useDeletePost` + confirm |
| 9 | Card "Retry" on failed card (P) | Logged-in | `POST /distributions/{id}/retry` | `posts.tsx:304` -> `retry()` `:133` -> `useRetryDistribution` hooks.ts:246; error via Alert | works | `DistributionsRelationManager.php:72` retry action (works) | none |
| 10 | Card "Retry" (P) | Guest | Sign-in sheet | same `posts.tsx:304`; mutate throws, shown as "Retry failed" Alert | broken (wrong UX) | n/a | Check `useIsGuest()` before mutate and open sign-in sheet |
| 11 | FAB "New cross-post" (P) | Both | Open create screen | `posts.tsx:494` router.push `/post/new` | works | CreateAction `ListPosts.php:16` (works) | none (guest: see #35) |
| 12 | Feed data (P) | Logged-in | Real API only | `usePosts` hooks.ts:97; demo only when no token (`placeholderData: token ? keepPreviousData : demoPosts`) | works (no demo leak) | same data model | none |
| 13 | Back button (D) | Both | router.back | `[id].tsx:167` | works | Filament breadcrumbs | none |
| 14 | Share icon (D header) | Both | Native share of title + live link | `[id].tsx:173` -> `share()` `:145`, RN `Share.share` | works | none (missing-on-web, low priority) | none |
| 15 | Play overlay on video (D) | Both | Play video | `[id].tsx:218` `Linking.openURL(post.video_url)` (external browser) | works (degraded); does nothing when `video_url` null (guest demo likely) | ViewPost has no player | Disable/hide when null; optional expo-video inline player |
| 16 | Per-platform "Open" link (D, published) | Both | Open post on platform | `[id].tsx:322` `Linking.openURL(d.url)` | works | `DistributionsRelationManager.php:50` Link column, new tab (works) | none |
| 17 | Per-platform "Retry" (D, failed) | Logged-in | `POST /distributions/{id}/retry`, inline error | `[id].tsx:337` -> `doRetry` `:151` | works | `DistributionsRelationManager.php:72` (works) | none |
| 18 | Per-platform "Retry" (D) | Guest | Sign-in sheet | `[id].tsx:152-155` `Alert.alert('Preview mode', ...)` | works but not the standard sheet | n/a | Swap Alert for shared sign-in sheet |
| 19 | "Re-authorize" (D, failed) | Both | Connect flow for that platform | `[id].tsx:343` router.push('/connect'), no platform param | works (generic) | Connected accounts resource | Pass platform param; guest: sheet |
| 20 | "Manage" link in channels header (D) | Both | Open connect screen | `[id].tsx:264` router.push('/connect') | works | same | Guest: sheet |
| 21 | Analytics tabs Aggregated / per-platform (D) | Both | Switch metrics view | `[id].tsx:376` setTab; data `GET /posts/{id}/analytics` | works | No post analytics on web View page = missing-on-web | Optional web widget using `AnalyticsController::post` |
| 22 | Platform analytics link button (D) | Both | Open post on platform | `[id].tsx:447` Linking.openURL(p.url) | works | see #16 | none |
| 23 | Bottom dock "New Post" (D) | Both | Open create | `[id].tsx:496` router.push('/post/new') (style named editBtn) | works | CreateAction | No edit endpoint (`PUT /posts/{id}` missing); if "Edit" was intended it cannot be built yet |
| 24 | Bottom dock "Refresh Status" (D) | Both | Refetch post + analytics | `[id].tsx:502` onRefresh | works | RelationManager polling | none |
| 25 | Pull-to-refresh (D) | Both | Refetch | `[id].tsx:179` | works | | none |
| 26 | Delete post (D / P) | Logged-in | Delete post | No UI element on mobile; no endpoint | missing | `ViewPost.php:16` DeleteAction, `PostsTable.php` DeleteBulkAction (works) | Add `DELETE /posts/{id}` (owner-only), contract entry, `useDeletePost`, confirm sheet from #8 |
| 27 | Video picker (N) | Logged-in | Pick MP4/MOV/WebM | `new.tsx:222` -> `pickVideo` `:139` (iOS permission, MIME check) | works | FileUpload `PostForm.php:26` (works) | Guest: open sign-in sheet at pick time instead of failing at submit |
| 28 | Title / Caption / Hashtags inputs (N) | Both | Text entry with limits | `new.tsx:245,256,268` | works | PostForm inputs (works) | none |
| 29 | Platform toggles (N) | Logged-in | Select connected, active platforms | `new.tsx:307` toggle(p); list from `useSocialAccounts` (`GET /social-accounts`) | works | PostForm "Publish to" `:55` | none; guest sees empty state |
| 30 | "Connect accounts" (N, empty state) | Both | Go to connect | `new.tsx:298` router.push('/connect') | works | | Guest: sheet |
| 31 | Schedule Switch (N) | Both | Toggle schedule | `new.tsx:337` | works | DateTimePicker `PostForm.php:41` | none |
| 32 | Preset chips In 1 hour / Tonight 8pm / Tomorrow 9am (N) | Both | Fill schedule | `new.tsx:350,353,362` | works | none | none |
| 33 | Date / Time text inputs (N) | Both | Manual schedule entry | `new.tsx:372,375` plain TextInput YYYY-MM-DD / HH:MM, validated | works (no native picker; UI frozen) | DateTimePicker | none |
| 34 | Publish / Schedule CTA (N) | Logged-in | `POST /posts` multipart with progress, then open detail | `new.tsx:417` submit -> `useCreatePost` hooks.ts:227 -> `router.replace('/post/[id]')` | works | `CreatePost.php` uses `PostUploadService`, same as API `PostController::store` (works) | none |
| 35 | Publish CTA (N) | Guest | Sign-in sheet | `new.tsx:166` inline error "Sign in to publish your own videos."; button styled disabled but tappable | broken (wrong UX) | n/a | If `guest`, open shared sign-in sheet in `submit()` |
| 36 | Back (N) | Both | router.back (disabled during upload) | `new.tsx:208` | works | | none (no cancel-upload) |
| 37 | TikTok-specific form fields (privacy, disclosure, comment/duet/stitch) | Logged-in | Per TikTok Direct Post rules | Not present. `new.tsx` only references tiktok in an icon map; `buildPostForm` sends title/caption/hashtags/platforms/scheduled_at | missing | Web `PostsTable.php:116` only has YouTube privacy Select = missing-on-web | Decide scope: add platform options sheet and extend `POST /posts` with `platform_options`, or document out of scope |
| 38 | Web: List "New post" CreateAction | Web | Create | `ListPosts.php:16` | n/a | works | none |
| 39 | Web: row action crossPost + modal | Web | Re-publish existing post to other platforms | `PostsTable.php` crossPostAction via `CrossPostService::optionsForUser` | missing-on-mobile | works | Optional mobile feature; needs endpoint exposing CrossPostService (none in routes/api.php). Web cross-post uses `CrossPostService`, API create uses `PostUploadService`: drift risk |
| 40 | Web: bulk cross-post / bulk delete | Web | Multi-select actions | `PostsTable.php` BulkActionGroup | missing-on-mobile | works | none (acceptable) |

## States & data source

- Data source: hooks call the real API (`/posts`, `/posts/{id}`, `/posts/{id}/analytics`, `/social-accounts`). `demo.ts` is used ONLY through `placeholderData` when there is no token (hooks.ts:103,125,137); with a token it is `keepPreviousData`/`undefined`, so no demo leak when logged in. Guest queries are `enabled: !!token` = false, so nothing hits the API.
- List (P): loading = 2 Skeletons; error = InlineMessage "Couldn't load data" (only when no cached data; no retry button, pull-to-refresh is the only recovery); empty = InlineMessage ("No posts yet" / "No clips in this state yet"). Chip counts come from a second `usePosts('all')` query that also polls.
- Detail (D): loading = Skeletons; error / not found = InlineMessage; polls every 5s while any distribution is in flight; retry errors shown inline per distribution. Analytics error/empty states not deeply verified.
- Create (N): accounts loading = spinner; accounts error = InlineMessage; no accounts = empty message + Connect button; upload shows progress %, error banner on failure; inline validation.
- Guest: no shared "Sign in to do this" sheet exists (Alert on D retry, inline text on N, raw-error Alert on P retry). Build one reusable sheet and call it from #1, #2, #8-#10, #18-#20, #26, #27, #35.
- Dead-pattern scan: no `onPress={() => {}}`, TODO/FIXME, console.log-only handlers or "coming soon" alerts in the three files. Dead items are Pressables with NO onPress (#1, 2, 3, 4, 8). No `href="#"`. Web: no Filament actions without an action.
- Parity: create path shares `PostUploadService` (OK). Delete exists on web only. Filter/search differ. Cross-post of an existing post exists on web only.
