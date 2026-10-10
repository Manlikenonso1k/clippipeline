# Build Instructions — Mobile App + API Backend (2-project blueprint)

**Audience: an AI coding agent.** Paste this file into the root of each project
you scaffold. It encodes a proven architecture for a white-label, multi-tenant
operations app made of **two projects**:

1. **`<app>-mobile`** — Expo + React Native + TypeScript (Android/iOS, + web for dev testing).
2. **`<app>-api`** — Laravel + Filament backend that exposes a JSON API *additively*
   (the API is added to an existing/able-to-grow Laravel app; it never rewrites it).

Replace `<app>`, colours, roles and domains with the new product's values. Keep the
rules — they are the parts that are expensive to learn the hard way.

---

## 0. Golden rules (non-negotiable)

1. **Build real features, never fake them.** No mock data paths in shipped code, no
   "pretend" buttons. If a thing appears in the UI, it is wired to the API and works.
2. **The server owns truth and time.** Countdowns, due times, "now", status colours,
   and money totals are computed on the server and sent to the client. The client
   never trusts the device clock for anything that matters.
3. **Money is integer minor units (kobo/cents) end-to-end.** Never store or compute
   money as floats. Format to currency only at the UI edge.
4. **Multi-tenant isolation is absolute.** Every tenant-scoped row has a `tenant_id`.
   The tenant is resolved **from the authenticated token**, never from a request
   parameter. One tenant can never see another's data.
5. **Roles are assigned by the business, never self-selected.** The API delivers the
   user's role; the app routes to the right home. A user cannot pick "Manager".
6. **Additive backend.** New tables and routes only; do not break existing ones.
   Every migration is reversible and safe to run on a live DB.
7. **The author is the human.** Commit as the user; do not add AI co-author trailers
   unless the user asks.
8. **Track features.** When a feature ships, update `features.md` (marketing list)
   and the README highlights. Keep them honest.

---

## 1. Stack (pin these; they interlock)

**Mobile (`<app>-mobile`)**
- Expo SDK (latest stable) · React Native · React · TypeScript (strict).
- Expo Router (file-based navigation).
- TanStack Query (server state via short polling) + Zustand (client/auth state).
- React Hook Form + Zod (forms + validation).
- `expo-secure-store` (token on native) · `expo-notifications` + `expo-audio` +
  `expo-haptics` (alerts) · `@expo/vector-icons` (MaterialIcons only — no emoji).

**API (`<app>-api`)**
- Laravel (latest) + Filament (admin panel) + Sanctum (token auth) +
  `spatie/laravel-permission` (roles).
- MySQL/Postgres in prod; SQLite fine for tests.

**Version pinning rule:** pin native modules to the Expo SDK's bundled versions
(`node_modules/expo/bundledNativeModules.json`). Do **not** let npm float them — a
mismatched native module (e.g. `react-native-reanimated` vs `react-native-worklets`)
breaks the cloud build. Declare transitive native deps you rely on explicitly in
`package.json` at the bundled version.

---

## 2. Project layout

```
<app>-mobile/
  app/                 # Expo Router routes (file = screen)
    _layout.tsx        # root: fonts, query client, hydrate auth + settings
    index.tsx          # boot router — waits for token hydration, routes by role
    (app)/             # authenticated screens; _layout.tsx defines the tab bar per role
  src/
    api/               # client.ts (fetch wrapper), hooks.ts (all useQuery/useMutation), types.ts
    components/        # shared UI (Button, Card, headers, alert popups)
    store/             # zustand stores (authStore, settingsStore)
    lib/               # format.ts (money/time), roles.ts (role→home map), clock.ts, alert.ts
    theme.ts           # design tokens (colours, fonts, type scale) — single source
  app.config.ts        # variants (dev/staging/prod): name, bundle id, API URL, EAS id
  eas.json             # build profiles (preview=apk, production=aab)
  features.md, README.md

<app>-api/
  app/Http/Controllers/Api/   # thin controllers, one per resource
  app/Http/Resources/         # JSON shaping (snake_case, computed fields)
  app/Models/                 # Eloquent + BelongsToTenant trait
  app/Services/               # business logic (the real brain, e.g. OrderService)
  app/Notifications/          # + Channels/ExpoChannel.php for push
  database/seeders/           # DemoSeeder (+ DemoOrdersSeeder) — idempotent
  database/migrations/        # additive, every table has tenant_id
  routes/api.php              # grouped by auth:sanctum + role middleware
  tests/Feature/              # one file per capability
```

