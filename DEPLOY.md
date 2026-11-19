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

## Studio (video editing, templates) — docs/STUDIO_SPEC.md

Studio adds Laravel endpoints (CONTRACT §17) plus a separate **render worker**. Laravel never runs
ffmpeg, Chromium or Whisper: the worker (any Linux machine with outbound HTTPS, e.g. the 12 GB home
server) pulls jobs, renders, and uploads results.

### 1. Server (Laravel)

```bash
php artisan migrate --force            # creates the studio_* tables (no data changes)
php artisan studio:worker-token        # prints the worker token once; keep it for step 2
php artisan studio:templates-sync      # registers templates from motion/dist/templates.json, queues gallery previews
```

Env (`.env`, see `.env.example`): `STUDIO_ENABLED=true`, `STUDIO_LINK_FETCHER=off`, `STUDIO_AI_ENABLED=false`,
`ANTHROPIC_API_KEY=`, `STUDIO_AI_MODEL=`, `STUDIO_MAX_UPLOAD_MB=500`, `STUDIO_MAX_SOURCE_MINUTES=10`.

**PHP upload limits apply to the worker too.** Rendered videos are uploaded back to
`/api/worker/jobs/{id}/complete`, so `upload_max_filesize` / `post_max_size` (hPanel → PHP options, see
"Large video uploads" above) must be at least `STUDIO_MAX_UPLOAD_MB`. If they are lower, the worker logs
"the server rejected the file size".

Cron: the existing `schedule:run` entry runs the new commands from `routes/console.php`:
`studio:requeue-stale` (every minute), `studio:prune` (daily 04:10), `studio:templates-sync` (daily 04:40).

### 2. Render worker (Linux)

Docker (simplest):

```bash
git clone … clippipeline && cd clippipeline
cp render-worker/.env.example render-worker/.env    # set CLIPPIPELINE_WORKER_TOKEN (keep the quotes)
docker build -f render-worker/Dockerfile -t clippipeline-render .
docker run -d --restart unless-stopped --env-file render-worker/.env \
  -v clippipeline-cache:/var/cache/clippipeline-render --name clippipeline-render clippipeline-render
docker logs -f clippipeline-render
```

systemd (no Docker): install Node 22, `ffmpeg` (with `zscale`), Python 3 and
`python3 -m venv /opt/clippipeline/venv && /opt/clippipeline/venv/bin/pip install faster-whisper`, then

```bash
cd /opt/clippipeline/motion && npm install
cd /opt/clippipeline/render-worker && npm install && cp .env.example .env   # PYTHON=/opt/clippipeline/venv/bin/python
sudo cp clippipeline-render.service /etc/systemd/system/ && sudo systemctl enable --now clippipeline-render
```

Defaults: `WORKER_CONCURRENCY=1`, Remotion uses half the CPU cores. The first transcription downloads the
Whisper model (`STUDIO_WHISPER_MODEL=small`, ~500 MB) into the cache. Set `ELEVENLABS_API_KEY` on the worker
to use ElevenLabs Scribe instead (better on Nigerian accents and Pidgin).

`php artisan app:health` now shows when the worker was last seen and the queued/failed Studio jobs.

### 3. Remotion licence

Remotion is free for individuals and companies of up to **3 people**, including a SaaS where users
render our templates with their own data. At **4+ people** a Remotion **Company License** is required
(the "Automators" plan: per-render pricing with a monthly minimum). Users never upload Remotion/JS code
and the server never generates component code; AI only fills template props.

## Email accounts (create account, forgot password) — CONTRACT §18

`php artisan migrate --force` adds `users.password_is_random` (existing Google/Facebook/TikTok accounts are flagged so
they can set a password without an old one).

**Reset emails need real mail settings** in `.env` (the default `MAIL_MAILER=log` only writes them to the log). With a
Hostinger mailbox (hPanel → Emails), for example:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=no-reply@nonsostudio.com
MAIL_PASSWORD=the-mailbox-password
MAIL_FROM_ADDRESS=no-reply@nonsostudio.com
MAIL_FROM_NAME="ClipPipeline"
```

Then `php artisan config:clear`. Test it from the app (Forgot password?) or the website (`/admin/password-reset/request`).

Set a password for someone from the server (asked hidden, never echoed or logged):
`php artisan app:set-password you@example.com` (add `--create` to make the account).

## Studio render worker on a VPS (Hostinger KVM 2)

Shared hosting cannot run the worker (ffmpeg, Whisper, headless Chrome). Run it on a VPS:

1. On the shared server: `/opt/alt/php83/usr/bin/php artisan studio:worker-token` and copy the token.
2. On the VPS as root:
   `curl -fsSL https://raw.githubusercontent.com/Manlikenonso1k/clippipeline/main/render-worker/setup-vps.sh -o setup-vps.sh && bash setup-vps.sh`
   (installs Docker, adds swap, clones to /opt/clippipeline, asks for the token, builds and starts the worker).
3. Optional CI/CD: add `WORKER_SSH_HOST` and `WORKER_SSH_PRIVATE_KEY` (and `WORKER_SSH_USER`/`WORKER_SSH_PORT`
   if not root/22) to the repo's Actions secrets; every push to main then updates the worker too.

Logs: `docker logs -f clippipeline-render`. Restart: `docker restart clippipeline-render`.
