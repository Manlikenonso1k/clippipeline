# ClipPipeline Studio: master prompt for Claude Code

---

## Part A: for Nonso (how to run this)

### 1. Folder setup (once)

Put everything side by side in one parent folder (HANDOFF.md says `Documents/`):

```
Documents/
  clippipeline/                 git, branch main
  clippipeline_mobile/          git, branch feat/app-screens-auth-charts
  video-editing-/               git clone https://github.com/Manlikenonso1k/video-editing-
  _refs/claude-remotion-skill/  git clone https://github.com/haidrrrry/claude-remotion-skill
  _refs/anything2explainer/     git clone https://github.com/Vincentwei1021/anything2explainer
```

Copy this file to `clippipeline/docs/STUDIO_SPEC.md`. Open a terminal in `Documents/` and run `claude`.

### 2. Prompts to paste

**Prompt 1 (first session):**
```
Read clippipeline/docs/STUDIO_SPEC.md fully. It is the master spec for adding
"Studio" (video editing, reference-style matching and motion-graphics templates)
to ClipPipeline web and mobile. Do Phase 0 only, then stop and show me the plan
and your questions.
```

**Prompt 2 (every session after that, one phase per session):**
```
Continue ClipPipeline Studio. Read clippipeline/docs/STUDIO_SPEC.md and
clippipeline/docs/STUDIO_PROGRESS.md, then do the next unfinished phase only.
Follow the STOP rule at the end of the phase.
```

**Prompt 3 (add a new motion-graphics template, any time after Phase 3):**
```
Read clippipeline/docs/STUDIO_SPEC.md section 8 (Templates) and the
/remotion-motion-graphics skill. Make a new ClipPipeline template from this:
<paste the prompt-motion.com prompt, or describe the video you want>
It must be a parameterised template whose props use only the supported schema
subset, and it must work in 9:16, 1:1 and 16:9. If the idea came from someone
else's video, put their handle in meta.credit, set is_active to false, and tell
me so I can ask their permission first. Render it in all three aspects, check
frames at 0/25/50/75/100%, fix issues, then run templates:build and templates:sync.
```

**Feedback prompts (after you test something):**
```
The lower-third text is cut off in 1:1. Fix it and re-render the template preview.
```
```
Auto-cut removed words it shouldn't. Show me the cut list for asset <id> and loosen the filler rules.
```

One phase per session keeps you under the Pro usage limit. If a session runs out mid-phase, Prompt 2 resumes from `STUDIO_PROGRESS.md`.

---

## Part B: the spec (for Claude Code)

### 0. Context: what already exists

Read these before planning. Do not re-derive what they already say.

**Backend `clippipeline/`** (`composer.json` is the source of truth: Laravel 13, Filament 5, Sanctum 4, PHP 8.3 with `config.platform.php` pinned to 8.3.30).
- Production is at `clippipeline.nonsostudio.com`. `HANDOFF.md` says it runs on Hostinger shared hosting (queue via cron, no supervisor), while `DEPLOY.md` describes a VPS with supervisor. Find out which in Phase 0.
- It is a "post once, everywhere" tool. `POST /api/posts` → `PostUploadService::create()` stores the video on the `public` disk, HEAD-checks the URL, and creates one `post_distributions` row plus one job per platform (YouTube Shorts, Instagram Reels, Facebook Reels, TikTok).
- Key files: `docs/CONTRACT.md`, the API contract. A copy lives in the mobile repo, and both copies change together.
- `routes/api.php` and `routes/console.php` have `// --- agent:xyz ---` marked blocks. Add a `studio` block and edit only inside it.
- Other key files: `app/Services/PostUploadService.php`, `CrossPostService.php`, `app/Support/VideoResolver.php`, `app/Services/Account/MeService.php` (plans and `clips_limit`), `config/clippipeline.php`, `ExpoPushService.php`, and `app/Providers/Filament/AdminPanelProvider.php`.
- The `/admin` Filament panel is gated by `is_admin`.
- The private disk is `local` (`storage/app/private`).
- Ignore `graphify-out/`. It is mostly vendor noise.

