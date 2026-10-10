The web-based Google OAuth and YouTube connection is already working in our Laravel backend. Now we just need to connect the Expo mobile app (in the clippipeline app folder) to it.

Here is how the agent should implement it:
1. Backend Addition (clippipeline repository):
   - Add a lightweight API endpoint (e.g., POST /api/auth/google/token) that accepts `code` and `redirect_uri`.
   - Use Laravel Socialite stateless (`Socialite::driver('google')->stateless()->redirectUrl($redirectUri)->userFromCode($code)`) to process the mobile code, matching how the web app handles it.
   - Issue and return a standard Laravel Sanctum token (`plainTextToken`) and the user object, exactly like our existing `POST /api/auth/login` endpoint does.

2. Mobile App Addition (clippipeline app folder):
   - In the mobile app login screen, use `expo-auth-session` (with our public Google Client ID and app redirect scheme) to prompt the user.
   - When successful, grab the authorization `code` from the response parameters.
   - Send that `code` and `redirect_uri` to the new `POST /api/auth/google/token` backend endpoint.
   - Save the returned Sanctum token to secure storage so subsequent API requests and the social-account fetching (`GET /social-accounts`) work seamlessly.