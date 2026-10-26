# Compliance QA (MUST items from docs/COMPLIANCE.md)

Backend + web verified from code on branch `feat/parity`. Status: done / partial / gap. Mobile UI items point at the mobile repo (`docs/QA_MOBILE.md`).
Tests: `php artisan test` (ComplianceTest, CreatorInfoAndExportTest, Filament*Test, UserJourneyTest cover most rows).

| ID | Status | Evidence (file:line) |
|---|---|---|
| C-01 | done | resources/views/legal/privacy.blade.php:39 (Limited Use), :41 (YouTube/Google links), :67 (retention), :78-80 (revoke links), :93 (non-affiliation); route routes/web.php:14 |
| C-02 | done | resources/views/legal/terms.blade.php:30 (YouTube ToS), :35 (TikTok Music/Branded), :56 (non-affiliation); routes/web.php:13 |
| C-03 | done | resources/views/legal/data-deletion.blade.php; routes/web.php:64 |
| C-04 | done | resources/views/legal/delete-account.blade.php:14-22 (works logged out, sign-in-to-delete, support); routes/web.php:66-67 |
| C-05 | done | resources/views/partials/public-footer.blade.php:6-12; panel footer resources/views/filament/partials/panel-footer.blade.php:7-15 |
| C-06 | done | routes/api.php:32; app/Http/Controllers/Api/ComplianceController.php; app/Services/Compliance/MetaSignedRequest.php:32-34 (hash_equals); MetaComplianceService.php:22; status page routes/web.php:65 |
| C-07 | done | routes/api.php:33; ComplianceController.php:39; MetaComplianceService.php:31 |
| C-08 | done | routes/api.php:34; app/Services/Compliance/TikTokWebhookService.php:14 (signature), :57-58 (authorization.removed, post.publish.*) |
| A-01 | done | app/Services/Connect/RevokeService.php:17-19,46,55,80; DisconnectService.php:39 |
| A-02 | done | app/Services/Account/AccountDeletionService.php:28-73 (+ app/Jobs/PurgeUserDataJob.php); audit row :42 without PII; web Profile.php:156-182 |
| A-03 | done | app/Services/Connect/TokenManager.php:71,134; AnalyticsSync.php:103,173; AccountsRetention.php:63-70 (30-day purge) |
| A-04 | done | app/Console/Commands/AccountsRetention.php:38,75 (last_authorized_check_at, 36-month prune); scheduled routes/console.php:38 |
| A-05 | done | app/Console/Commands/RefreshAccountTokens.php:12 (hourly), TokenManager.php:28-64; routes/console.php:27 |
| A-06 | done | app/Logging/RedactSecrets.php:14 (tests/Feature/LogRedactionTest.php); app/Models/SocialAccount.php:52-53 (encrypted) |
| A-07 | done | Web shows sums/averages only (app/Services/AnalyticsService.php); mobile Boost excludes YouTube, insights.tsx:231 (mobile repo) |
| P-01 | done | Web confirm step: app/Filament/Resources/Posts/Schemas/PostForm.php Review step (createSteps) + summary(); mobile confirm sheet in mobile repo |
| P-02 | done (web) | app/Services/Publishing/PublishRules.php:84-88 (YT description 5000 B, strips <>), :91 (FB 3-90 s), :101 (IG 3 s-15 min), :152-158 (TikTok max duration, 4 GB); title <=100 in PostForm detailsFields. Web (2026-10): YouTube Shorts <=180 s and IG <=300 MB warnings in the browser (resources/views/filament/forms/video-checks.blade.php) plus limits text in the platform list (PostForm platformsField); IG 300 MB also rejected server-side (PublishRules instagram case). YouTube >180 s stays warn-only by design. Test: test_create_form_shows_platform_limits_logos_and_browser_checks |
| P-03 | done | PublishRules.php:94-95 (FB 30/day), :107-108,186 (IG content_publishing_limit); YouTube quota mapped in publish job errors |
| P-04 | done | PostForm.php youtubeSection (ToS link, guidelines checkbox, private forced while YOUTUBE_AUDITED=false); PublishRules.php:50-51,80 |
| P-05 | done | app/Filament/Resources/Posts/Tables/PostsTable.php:84,135-150 (cancel scheduled); API DELETE /api/posts/{post} |
| P-06 | done | PostForm.php PROCESSING_NOTICE (:49) shown in Review; status badges in PostsTable.php; DistributionsRelationManager |
| T-01 | done | app/Services/TikTok/CreatorInfoService.php; web refetch on every TikTok pick (PostForm platformsField afterStateUpdated); avatar + "Posting to @name" in tiktokSection; routes/api.php:71 |
| T-02 | done | PostForm.php tiktokSection tt_privacy_level (options only from creator_info, no default, required); FilamentPostsTest::test_tiktok_form_loads_creator_info_each_time_and_has_no_privacy_default |
| T-03 | done | PostForm.php tt_allow_* toggles (default false, disabled when creator disabled); PublishRules.php:162-165 |
| T-04 | done | PostForm.php max-duration text and can_post callout/rule; browser-side max-duration warning (video-checks.blade.php, reads $wire.tiktokCreator.max_video_post_duration_sec); PublishRules.php:143-154 (server rejects over-max); test_tiktok_blocked_when_creator_cannot_post |
| T-05 | done | PostForm.php tt_disclose / tt_brand_organic / tt_brand_content (one required; Branded disables Only me) |
| T-06 | done | PostForm.php BRAND_ORGANIC_LABEL / BRAND_CONTENT_LABEL helper text |
| T-07 | done | PostForm.php reviewFields consent line (+ Branded Content Policy when branded); PublishRules.php:58 (music_consent accepted) |
| T-08 | done | PostForm.php TIKTOK_PRIVATE_NOTICE; CreatorInfoService.php:94-96 (only SELF_ONLY while unaudited) |
| T-09 | done | PublishRules.php:129-158 (privacy not in options, branded+SELF_ONLY, duration, consent :58) |
| U-01 | done | Web: panel-footer.blade.php:7-8, profile.blade.php:40-41. Mobile: QA_MOBILE.md |
| U-02 | done | Disconnect modal ConnectAccounts.php:109-125; DisconnectService.php:39 |
| U-03 | done | Profile.php:46 (DELETE_EXPLANATION), :156-182 (type DELETE), profile.blade.php:43 (/delete-account link) |
| U-04 | done | connect-accounts.blade.php:31-34 (rationale per provider, YouTube ToS note) |
| U-05 | done (web; assets to swap) | Insights.php:82-98 "Last updated"; Connect "Last synced" connect-accounts.blade.php:63. Web (2026-10): brand marks next to platform names on the post form (PostForm::platformOptionsWithLogos, public/images/brands/). YouTube/Instagram/Facebook SVGs are simplified stand-ins: replace with official unmodified assets before review. TikTok shows text only until the official Logo and Button pack file is dropped in as public/images/brands/tiktok.svg (TikTok brand rules). Logos on Insights/Connect not added |
| U-06 | done | ConnectAccounts.php getProviderCards (needs_reauth state) |
| U-07 | done | panel-footer.blade.php:15, profile.blade.php:48 (SHOULD) |
| U-08 | done | No "SOC2" in app or views (FilamentProfileTest asserts) |
