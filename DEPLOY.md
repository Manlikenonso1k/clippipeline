# ClipPipeline — Deployment

## PHP version
Production runs **PHP 8.3.30**. `composer.json` pins `config.platform.php` to
`8.3.30` so the lock resolves to packages that run on 8.3 (Symfony 7.x). Do **not**
run `composer update` on a machine with a newer PHP without that pin, or the lock
will drift to Symfony 8.x (needs PHP ≥ 8.4.1) and `composer install` will fail on
the server.

## Deploy steps (run on the server, in order)
```bash
cd /var/www/clippipeline
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link           # once; makes storage/app/public web-reachable
php artisan optimize:clear
php artisan optimize                # cache config/routes/views
sudo supervisorctl restart clippipeline-worker:*
sudo systemctl reload php8.3-fpm
```

## First-time setup
```bash
# Promote your admin so /admin stops returning 403:
php artisan app:make-admin you@example.com

# Queue worker (Supervisor):
sudo cp deploy/supervisor-clippipeline-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start clippipeline-worker:*

# Scheduler (cron) — see deploy/crontab.txt:
crontab -e
# add:  * * * * * php /var/www/clippipeline/artisan schedule:run >> /dev/null 2>&1
```

## Permissions
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```
The web user must be able to write `storage/` (logs, framework cache, public
uploads) and `bootstrap/cache/` (compiled config/routes), or the panel 500s.

## Large video uploads (up to 500MB)
Livewire rules already allow mp4/mov up to 500MB (`config/livewire.php`). The
server must allow it too.

`php.ini` (php8.3-fpm):
```ini
upload_max_filesize = 512M
post_max_size = 520M
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
```
Then `sudo systemctl reload php8.3-fpm`.

Nginx server block:
```nginx
client_max_body_size 520M;
```
Then `sudo nginx -t && sudo systemctl reload nginx`.

`APP_URL` must be the HTTPS domain so uploaded videos get absolute HTTPS URLs
(Instagram requires a publicly reachable HTTPS video URL).

## Health check
```bash
php artisan app:health      # worker heartbeat, pending/failed jobs, connected accounts + token expiry
```

## OAuth redirect URIs — register these in the developer consoles
- **Google** (console.cloud.google.com → Credentials → your OAuth client → Authorized redirect URIs):
  - `https://clippipeline.nonsostudio.com/auth/callback/google`  (login)
  - `https://clippipeline.nonsostudio.com/integrations/callback/youtube`  (YouTube connect)
- **Meta** (developers.facebook.com → your app → Facebook Login → Valid OAuth Redirect URIs):
  - `https://clippipeline.nonsostudio.com/integrations/callback/instagram`
- **TikTok** (developers.tiktok.com → your app → Login Kit redirect URI):
  - `https://clippipeline.nonsostudio.com/integrations/callback/tiktok`

## Required env values (set the ones that are blank)
```
APP_URL=https://clippipeline.nonsostudio.com
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=600
GOOGLE_CLIENT_ID=...        GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT=https://clippipeline.nonsostudio.com/auth/callback/google
FACEBOOK_CLIENT_ID=...      FACEBOOK_CLIENT_SECRET=...
FACEBOOK_REDIRECT=https://clippipeline.nonsostudio.com/integrations/callback/instagram
TIKTOK_CLIENT_ID=...        TIKTOK_CLIENT_SECRET=...
TIKTOK_REDIRECT=https://clippipeline.nonsostudio.com/integrations/callback/tiktok
# Optional (TikTok auto-import). Leave blank to disable the poll cleanly:
TIKTOK_SCRAPER_URL=         TIKTOK_SCRAPER_KEY=
```

## Platform publishing status
- **YouTube Shorts** — implemented (token auto-refreshes before upload).
- **Instagram Reels** — implemented; needs an IG **Business/Creator** account linked
  to a Facebook Page. If none is found on connect, the account is flagged
  "needs attention"; run `php artisan app:backfill-ig-ids` after linking, or use
  **Reconnect**.
- **Facebook Reels** — shown disabled: needs a connected Page + App Review for the
  Reels API.
- **TikTok direct posting** — shown disabled: needs an audited Content Posting API
  app with the `video.publish` scope.

## YouTube API Compliance Audit note
Until Google completes the **YouTube API Compliance Audit** for your Cloud project,
uploads via the API are locked to **private** regardless of the privacy you choose.
The app records this in the distribution `meta` and shows a notice. Request the
audit in the Google Cloud console to enable public uploads.