**Mobile `clippipeline_mobile/`** (branch `feat/app-screens-auth-charts`): Expo SDK 57, Expo Router, React Native 0.86, React 19.2, TypeScript, react-query, zustand, zod.
- API: `src/api/{client,hooks,types,demo}.ts`. Guest mode shows demo data.
- Screens: the create-post screen is `src/app/post/new.tsx`. Tabs are in `src/app/(tabs)/_layout.tsx` (Home, Posts, Connect, Insights, Profile).
- Design system: "Kinetic Obsidian". Copy the `C` token object from `post/new.tsx`.
- `AGENTS.md` says never answer Expo APIs from memory: read the docs for v57.
- `npx expo install` may be blocked. The fallback is the version from `node_modules/expo/bundledNativeModules.json` with `npm install pkg@version`.
- Do not commit `package-lock.json`.
- On Windows, use `npx.cmd`.

**Nonso's `video-editing-/`** is a Claude Code video workspace. Reuse it:
- `scripts/vkit.py`: ffmpeg pipeline. It covers probe/inventory, contact sheets, `conform` (aspect fit with blurred fill, HDR tone-map, -16 LUFS), `transcribe` (faster-whisper or ElevenLabs Scribe, word timestamps, cached), silence and loudness detection, an EDL-driven `roughcut`, `check` (loudness, true peak, black frames, silences) and `export`.
- `plan/edl.example.json`: the EDL v1 format.
- `.claude/skills/remotion-*`: from remotion-dev/skills.
- `vendor/video-use/`: browser-use's video-use (MIT), useful for its craft rules.
- `CLAUDE.md`: craft rules worth keeping:
  - Cut on word boundaries: start 0.05–0.15 s before the first kept word and end 0.2–0.35 s after the last.
  - Apply captions last.
  - Master to -14 LUFS with true peak ≤ -1 dBTP, and duck music under speech.
  - Never linear easing.
  - Render, check, fix, with at most 3 passes.

**`_refs/claude-remotion-skill`** (MIT): the `remotion-motion-graphics` skill with 10 motion rules, `motion-patterns.md` (17 components) and `theme.ts`.

**`_refs/anything2explainer`**: PolyForm **Noncommercial** licence. Read the licensing rules in §2 before opening it.

**Product status:** some platform publishing still waits on developer-account work (TikTok audit, Meta App Review, YouTube compliance audit), and the mobile app is not in the stores yet. **Studio must be fully usable without any of that.** Every Studio flow ends in a downloadable MP4. "Send to cross-post" is an extra step that is disabled with a reason when no account is connected.

### 1. Goal

Add **Studio** to ClipPipeline web and mobile. A creator can:

