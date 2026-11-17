# Studio progress

Spec: `docs/STUDIO_SPEC.md`. Branch `feat/studio` in both repos.

## Session 1 (2026-11-17)

Nonso asked for the whole build plus an installable APK in one go, so this session ran Phases 0–3 and 5
together instead of one phase per session. The open questions from Phase 0 were answered with the
recommended defaults below; change any of them and tell Claude.

### Done

- **Phase 0:** skills copied to `.claude/skills/` (remotion-* from video-editing-, remotion-motion-graphics
  MIT, video-use MIT, with licences), `skills-lock.json`, `THIRD_PARTY_NOTICES.md`, vkit ported to
  `render-worker/tools/vkit.py` (staff.csv coupling removed: names come from `VKIT_NAMES_JSON`; MIT credit
  for the HDR tone-map chain).
- **Phase 1 (backend):** `app/Studio/` (models, services, controllers, middleware, commands), migration
  `2026_11_17_100000_create_studio_tables.php`, CONTRACT §17 (both repos), `// --- agent:studio ---` route
  and scheduler blocks, `PostUploadService::createFromStoredFile()` (shared `persist()`; `create()` and
  `POST /posts` unchanged), plan limits in `config/clippipeline.php`, `studio:worker-token|prune|requeue-stale|
  templates-sync|smoke`, `app:health` Studio section. 29 Studio feature tests; full suite 219/219.
- **Phase 2 (motion + worker):** `motion/` (Remotion 4.0.541, EditTimeline with TransitionSeries, blurred
  fill, punch-in zoom, overlays anchored to segments, captions rendered last, music ducked under speech,
  EDL v1→v2 converter in `Edl::fromV1`), `render-worker/` (Node 22 + tsx, pull loop, lease heartbeats,
  cancel, local CORS/Range media server, sha256 input cache, bundle cached per source hash,
  `tools/studio_tools.py` JSON CLI), Dockerfile + systemd unit + `.env.example`.
  End-to-end run against a local Laravel: 12 s clip → probe → auto-cut → preview render 10.7 s in 30 s
  (-13.9 LUFS, TP -8.6 dBTP) and a final 1080×1920 render 12.3 s in 88 s (-13.5 LUFS, TP -6.6 dBTP,
  no black frames, no long silences). Frames checked; two bugs fixed (PHP `[]` props, demo captions
  leaking into real edits).
- **Phase 3 (templates):** all 17 starter templates, `npm run templates:build` validates and writes
  `motion/dist/templates.json`, `studio:templates-sync`, gallery previews rendered by the worker in all
  three aspects, gallery + customize endpoints (deterministic; AI behind `STUDIO_AI_ENABLED`).
- **Phase 5 (mobile):** Studio tab, `studio/new` wizard, `studio/[id]` project (jobs, auto-cut sheet,
  undo, apply reference, timeline reorder/trim/split/zoom/speed/volume, template props forms, media,
  renders), `studio/templates` gallery (category counts, Popular/New, kind filter, pause previews,
  posters until tapped on mobile data, customize, add to project), `studio/render/[id]` (play, save to
  gallery, share, cross-post via the existing post form). Guest mode uses the bundled catalogue and demo
  projects. versionCode 14. APK built by `.github/workflows/android-apk.yml` and published as a GitHub
  release.

### Decisions (defaults taken without asking — change freely)

| Question | Default taken |
|---|---|
| Worker host | Nonso's Linux home server (Docker or systemd); AWS t3 works the same |
| Web panel access | Unchanged; the web editor (Phase 4) is not built yet |
| Plan limits | free 10 / pro 120 / agency 600 render minutes; previews count 25 % |
| AI | Off (`STUDIO_AI_ENABLED=false`); model `claude-haiku-5-5` when on |
| Link fetcher | `off`: platform links get a 422 asking for an upload |
| Studio tab | Added in the centre of the tab bar; Connect stays (6 tabs) |
| Auto-cut | Runs inline on the web host (pure PHP over cached transcripts), recorded as a finished `auto_cut` job, so it works without the worker |
| Mobile signing | CI APK is signed with the debug key (no EAS token in CI): uninstall an EAS build before installing |

### Not done / next

1. **Deploy**: merge, `migrate`, `studio:worker-token`, `studio:templates-sync`, start the worker
   (DEPLOY.md, Studio). Until then the app's Studio screens show "Studio isn't switched on" for
   signed-in users; the gallery works from the bundled catalogue.
2. **Phase 4 web editor** (`resources/js/studio`, Filament page, @remotion/player) — not started.
3. **Filament admin** for Studio jobs (retry/cancel) and a Filament gallery page (Phase 3/7).
4. Transcription was not exercised here (the sandbox cannot download Whisper models); run
   `php artisan studio:smoke you@example.com clip.mp4` on the server with the worker running.
5. Device checks for the APK: picking multiple videos, upload progress, save to gallery, share,
   push tap → project.
6. Phase 6 (Explainer) only if asked.
