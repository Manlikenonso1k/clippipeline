# feat/parity — progress (paused 2026-10-14)

State: all gates green at pause — php artisan test 186/186, pint clean, mobile tsc + expo lint 0 errors.
Branch `feat/parity` pushed in both repos. NOT merged into main / feat/app-screens-auth-charts yet. No EAS build yet.

## Done
- Phase 0: page map + task list (docs/TASKS.md).
- Wave 1: page audits (docs/audit/*), compliance research (docs/compliance/{tiktok,meta,youtube-google}.md).
- Merge: docs/PARITY.md, docs/COMPLIANCE.md, docs/compliance/NONSO_CHECKLIST.md, CONTRACT.md Part 2 (§9–16), shared stubs.
- Wave 2: backend (sign-in broker = mobile Google/FB/TikTok login fix, me/preferences/delete account, notifications, post delete/filters,
  TikTok creator-info + compliant options, export, Meta/TikTok compliance callbacks, public policy pages, retention job);
  web Filament (Home, Posts, Connect, Insights, Profile, Notifications); mobile (every control wired, guest sign-in sheet, real profile,
  TikTok-compliant post form). Gate 2 passed.
- Wave 3: USER-SIM journeys (tests/Feature/UserJourneyTest.php, docs/USER_SIM.md) — works end to end, not yet reviewer-ready.
- QA (stopped mid-way, partial fixes saved in the WIP commit): BUG-1 sign-in with existing email, export math, last_synced_at,
  plan clip-limit enforcement, Failed filter includes partial, log redaction of tokens, contract updates.

## To do when resuming
1. Resume QA: finish USER-SIM item 5 (video preview before publish + Publish disabled when disclosure on with nothing ticked, mobile + web),
   Cross-Post Boost (A-07), period labels on Home/Profile; verify the partial QA edits; remove remaining markTestIncomplete journeys.
2. QA deliverables still missing: PARITY.md "QA" column, docs/COMPLIANCE_QA.md (ID | status | file:line), docs/CLICK_TEST.md, UI-freeze diff check.
3. Gate, merge feat/parity → main (backend) and → feat/app-screens-auth-charts (mobile), push.
4. Bump app.json versionCode to 11; `npx eas-cli login` first (this PC is logged out), then the preview build. New native modules (expo-sharing, expo-file-system) need it.
5. Update HANDOFF.md and write the final report.

## Owner decisions pending
- `/admin` limited to is_admin users (User::canAccessPanel) — regular users get 403 until opened or promoted with app:make-admin.
- TiktokInsights Filament page still shows placeholder data (now last in nav).
