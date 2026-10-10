# Project Guidelines

## Architecture

- The Laravel backend is in this repository; the Expo mobile client is the sibling `clippipeline-mobile` project.
- Google web login remains session-based. Mobile Google OAuth uses `POST /api/auth/google/token` with an authorization `code` and the exact Expo `redirect_uri`.
- Mobile API requests use Laravel Sanctum bearer tokens. Keep authenticated mobile endpoints under `routes/api.php` with `auth:sanctum`.

## Code Style

- Follow existing Laravel conventions and use typed controller responses where practical.
- Reuse the existing `SocialAuthController` Google user provisioning behavior for mobile login.
- Do not log or commit OAuth codes, access tokens, client secrets, or other credentials.

## Build and Test

- Backend tests: `php artisan test`
- Backend formatting: `vendor/bin/pint`
- Mobile checks: run `npx.cmd tsc --noEmit` and `npx.cmd expo lint` from the sibling `clippipeline-mobile` directory.

## Mobile OAuth

- Use `expo-auth-session` and `AuthSession.makeRedirectUri({ scheme: 'clippipelinemobile', path: 'oauth' })`.
- Configure the public Google client ID through `EXPO_PUBLIC_GOOGLE_CLIENT_ID`; never hardcode secrets.
- Persist the returned Sanctum token with the existing `useAuthStore`; all API calls must continue through `src/api/client.ts`.