---

## 3. Backend blueprint

### Auth & identity
- `POST /api/auth/login` — email **or** phone + password → Sanctum token.
- `GET /api/me` → `{ user: {id,name,roles[],department}, tenant: {...}, prices }`.
  The app reads role/tenant/config from here; nothing is hardcoded client-side.
- `users.email` is **nullable** (phone-only staff must be creatable).

### Multi-tenancy
- A `BelongsToTenant` trait adds a global scope that filters every query by the
  current tenant, and stamps `tenant_id` on create. Resolve the current tenant from
  `request()->user()->tenant_id` — **never** a header or body field.
- Provide `Tenant::runAs($id, fn)` for seeders/jobs/tests that run outside a request.

### Roles & the assignment matrix
- Seed roles once. Who can create whom is a **strict matrix enforced in the
  controller**, e.g.: Admin→any; Manager→floor roles; Owner→Manager + floor roles.
- Route protection: a `role` middleware, e.g. `->middleware('role:Manager,CEO,Admin')`.

### Resources & money/time
- API Resources return `snake_case`, integers for money, ISO-8601 for times, plus
  **server-computed** fields the client must not derive: `server_time`,
  `seconds_remaining`, `status_colour`, etc.
- Keep business logic in `app/Services/*`, not controllers. Controllers validate,
  call the service, return a Resource.

### Notifications / push
- A single `ExpoChannel` posts to `https://exp.host/--/api/v2/push/send` for every
  device token the recipient registered (`POST /api/device-tokens`).
- Notifications implement `via() => ['database', ExpoChannel::class]` so there's an
  in-app feed (DB, polled) **and** OS push. Do **not** `implements ShouldQueue`
  unless a queue worker is guaranteed in prod — otherwise pushes silently never send.

### Testing
- `tests/Feature/*`, `RefreshDatabase`, one file per capability. Helpers: make a
  tenant, make a user with a role (`Role::findOrCreate` + `assignRole`), auth via a
  bearer token (`$this->app['auth']->forgetGuards()` before each new token).