1. **Upload their own clips**, or add them by link.
2. **Add a reference video** by upload or link. Studio analyses its style (pace, shot length, caption style, palette, loudness) and can apply that style to the edit.
3. **Cut**: auto-remove silences and filler words, cut on word boundaries, edit by deleting words in the transcript, and trim, split, reorder or delete segments on a timeline. Change aspect to 9:16, 1:1 or 16:9 with a blurred fill.
4. **Add motion graphics from a template gallery** like prompt-motion.com: category chips with counts, looping previews, a "Pause previews" toggle, and a "Customize" flow (what it's for, format, colours). Templates can be intro/outro segments, overlays (lower thirds, stats, stickers), caption styles or transitions.
5. **Preview, render, download**, and optionally **send to cross-post** through the existing publishing pipeline.

Later and optional: an **Explainer** generator (topic or script in, narrated motion-graphics video out), built from our own templates (Phase 6).

### 2. Ground rules

**Repo safety (from HANDOFF.md):**
- Never run `composer update`, `migrate:fresh` or `db:seed`, and never touch the production DB. Create migration files only.
- Ask before `composer require`.
- Never log or commit secrets.
- Work on branch `feat/studio` in both repos and commit at the end of each phase. Push or merge only when Nonso says so.

**Contract:** add Studio as `docs/CONTRACT.md` §17 in both repos, in the same commit pair. Mirror the types in mobile `src/api/types.ts`.

**Licences (hard rules):**
- **anything2explainer** is PolyForm Noncommercial, and ClipPipeline is a paid product. Do not copy its code, prompts, reference docs, storyboards, scripts, fonts or assets into these repos. You may read it only to understand its general workflow (script → voiceover → storyboard → shots → QC). Then design our own version. If Nonso gets written commercial permission from the author, he will say so.
- **claude-remotion-skill** (MIT) and **video-use** (MIT) may be copied and adapted. Keep their LICENSE files and add `THIRD_PARTY_NOTICES.md`.
- **Remotion** is free for individuals and companies of up to 3 people, including a SaaS.
  - Users may render **our templates with their own data**. Never accept user-supplied Remotion/JS code, and never generate component code at runtime. AI may only fill template props.
  - Add a note to `DEPLOY.md`: at 4+ people, a Remotion Company License is needed ("Automators": per-render pricing with a monthly minimum).
- **prompt-motion.com** is UX inspiration only (gallery, categories, customize flow). Its videos and prompts belong to their creators. Do not copy them into templates. Templates have a `credit` field for permissioned adaptations.
- **Fonts and music:** use only OFL/Google Fonts or fonts committed with their licence. Music comes from the user only; there is no bundled music library.

**Platform links:**
- Links to tiktok.com, instagram.com, youtube.com/youtu.be, facebook.com and x.com are fetched only when `STUDIO_LINK_FETCHER=ytdlp`. The default is `off`.
- Otherwise, return a 422 asking the user to upload the file.
- Reason: those platforms' terms prohibit downloading with unofficial tools, and ClipPipeline depends on their API approvals.
- Direct HTTPS video URLs (`Content-Type: video/*`) are always allowed.
- **Reference videos are for analysis only.** They are never placed in the user's edit or output. Delete the reference media after analysis, and keep the style profile and contact sheet.

**Usage limits (Nonso is on the Claude Pro plan):**
- Do one phase per session.
- Read only the files you need, and use grep before opening whole files.
- Never read video files. Use contact sheets and `vkit frames`.
- Don't spawn parallel subagents unless asked.
- Keep `docs/STUDIO_PROGRESS.md` current: what's done, what's next, decisions, open questions.

**Design:**
- Use the Kinetic Obsidian tokens on mobile and web.
- For new mobile screens, use the Stitch MCP (per mobile `AGENTS.md`) if it is connected. Otherwise follow the existing screens.
- Motion graphics follow the 10 rules in `remotion-motion-graphics`: no linear easing, multi-property staggered entrances, faster exits, a layered background, and render → look at frames → fix.

**Checks before declaring a phase done:**
- `php artisan test`
- `vendor/bin/pint`
- `npx tsc --noEmit` in each TS project
- `npx expo lint` in mobile
- Worker tests
- One real render where the phase touches rendering

### 3. Architecture

```
clippipeline/
  app/Studio/...               Laravel: models, controllers, services, jobs (namespace App\Studio)
  motion/                      Remotion project, shared by worker and web editor
    src/Root.tsx               registers EditTimeline + one composition per template
    src/theme.ts               from remotion-motion-graphics/assets/theme.ts, Kinetic Obsidian palette
    src/timeline/EditTimeline.tsx   renders a project EDL (section 7)
    src/templates/<slug>/{Template.tsx,schema.ts,meta.ts}
    src/templates/registry.ts
    scripts/build-registry.ts  writes motion/dist/templates.json (committed, small; no binaries)
  render-worker/               Node 22 + TypeScript service (pull model)
    src/{index,api,cache}.ts, src/jobs/*.ts
    tools/vkit.py              ported from video-editing-/scripts/vkit.py
    Dockerfile, .env.example, clippipeline-render.service (systemd)
  resources/js/studio/         React web editor, mounted in a Filament page
  .claude/skills/              remotion-* (from video-editing-), remotion-motion-graphics (MIT), video-use (MIT)
  docs/STUDIO_SPEC.md, docs/STUDIO_PLAN.md, docs/STUDIO_PROGRESS.md
```

**Render worker (pull model):**
- Laravel never runs ffmpeg, Chromium or Whisper. The production host may not have them.
- The worker runs anywhere with outbound HTTPS. The default is Nonso's 12 GB Linux home server; AWS t3 is the alternative.
- It polls Laravel for jobs, downloads inputs through signed URLs into a local cache keyed by asset id + sha256, does the work, and uploads only what the app needs.
- It needs no open ports, so it works behind home NAT.
- Its token is a Sanctum token with ability `studio:worker`, owned by a service user created by `php artisan studio:worker-token`.
- Defaults: `WORKER_CONCURRENCY=1`, and Remotion `concurrency` at about 50% of cores.
- During renders, serve inputs to Remotion from local disk (bundle `publicDir` or a localhost static server). Never stream from production mid-render.

**Remotion versions:** every `remotion` / `@remotion/*` package must be the exact same version in `motion/`, `render-worker/` and the root `package.json` (web editor). Add `npm run check:remotion` to enforce this. Get APIs from the `/remotion-docs` skill, not memory. That includes `calculateMetadata`, zod schemas on `<Composition>`, `@remotion/captions`, `@remotion/transitions`, `@remotion/media-utils`, `@remotion/player` and `renderMedia`.

**Storage:**
- Studio files go on the private `local` disk under `studio/{user_id}/...`, served by `URL::temporarySignedRoute`.
- Only when a render is cross-posted is it copied to the `public` disk (the existing flow needs a public HTTPS URL).
- Retention via `studio:prune` (daily, configurable):
  - Preview renders: 14 days.
  - Reference media: deleted right after analysis.
  - Source preview proxies: 30 days after the project was last opened.

### 4. Data model (migrations)

- **`studio_projects`**: id, user_id, title, aspect (`9:16|1:1|16:9`), fps (30), edl json, revision uint, reference_asset_id null, style_profile json null, last_render_id null, timestamps.
- **`studio_assets`**:
  - Identity: id, user_id, project_id null, role (`source|reference|music|image|logo`), origin (`upload|link`), original_url null, original_name.
  - Storage: disk, path, mime, size_bytes, sha256.
  - Media facts: duration_seconds, width, height, fps, has_audio, probe json.
  - State: status (`pending|fetching|processing|ready|failed`), error.
  - Derived files: preview_proxy_path, poster_path, waveform_path, thumbs_path, transcript_path, transcript_language.
  - Other: style_profile json null, timestamps.
- **`studio_jobs`**:
  - Identity: id (uuid), user_id, project_id null, asset_id null.
  - type: `probe|fetch_link|transcribe|analyze_reference|auto_cut|render_preview|render_final|render_template_preview|explainer`.
  - status: `queued|running|done|failed|canceled`.
  - priority, input json, result json, progress (0–1), message.
  - attempts, max_attempts (3), worker_id, lease_expires_at, started_at, finished_at, error, timestamps.
  - Index on (status, priority, created_at).
- **`studio_renders`**: id, project_id, user_id, job_id, quality (`preview|final`), edl_revision, path, poster_path, duration_seconds, size_bytes, width, height, post_id null, created_at.
- **`studio_templates`**: slug + version (unique), name, categories json, kind (`segment|overlay|captions|transition`), aspects json, default_duration, min_duration, max_duration, props_schema json, default_props json, preview_path, poster_path, credit json null, is_active, sort, uses_count, timestamps.
- **`studio_ai_calls`**: id, user_id, purpose, tokens_in, tokens_out, created_at. Used for quotas.

**Plan limits** go in `config/clippipeline.php` plans: `studio_render_minutes`, `studio_ai_calls`, `studio_max_source_minutes`. These are placeholders until Nonso confirms: free 10 / pro 120 / agency 600 render minutes. Enforce the limits like `PostUploadService::assertWithinPlan`. Uploads allow mp4/mov/webm up to 500 MB, jpg/png/webp images, and mp3/m4a/wav music.

### 5. API (CONTRACT §17, `auth:sanctum`, new `// --- agent:studio ---` block)

**User endpoints:**
- `GET|POST /studio/projects` and `GET|PATCH|DELETE /studio/projects/{id}`
  - PATCH sends `{edl, revision}`. A stale revision returns 409.
- `POST /studio/projects/{id}/assets`: multipart `file` or JSON `{url}`, plus `role`. Queues `probe`, or `fetch_link` then `probe`.
- `GET /studio/assets/{id}`, `GET /studio/assets/{id}/transcript`, `DELETE /studio/assets/{id}`
- `POST /studio/projects/{id}/auto-cut`: `{remove_silences, silence_min_seconds=0.6, silence_db=-35, remove_fillers, filler_words[], max_segment_seconds|null, use_reference_style, target_seconds|null}` returns a job.
- `POST /studio/projects/{id}/apply-reference`: server-side and instant. Maps style_profile onto the EDL (§9).
- `POST /studio/projects/{id}/render`: `{quality: preview|final}` returns a job.
- `GET /studio/jobs/{id}`
- `GET /studio/projects/{id}/renders`: includes signed download URLs.
- `POST /studio/renders/{id}/cross-post`: same fields as `POST /posts` minus `video`. Calls a new `PostUploadService::createFromStoredFile()`; extract shared logic and leave `create()` and `POST /posts` unchanged.
- `GET /studio/templates?category=&kind=&aspect=&sort=popular|new` and `GET /studio/templates/{slug}`
- `POST /studio/templates/{slug}/customize`: `{purpose, aspect, colors[], text?}` returns `{props}`. Behind `STUDIO_AI_ENABLED`.
- `GET /studio/usage`

**Worker endpoints** (ability `studio:worker`):
- `POST /worker/claim`: `{worker_id, capabilities[]}` returns a job with signed input URLs, or 204.
  - Claim atomically and set a 10-minute lease.
  - The scheduler's `studio:requeue-stale` requeues expired leases.
- `POST /worker/jobs/{id}/heartbeat`: `{progress, message}`, extends the lease.
- `POST /worker/jobs/{id}/complete`: multipart `files[]` plus `result` JSON. Idempotent.
- `POST /worker/jobs/{id}/fail`: `{error, retryable}`

**Push:** on render done or failed, send through `ExpoPushService` with type `studio`. A tap opens `/studio/[id]`.

### 6. Worker jobs

- **`probe`**:
  - ffprobe facts.
  - A 540p H.264 CFR 30 fps **preview proxy**, uploaded so browsers can play iPhone HEVC/MOV.
  - Poster, a thumbnail sprite, waveform peaks JSON, and loudness/silence data (vkit `silence_and_loudness`).
  - Then queue `transcribe` for `source` assets that have audio.
- **`fetch_link`**: HEAD check, size limit, download, sha256. Follow the platform-link rule in §2.
- **`transcribe`**:
  - faster-whisper with word timestamps. Default model `small`, `STUDIO_WHISPER_MODEL` to change it. Language auto.
  - Use ElevenLabs Scribe when `ELEVENLABS_API_KEY` is set (vkit already supports this). It handles Nigerian accents and Pidgin better.
  - Cache by sha256.
- **`analyze_reference`**: produces style_profile (§9) and a contact sheet, then deletes the reference media.
- **`auto_cut`**: writes new main-track segments into the EDL using the rules in §10, bumps revision, and returns a cut list.
- **`render_preview`**: 540p, fast settings. **`render_final`**: 1080 on the short side, CRF ~18.
  - Both bundle `motion/` once and cache the bundle per git sha.
  - Both use `selectComposition` + `renderMedia` on `EditTimeline` with the EDL and local asset paths.
  - Then a loudnorm pass to -14 LUFS / -1 dBTP, `vkit check`, and upload the MP4 + poster.
  - Validate template props with each template's zod schema first. Invalid props fail the job with a clear message.
- **`render_template_preview`**:
  - A 4 s muted loop, 480p, ≤ 400 KB, plus a poster.
  - One per template per aspect, for the gallery. Mobile data is expensive for Nigerian users, so keep previews small.

Port vkit into `render-worker/tools/vkit.py`. Remove the `brief/staff.csv` coupling (names come from job input), and keep the MIT credit for the HDR tone-map chain adapted from video-use. Call its functions from Node as subprocesses where they fit (conform, transcribe, check, frames), or port them to TypeScript when that is simpler.

### 7. Project EDL v2 (the single source of truth for an edit)

```json
{
  "version": 2,
  "aspect": "9:16",
  "fps": 30,
  "theme": { "accent": "#7C5CFF", "accent2": "#B14CFF", "bg": "#0E0E12", "text": "#FFFFFF", "font": "Inter" },
  "fit": "blur",
  "main": [
    { "id": "s1", "type": "template", "template": "kinetic-title", "version": 1, "duration": 2.5, "props": {} },
    { "id": "s2", "type": "clip", "asset_id": 12, "in": 3.20, "out": 9.85, "volume_db": 0, "speed": 1, "zoom": 1.0,
      "transition_in": { "template": "swipe", "duration": 0.4 } }
  ],
  "overlays": [
    { "id": "o1", "template": "lower-third", "version": 1, "anchor": "s2", "offset": 0.5, "duration": 3, "props": {} }
  ],
  "captions": { "enabled": true, "template": "captions-pop", "version": 1, "props": {}, "fixes": { "wrong": "right" } },
  "music": [ { "asset_id": 20, "start": 0, "end": null, "volume": 0.25, "duck": true, "fade_in": 0.5, "fade_out": 1.0 } ],
  "loudness": -14
}
```

- `EditTimeline` computes its duration with `calculateMetadata` and uses `TransitionSeries` for `transition_in`.
- Clips use `OffthreadVideo`.
- Overlays are positioned relative to their anchor segment, so reordering keeps them attached.
- Captions are built from the transcripts of the kept word ranges and rendered **last**, above everything else.
- Music ducks under speech ranges.
- Write a converter from vkit EDL v1 for Nonso's old projects.

### 8. Templates

**Each template** lives in `motion/src/templates/<slug>/`:
- `meta.ts`: `{slug, version, name, categories, kind, aspects, defaultDuration, minDuration, maxDuration, credit}`
- `schema.ts`: a zod props schema
- `Template.tsx`

**Props schema subset.** Clients render forms from it, so keep to these types:
- string (with max length)
- number (with min/max) → slider
- boolean
- enum → select
- color (string, `format: "color"`)
- `asset:image` / `asset:video` → picker
- string[] (with max items)

Export JSON Schema into `motion/dist/templates.json`. All colours default to the project `theme`. No user-facing text is hard-coded. Every template must look right in 9:16, 1:1 and 16:9.

**Categories**, as on prompt-motion: Kinetic type, Product UI, Phone, Charts, Diagrams, Shapes, Particles, Characters, Photos, Music, Code.

**Starter set** (Phase 2 builds the first four, Phase 3 the rest):

| slug | kind | category |
|---|---|---|
| `kinetic-title` | segment | Kinetic type |
| `captions-pop` (word-by-word highlight) | captions | Kinetic type |
| `lower-third` | overlay | Shapes |
| `cta-outro` (logo, handle, follow) | segment | Shapes |
| `captions-clean` (2-line subtitles) | captions | Kinetic type |
| `stat-counter` | segment/overlay | Charts |
| `bar-chart` (≤ 6 bars) | segment | Charts |
| `flow-diagram` (3–5 nodes) | segment | Diagrams |
| `phone-mockup` (clip or screenshot in a phone) | segment | Phone, Product UI |
| `ui-cursor-zoom` (screenshot, cursor, click, zoom) | segment | Product UI |
| `photo-stack` (Ken Burns sequence) | segment | Photos |
| `audio-bars` (visualizer from music) | overlay | Music |
| `code-typing` | segment | Code |
| `particle-burst` | transition/overlay | Particles |
| `swipe`, `zoom-blur`, `flash` | transition | Shapes |

**Pipeline:**
1. `npm run templates:build` in `motion/` validates every template and writes `dist/templates.json`.
2. `php artisan studio:templates-sync` upserts `studio_templates` and queues `render_template_preview` for new versions.
3. Old versions stay renderable for existing projects.

**Gallery (web and mobile):**
- Category chips with counts, Popular/New sort, and a kind filter.
- A grid of looping previews.
- A "Pause previews" toggle. On mobile, previews show posters until tapped unless on Wi-Fi.
- The detail view has a props form and "Add to project" (as segment, overlay, captions or transition).

**Customize:**
- The default is deterministic: the project theme pickers recolour every template.
- With `STUDIO_AI_ENABLED=true` and `ANTHROPIC_API_KEY`, the backend sends the template's JSON Schema, defaults and the user's `{purpose, aspect, colors, text}` to Claude (model from `STUDIO_AI_MODEL`, a cheap model by default).
- Claude returns **props JSON only** through structured output. The backend validates it and counts it against the plan quota.
- It never returns code.

### 9. Reference video

`analyze_reference` produces:

```json
{
  "duration": 32.4, "aspect": "9:16", "shot_count": 18, "avg_shot_seconds": 1.8, "cut_times": [0.0, 1.6],
  "pace": "fast", "loudness_lufs": -13.2, "has_speech": true, "has_music": true,
  "palette": ["#111111", "#F5D90A", "#FFFFFF"],
  "contact_sheet_path": "...",
  "ai_notes": { "caption_style": "word-by-word, centred, bold, yellow active word", "text_overlays": "...", "transitions": "hard cuts, punch-in zooms" },
  "suggested": { "captions_template": "captions-pop", "captions_props": {}, "max_segment_seconds": 2.0, "accent": "#F5D90A", "punch_in": true }
}
```

- Cuts come from ffmpeg scene detection (`select='gt(scene,0.3)'`, threshold configurable).
- The palette comes from sampled frames.
- Speech, music and loudness data come from vkit.
- `ai_notes` is filled only when the AI flag is on: send the contact sheet to Claude vision and get back JSON matching our template catalogue.

`apply-reference` maps `suggested` onto the EDL: captions template and props, theme accent, `max_segment_seconds` for the next auto-cut, and alternating 1.00/1.08 punch-in `zoom` on long talking segments when `punch_in` is set. It never copies reference media.

### 10. Cutting rules (auto_cut)

1. Start from transcript words plus silence ranges.
2. Drop silences ≥ `silence_min_seconds`.
3. Drop filler words when `remove_fillers` is on. The default list is `um, uh, er, ehm, hmm`, and it is editable per request.
4. Keep cut padding at 0.05–0.15 s before the first kept word and 0.2–0.35 s after the last.
5. Never cut mid-word. Keep laughs and reactions after a punchline (non-word audio above the silence threshold right after a word).
6. If `max_segment_seconds` is set, split at sentence or phrase boundaries and alternate `zoom`.
7. If `target_seconds` is set and the AI flag is on, ask Claude to pick the strongest ranges from the transcript (JSON ranges only).
8. Return a human-readable cut list and keep the previous EDL so one "Undo auto-cut" can restore it.

### 11. Web editor (Phase 4)

**Mounting:** a Filament page "Studio" that mounts a React app from `resources/js/studio/main.tsx`.
- Add `@vitejs/plugin-react`, the Vite input and an `@motion` alias to `motion/src`.
- API auth: same-domain Sanctum SPA auth (`statefulApi()`, XSRF cookie). Verify against current Laravel and Sanctum docs.

**Panel access:** `/admin` is admin-only today. Ask Nonso in Phase 0 whether to open the panel to all users (resources are already owner-scoped) or serve Studio at its own authenticated route.

**Layout:**
- **Left:** media bin (Sources, Reference, Music, Images/Logo), with upload and add-by-link. A Templates tab holds the gallery.
- **Centre:** `@remotion/player` preview of `EditTimeline`, using preview proxies.
- **Right:** inspector. Clip in/out, volume, speed and zoom; the template props form; transitions.
- **Bottom:** timeline. Main track with thumbnails and waveform, an overlays track, a captions track and a music track.
  - Drag to reorder, trim handles, split at playhead (`S`), delete (`Del`).
  - Undo/redo with Ctrl/Cmd+Z and Shift+Ctrl/Cmd+Z, using zustand history.
  - Autosave PATCH, debounced 2 s, with revision. Show a 409 conflict banner.
- **Transcript panel:** click a word to seek. Select words and delete to create cuts.

**Actions:** Auto-cut (options modal), Apply reference style, Render preview, Render final, Download, and Send to cross-post (reuse the platform/options UI rules from the Posts resource; disabled with a reason when no account is connected).

### 12. Mobile (Phase 5)

**Routes:**
- `src/app/studio/index.tsx`: projects list.
- `src/app/studio/new.tsx`: wizard. Steps: pick clips (multi), add reference (file or link), choose aspect, choose toggles (remove silences, remove fillers, captions style, match reference), then choose intro/outro/lower-third from the gallery with simple prop forms.
- `src/app/studio/[id].tsx`: project screen. Clip list with reorder and a two-handle trim over the thumbnail sprite, template list, Render preview / Render final, job progress (poll 3 s plus push).
- `src/app/studio/templates.tsx`: gallery.
- `src/app/studio/render/[id].tsx`: playback, save to device, share, and "Cross-post", which reuses `post/new.tsx` prefilled with the render instead of a file.

**Navigation:** propose a centre "Studio" tab in the plan. For example, move Connect into Profile as "Connected channels". Nonso approves the navigation change in Phase 0.

**Modules:** check against Expo SDK 57 docs before using `expo-video`, `expo-media-library`, `expo-sharing`, `expo-document-picker` and `expo-network`. Add hooks in `src/api/hooks.ts` and all calls through `src/api/client.ts`. Uploads use the existing XHR progress pattern.

**Guest mode:** the template gallery works with demo data. Creating a project prompts sign-in.

**Builds:** native modules need an EAS/dev build. Bump `android.versionCode`.

### 13. Phases (one per session; STOP at the end of each)

**Phase 0: recon, merge skills, plan (no app code)**
- Create `feat/studio` in both repos. Confirm the mobile base branch is the newest one with `git log`.
- Copy skills:
  - `video-editing-/.claude/skills/remotion-*` → `clippipeline/.claude/skills/`, and merge `skills-lock.json`.
  - `_refs/claude-remotion-skill/remotion-motion-graphics` → `clippipeline/.claude/skills/remotion-motion-graphics/`, with its LICENSE.
  - `video-editing-/vendor/video-use` → `clippipeline/.claude/skills/video-use/`, with its LICENSE. Not `vendor/`: that is Composer's folder.
  - Add `THIRD_PARTY_NOTICES.md`.
- Copy `vkit.py` into `render-worker/tools/` (decoupling happens in Phase 2).
- Find the facts:
  - Where production really runs (Hostinger shared or AWS/VPS), PHP upload limits there, and how `public/build` reaches production (it is gitignored).
  - Whether Node or ffmpeg exist on the host.
- Write `docs/STUDIO_PLAN.md`: file-by-file plan, migrations, the CONTRACT §17 draft and the navigation proposal.
- Ask at most 8 open questions, each with a recommended default. Include at least: worker host, panel access, plan limits, AI on/off, the link-fetcher default, and the Studio tab.
- Write `docs/STUDIO_PROGRESS.md` and commit.
- **STOP:** show a short plan summary and the questions.

**Phase 1: backend foundation**
- Migrations, models and policies (owner scoping, 404 for others).
- Asset upload and link endpoints, the projects CRUD with revision, the job queue and worker API (claim, lease, heartbeat, complete, fail, requeue-stale), signed downloads and plan limits.
- `studio:worker-token`, `studio:prune`, and an `app:health` additions section (worker last seen, queued/failed Studio jobs).
- CONTRACT §17 in both repos, plus feature tests.
- **STOP:** list endpoints and test results.

**Phase 2: motion package, render worker, first 4 templates, end-to-end**
- `motion/` with theme, `EditTimeline`, the EDL v1→v2 converter, and `kinetic-title`, `captions-pop`, `lower-third`, `cta-outro`.
- `render-worker/` with every §6 job except `explainer`, plus the Dockerfile and systemd unit.
- `php artisan studio:smoke {email} {video}` creates a project, uploads, auto-cuts, renders a preview, waits, and prints the URL.
- Run it locally against `php artisan serve` with a real clip. Then run `vkit check` and look at the frames, with the 3-pass fix loop.
- **STOP:** report loudness numbers, runtime, render time, and anything not fixed.

**Phase 3: template library**
- The remaining templates, the registry build, `studio:templates-sync`, preview renders, gallery endpoints, a Filament gallery page and Customize (deterministic plus the AI flag).
- Check every template in 3 aspects.
- **STOP:** show the gallery list, with the template previews.

**Phase 4: web editor (§11)**
- Include Apply reference, the transcript editing, and Send to cross-post.
- **STOP:** give Nonso a click-test checklist (`docs/STUDIO_CLICK_TEST.md`).

**Phase 5: mobile Studio (§12)**
- Includes guest demo data and the CONTRACT/types sync.
- Run `tsc`, `expo lint`, and the web run (`npx expo start --web`).
- **STOP:** list the screens and what needs a device build to verify.

**Phase 6 (optional, only if Nonso asks): Explainer generator**
- Topic or script in, short narrated video out, using **our own** implementation:
  1. Claude writes a ≤ 60 s script and a scene plan JSON that may only reference our templates.
  2. TTS on the worker with Kokoro-82M (Apache-2.0), or the user uploads their own voiceover.
  3. faster-whisper word alignment.
  4. Build the EDL with template segments timed to the voice, plus captions.
  5. Render.
- Nothing from anything2explainer.

**Phase 7: hardening and deploy docs**
- Retention, quotas and the usage UI.
- Filament admin for Studio jobs (retry/cancel).
- `DEPLOY.md` sections: render worker install on Linux (Docker and systemd), env vars, Remotion licence note, and the cron entries for `studio:requeue-stale`, `studio:prune` and `studio:templates-sync`.
- Rate limits on uploads, renders and AI. Final full test run.
- **STOP:** release notes and the merge checklist.

### 14. Env vars to add (`.env.example`, never real values)

```
STUDIO_ENABLED=true
STUDIO_LINK_FETCHER=off            # off | ytdlp
STUDIO_AI_ENABLED=false
ANTHROPIC_API_KEY=
STUDIO_AI_MODEL=
STUDIO_WHISPER_MODEL=small
ELEVENLABS_API_KEY=
STUDIO_MAX_UPLOAD_MB=500
STUDIO_MAX_SOURCE_MINUTES=10
# render-worker/.env
CLIPPIPELINE_API_URL=https://clippipeline.nonsostudio.com/api
CLIPPIPELINE_WORKER_TOKEN=
WORKER_ID=home-server-1
WORKER_CONCURRENCY=1
WORKER_CACHE_DIR=/var/cache/clippipeline-render
```
