<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Project Configuration

This project implements a TikTok -> YouTube Shorts & Instagram Reels cross-posting pipeline. Configure the following environment variables in your .env:

- Database
  - DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
- Redis / Queues
  - REDIS_HOST, REDIS_PASSWORD, REDIS_PORT
  - QUEUE_CONNECTION=redis
  - CACHE_DRIVER=redis
  - SESSION_DRIVER=database (recommended) or file
- Google (YouTube)
  - GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT (e.g., https://yourdomain/auth/callback/google)
- Facebook / Instagram
  - FACEBOOK_CLIENT_ID, FACEBOOK_CLIENT_SECRET, FACEBOOK_REDIRECT (e.g., https://yourdomain/integrations/callback/instagram)
- TikTok scraper (third-party)
  - TIKTOK_SCRAPER_URL, TIKTOK_SCRAPER_KEY
- Optional (S3 for public file hosting)
  - AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, AWS_BUCKET

Installation / Setup

1) Install PHP deps:
   composer require google/apiclient guzzlehttp/guzzle laravel/socialite predis/predis

2) Install Horizon (optional):
   composer require laravel/horizon

3) Set app storage permissions and link public storage:
   php artisan storage:link

4) Run migrations:
   php artisan migrate

5) Start queue worker (or Horizon):
   php artisan queue:work redis --sleep=3 --tries=3
   # or
   php artisan horizon

6) Scheduler (on deployment server):
   Add cron (runs every minute):
     * * * * * cd /var/www/projectx/app && php artisan schedule:run >> /dev/null 2>&1

Testing / Useful Commands

- Poll TikTok manually:
  php artisan tiktok:poll-latest

- Test uploads (uses stored social_accounts):
  php artisan social:test-upload --file="C:\path\to\sample.mp4"
  php artisan social:test-upload --postId=123

Deployment notes (target root: /var/www/projectx/app)

- Supervisor example (worker):

  [program:projectx-queue]
  process_name=%(program_name)s_%(process_num)02d
  command=php /var/www/projectx/app/artisan queue:work redis --sleep=3 --tries=3 --timeout=3600
  autostart=true
  autorestart=true
  user=www-data
  numprocs=1
  redirect_stderr=true
  stdout_logfile=/var/log/projectx-queue.log

- Ensure public disk or S3 is configured for Instagram temporary public URLs used during media upload.

Security

- Store OAuth refresh tokens in social_accounts table. Ensure DB is access-controlled and backups are encrypted.
- Do not commit .env or secrets to version control.

If you want, I can add a CONTRIBUTING or DEPLOYMENT file with supervisor / systemd examples and CI steps.
