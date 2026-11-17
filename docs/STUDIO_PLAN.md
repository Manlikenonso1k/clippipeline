# Studio plan (as built)

See `docs/STUDIO_SPEC.md` for the spec and `docs/STUDIO_PROGRESS.md` for status.

| Area | Files |
|---|---|
| Data | `database/migrations/2026_11_17_100000_create_studio_tables.php`, `app/Studio/Models/*` |
| API | `routes/api.php` (`agent:studio` blocks), `app/Studio/Http/Controllers/*`, CONTRACT §17 |
| Jobs | `app/Studio/Services/JobQueue.php` (claim/lease), `JobCompletion.php` (results), `WorkerPayload.php` |
| Editing | `Edl.php` (EDL v2 validation, apply reference, v1 converter), `AutoCutter.php` (§10 rules), `AutoCutRunner.php` |
| Limits | `Usage.php`, `config/clippipeline.php` (`studio` block) |
| AI | `AiClient.php` (tool-forced JSON), `TemplateCustomizer.php`, `ReferenceAi.php` |
| Commands | `app/Studio/Console/*`, schedule in `routes/console.php` |
| Motion | `motion/src/{edl,theme,fonts,Root}.ts(x)`, `timeline/EditTimeline.tsx`, `templates/<slug>/{meta,schema,Template}`, `scripts/build-registry.ts` |
| Worker | `render-worker/src/{index,api,cache,server,tools,config}.ts`, `src/jobs/*`, `tools/{studio_tools,vkit}.py` |
| Mobile | `src/app/(tabs)/studio.tsx`, `src/app/studio/*`, `src/components/studio/*`, `src/lib/studio.ts`, `src/store/studioStore.ts`, hooks/types/demo in `src/api` |

Navigation: Studio is the centre tab (Home, Posts, **Studio**, Connect, Insights, Profile).