- Assert the matrix (who can/can't), the tenant isolation, and the money/time math.

### Demo data (so every screen is demoable)
- `DemoSeeder`: one tenant, one account per role (known password), menu/catalog,
  and config. Idempotent (`updateOrCreate`).
- `DemoOrdersSeeder`: a realistic spread of the core record in **every state**
  (on-time, late, overdue, in-progress, ready, pending) across departments and
  staff, so the board/queue/dashboard all have data. Clears its own prior demo
  records first so re-runs stay clean.
- A `demo:reset` artisan command: clear just the demo records, or `--wipe` the
  whole demo tenant. Require `--force` on production.

---

## 4. Frontend blueprint

### Boot & routing
- `app/index.tsx` waits for the token to hydrate from storage, then routes by role
  using a `homeForRoles(roles)` → route map. `(app)/_layout.tsx` builds the tab bar
  from the role (different tabs per role; register all routes, hide non-tabs via
  `href: null`).

### Data layer
- **All** server calls go through `src/api/hooks.ts` (TanStack Query). One `apiFetch`
  wrapper in `client.ts` adds the bearer token + `Accept: application/json` and
  throws a typed `ApiError`. Types live in `types.ts`.
- Poll at sensible cadences (e.g. board 10s, lists 30s, dashboard 60s). Use
  `placeholderData: keepPreviousData` for anything with a filter/period switch so the
  screen never blanks to a spinner when the key changes.
- Mutations invalidate the relevant query keys on success.

### State
- `authStore` (zustand): token in `expo-secure-store` on native, **`sessionStorage`
  on web** (per-tab, so multiple browser tabs = independent logins for dev testing).
- `settingsStore`: per-device prefs (e.g. alert sound on/off), persisted the same way
  (SecureStore native / `localStorage` web). Wrap every storage read/write in
  try/catch; it can be blocked/empty.

### Theming
- One `theme.ts`: colour tokens + an Inter type scale. No inline hex, no `fontWeight`
  with custom fonts (use the right family). MaterialIcons only; never emoji.

### Alerts (the "pay attention now" moments)
- Foreground: custom flashing pop-ups driven by polling (new record, nudge, ready).
  Pair each with `expo-audio` chime + `expo-haptics` buzz, gated by a settings
  toggle. Lazy-import audio/haptics and wrap in try/catch (no-op on web/Expo Go).
- Background/closed: only OS push reaches the user (see §5 FCM).

### Money & time at the edge
- `formatNaira(kobo)` (or your currency) formats only for display.
- Sync a server-time offset from each response's `server_time`; render countdowns
  against that offset, never `Date.now()` directly.

---

## 5. Build, deploy, push

### API URL resolution (`app.config.ts` + `config.ts`)
- Order: explicit `EXPO_PUBLIC_API_BASE_URL` → (dev) derive the PC's LAN IP from the
  Metro host so a changing DHCP address needs no edit, falling back to
  `localhost:<port>` on web/same machine → (staging/prod) the baked per-variant URL.
- The mobile backend is the **same** server as the web app when they share a Laravel
  codebase: point the app at `https://<domain>/api`. No separate hosting.

### EAS builds
- `eas.json`: a `preview` profile that outputs an **installable `.apk`**
  (`distribution: internal`, `android.buildType: apk`) to hand to stakeholders, and a
  `production` profile (`.aab`) for the stores. `--profile production` is NOT
  installable by tapping a link — use `preview` for that.
- Commands: `eas login` (human must do this — credentials), `eas init --id <id>`,
  `eas build --profile preview --platform android`.
- **Slug must match the EAS project's slug**, or the build is refused.

### Lockfile gotcha (Windows dev → Linux build)
- A `package-lock.json` generated on Windows can omit Linux-only RN deps and break
  `npm ci` on EAS. Either declare those deps explicitly and commit a complete lock,
  or keep the lock out of the repo and build with `EAS_BUILD_SKIP_LOCKFILE_CHECK=1`
  (EAS then runs `npm install` on its own platform). Add `.npmrc` with
  `legacy-peer-deps=true` to tolerate RN's peer-dep noise.

### Push notifications
- **Android standalone needs Firebase Cloud Messaging (FCM) credentials** or Expo
  cannot deliver push (works in Expo Go without, fails in a real build). Set up a
  Firebase project, add `google-services.json` (`android.googleServicesFile`), and
  upload the FCM V1 service-account key via `eas credentials`. Rebuild after.
- iOS push uses APNs, which EAS manages with an Apple account.

### Backend deploy (e.g. Hostinger VPS)
- `git pull` → `composer install --no-dev -o` → `php artisan migrate --force` →
  cache config/route/view. HTTPS on.
- **Run the scheduler** (`* * * * * php artisan schedule:run`) — overdue/escalation
  logic depends on it; without it, time-based features silently never fire.
- Run a queue worker only if you queued anything.

### Local-install gotcha (Windows npm)
- If `npm install` is blocked by a scripts policy, use `npm install --ignore-scripts`
  and pin versions from `bundledNativeModules.json`; `npx expo install` may fail.

---

## 6. How an agent adds a feature (end to end)

1. **Backend first:** migration (additive, `tenant_id`) → model (+`BelongsToTenant`)
   → service method (the logic) → controller (validate → service → Resource) → route
   (with role middleware) → Feature test. Run the suite; it must stay green.
2. **Types + hook:** add the response type in `types.ts` and a `useX` hook in
   `hooks.ts`. Run `tsc --noEmit`.
3. **Screen/UI:** build to the design tokens; handle loading (skeleton, not a bare
   spinner), error, and empty states. No blanking on refetch.
4. **Docs:** update `features.md` + README. If it's native (new Expo module), note a
   rebuild is required (OTA won't carry native changes).
5. **Verify for real:** hit the live endpoint and confirm the screen shows real data.
   State plainly what you tested and what you didn't.

---

## 7. Gotchas we already paid for (don't rediscover)

- Blank dashboard on filter switch → `keepPreviousData` + skeleton.
- Pull-to-refresh spinner flashing every poll → drive the refresh indicator from a
  manual flag, not `isRefetching`.
- "No notifications when minimized" → that's OS push; needs FCM set up (§5). The
  flashing in-app alerts only run while the app is foregrounded (polling).
- Duplicate records from a double-tap → client ref-guard + a short server-side
  duplicate window.
- A notification sent to a department with no on-shift recipient goes nowhere → make
  sure every station/role has at least one account, and seed accordingly.
- Demo credentials only exist where the seeder ran. Production has none until you
  seed or create real accounts — logging in "fails" for a user that doesn't exist.

---

Keep this file in the repo root of both projects and follow it. When in doubt,
prefer: server-authoritative, tenant-scoped, integer money, real features, green
tests, honest docs.
