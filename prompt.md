You are working in the ClipPipeline Laravel 12 + Filament 3 repo. Do NOT ask me questions and do NOT stop to propose a plan. Make sensible decisions, implement everything below, run the tests, commit, and push to main. If something needs a secret or env value from me, add it to .env.example, handle its absence gracefully, and list it at the end.

GOAL: I can open the admin panel, pick a video, click "Cross-post", and it publishes to my connected YouTube Shorts and Instagram Reels accounts. The result of each platform is recorded and visible.

FIX ALL OF THESE:

0. 403 on /admin in production (https://clippipeline.nonsostudio.com/admin)
   - Make App\Models\User implement Filament\Models\Contracts\FilamentUser with canAccessPanel(Panel $panel): bool. Allow users with an is_admin flag (add a migration if the column doesn't exist), and make sure the existing admin user is flagged.
   - Add an artisan command `app:make-admin {email}` that creates or promotes an admin user.
   - Verify storage/ and bootstrap/cache permissions notes are in the deploy docs.

1. post_distributions schema bug
   - PublishToYouTubeShortsJob and PublishToInstagramReelsJob write `published_at` and `meta` to post_distributions, but those columns don't exist. Add a migration adding `published_at` (nullable timestamp), `meta` (nullable json), `error` (nullable text), and `external_id` (nullable string) if missing. Update the model $fillable and $casts.
   - Make the jobs record failures with the real exception message in `error`, and set status to failed. Successful uploads must record status=published, external_id, published_at.

2. Manual "Cross-post" flow (no TikTok needed)
   - On the Posts Filament resource, add a table action and a bulk action "Cross-post" with a form: platform checkboxes (YouTube Shorts, Instagram Reels, only enabled when the current user has that account connected), title, caption/description, hashtags.
   - The video source must support: an uploaded file on the post, or a public URL. The action creates a post_distributions row per platform (status=queued) and dispatches the matching job.
   - Show distribution status, error and external link per platform in the post view (relation manager or infolist), plus a "Retry" action on failed rows.
   - Send a Filament notification on dispatch.

3. TikTok source is optional
   - TikTokScraperService requires TIKTOK_SCRAPER_URL and TIKTOK_SCRAPER_KEY, which are unset. Move these to config/services.php, add them to .env.example, and make `tiktok:poll-latest` skip cleanly with a clear log line when they're not configured. Manual cross-post (item 2) must work without them.

4. Instagram business id
   - In the Instagram connect/OAuth callback, after getting the token, call the Graph API (/me/accounts, then /{page-id}?fields=instagram_business_account) and save `ig_user_id` into the social account's meta. If none is found, mark the account as needing attention and show a clear message in the UI instead of failing silently. Add a "Reconnect" action.
   - InstagramService::publishReel must read ig_user_id from meta and throw a clear exception if it's missing. Also poll the container status until FINISHED before publishing.

5. Multi-user scoping bug
   - Replace every `social_accounts->where('provider', ...)->first()` in the publish jobs with a lookup scoped to the post owner's user_id. Add a failing test first, then fix it.

6. Queue + scheduler on production
   - Set QUEUE_CONNECTION=database in .env.example, make sure the jobs and failed_jobs migrations exist.
   - Add deploy/supervisor-clippipeline-worker.conf (queue:work --tries=3 --timeout=300) and deploy/crontab.txt (`* * * * * php /path/artisan schedule:run`).
   - Add a Filament dashboard widget or an `app:health` command showing: queue worker alive (heartbeat), pending/failed job counts, which social accounts are connected and token expiry.
   - Document the exact server commands in DEPLOY.md: pull, composer install --no-dev, migrate --force, optimize:clear, restart supervisor, reload php-fpm.

7. Token handling
   - Refresh the YouTube (Google) access token using the refresh token before upload when it is expired, and persist the new token. Log and surface expiry for Instagram long-lived tokens.

FINISH:
- Add feature tests for: canAccessPanel, cross-post dispatching the right jobs, the distribution status writes, and the user-scoped account lookup. Use Http::fake() for the YouTube and Instagram calls.
- Run `php artisan test` and `./vendor/bin/pint`. Fix anything that fails.
- Commit in logical commits and push to main.
- End with a short report: what changed, the exact commands I must run on the server, and the env values I must set (list only the ones that are missing). Also remind me to register the OAuth redirect URIs in the Google and Meta developer consoles.


ADDITIONAL REQUIREMENTS (do all of these too):

8. All connected platforms: cross-post must target EVERY provider present in social_accounts for the user, not just YouTube and Instagram. For any connected provider that has no publish service yet, implement it using the official API (e.g. Facebook Page video/reels, TikTok Content Posting API). If that's impossible, show it disabled in the form with the reason, and list it in the final report.

9. Fix the PHP-version blocker you reported earlier. Make composer.json's PHP constraint match the production PHP version, and state the version in DEPLOY.md.

10. Large video uploads: set Livewire temporary_file_upload rules to allow mp4/mov up to 500MB. Document the php.ini changes (upload_max_filesize, post_max_size, max_execution_time) and the nginx client_max_body_size change in DEPLOY.md. Store videos on the public disk, make sure storage:link exists, and generate absolute HTTPS URLs.

11. Instagram: detect which login flow the app uses (Facebook Login vs Instagram Login) and fetch the IG user id from the matching endpoint. Before publishing, verify the video URL is publicly reachable with an HTTP HEAD request, and fail with a clear error if not. Add an `app:backfill-ig-ids` command that fills ig_user_id for already-connected accounts.

12. No duplicate posts: set retry_after in config/queue.php higher than the job timeout (e.g. timeout 300, retry_after 600). Make the publish jobs idempotent: skip if the distribution already has an external_id or status=published. Use ShouldBeUnique keyed on the distribution id.

13. Real smoke test: add `app:test-crosspost {user_email} {video_path}` that posts a short test video to every connected platform (YouTube as private) and prints the result per platform. Report any platform error verbatim.

14. YouTube: default privacyStatus to the value chosen in the form. If the API returns a video locked to private, record that in meta and show a notice in the UI that the Google Cloud project needs the YouTube API Compliance Audit.

15. If the app already has a role system (e.g. spatie/permission), use it in canAccessPanel instead of adding is_admin.

16. After pushing, if there is a deploy script or CI, run it. Otherwise list the exact server commands in order in the final report.


composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear && php artisan optimize
php artisan app:make-admin you@example.com      # first time
sudo supervisorctl restart clippipeline-worker:* # after installing the supervisor conf
sudo systemctl reload php8.3-fpm
you@example.com.
Generated password: V1FLhGCPVdwbXciN