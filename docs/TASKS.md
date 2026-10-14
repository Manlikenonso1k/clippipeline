# feat/parity task list

| # | Task | Owner | Status |
|---|---|---|---|
| 0 | Branches, page map, docs/audit + docs/compliance | LEAD | done |
| 1 | Compliance research: TikTok / Meta / YouTube+Google -> docs/compliance/*.md | R1, R2, R3 | |
| 2 | Page audits: home, posts (+details), connect, insights, profile -> docs/audit/*.md | P1-P5 | |
| 3 | Merge -> docs/PARITY.md, docs/COMPLIANCE.md, docs/compliance/NONSO_CHECKLIST.md, CONTRACT.md, stubs | LEAD | |
| 4 | Backend endpoints + compliance backend + mobile Google login broker | BACKEND | |
| 5 | Mobile parity + compliance UI (UI freeze) | MOBILE | |
| 6 | Web panel: Home, Posts, Connect, Insights, Profile | WEB | |
| 7 | Gate 2 | LEAD | |
| 8 | QA re-walk + dead-pattern grep + compliance file:line + UI-freeze diff | QA | |
| 9 | User simulation (connected accounts, faked platforms) | USER-SIM | |
| 10 | Merge, push, versionCode, EAS build, HANDOFF, report | LEAD | |

## Page map
| Page | Mobile | Web (Filament) today |
|---|---|---|
| Home | src/app/(tabs)/dashboard.tsx | app/Filament/Pages/Dashboard.php + app/Filament/Widgets/* |
| Posts | src/app/(tabs)/posts.tsx, src/app/post/[id].tsx, src/app/post/new.tsx | app/Filament/Resources/Posts/** |
| Connect | src/app/(tabs)/connect.tsx | app/Filament/Pages/ConnectAccounts.php + resources/views/filament/pages/connect-accounts.blade.php |
| Insights | src/app/(tabs)/insights.tsx | none (TiktokInsights.php is placeholder data) |
| Profile | src/app/(tabs)/profile.tsx | none |
| Login | src/app/login.tsx | Google via /auth/redirect/google (works); mobile Google is broken (custom-scheme redirect) |
Shared mobile code: src/api/{types,hooks,client,demo}.ts, src/components/ui-kit.tsx, src/store/authStore.ts.
