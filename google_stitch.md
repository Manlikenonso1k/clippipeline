# ClipPipeline — Mobile App Design Prompts (Google Stitch + Claude)

Prompts to generate the **ClipPipeline** mobile UI (iOS + Android) in
[Google Stitch](https://stitch.withgoogle.com) and in Claude. Derived from the
actual backend (see `graphify-out/GRAPH_REPORT.md`): a Laravel + Filament app that
**pulls a creator's TikTok videos and cross-posts them to YouTube Shorts and
Instagram Reels**, then tracks per-platform analytics.

How to use:
1. Paste **§1 (Master context)** first — it sets the product, audience, and design system.
2. Then paste one **§3 screen prompt** at a time (Stitch designs one screen best per prompt).
3. For Claude, paste §1 + the screen prompt and ask for a React Native (Expo) component using the tokens in §2.

---

## 1. Master context (paste this first, every time)

```
You are designing a production mobile app called ClipPipeline for iOS and Android.

What the app does: creators connect their TikTok account, and ClipPipeline
automatically downloads their latest TikTok videos and re-publishes them as
YouTube Shorts and Instagram Reels — one clip, posted everywhere. The app then
shows per-platform analytics (views, likes, comments, shares, watch time) so the
creator sees how each repost performs.

Target user: solo content creators and small social teams. They are mobile-first,
design-literate (they live on TikTok/IG), and want a clean, fast, confidence-
inspiring dashboard — not a cluttered enterprise tool.

Tone & style: modern, bold, creator-friendly. Dark-first UI with vivid accent
gradients, generous spacing, large rounded cards, big legible numbers for stats.
Think "Linear meets a social analytics app." No clip-art, no emoji in the UI —
use clean line icons (Material Symbols). Every platform is identified by its brand
color (TikTok, YouTube red, Instagram gradient).

Platforms shown throughout: TikTok (source), YouTube Shorts, Instagram Reels (targets).
Each cross-post has a status: Pending, Processing, Published, or Failed — always show
this as a colored pill/badge.
```

## 2. Design system (tokens — reuse for Stitch and Claude)

```
Colors (dark theme, primary):
  background      #0B0B0F
  surface/card    #16161D
  surface-raised  #1E1E28
  border          #2A2A36
  text-primary    #FFFFFF
  text-secondary  #A1A1AE
  accent/brand    #7C5CFF  (violet) with gradient to #B14CFF
  success         #28C76F  (Published / on-track)
  warning         #FF9F43  (Processing / pending)
  danger          #EA5455  (Failed)
Platform colors:
  TikTok          #00F2EA / #FE2C55 (cyan→pink)
  YouTube         #FF0000
  Instagram       gradient #F58529 → #DD2A7B → #8134AF

Typography: Inter. Scale — Display 32/700, H1 24/700, H2 18/600,
  Body 15/400, Caption 13/500. Big stat numbers 28–40/700, tabular figures.

Shape & spacing: 16px screen gutters, 20px radius cards, 12px radius inputs,
  8pt spacing grid, soft shadow on raised cards. 44px min tap targets.

Nav: bottom tab bar (Home, Posts, Connect, Insights, Profile) with line icons
  and the active tab in the violet accent.
```

## 3. Screen prompts (paste one at a time into Stitch)

### 3.1 Sign in
```
Design a mobile sign-in screen for ClipPipeline. Centered logo + one-line tagline
"Post once. Everywhere." A single primary button "Continue with Google" (white
button, Google icon). Secondary small links to Terms and Privacy at the bottom.
Dark background with a subtle violet gradient glow behind the logo. This is the
only auth method — do not add email/password fields.
```

### 3.2 Home / Dashboard
```
Design the Home dashboard for ClipPipeline. Top: greeting with the user's avatar
and name. A row of 4 stat cards with big numbers and tiny trend arrows: Total Views,
Total Likes, Posts Published, Connected Accounts. Below: a "Performance" line/area
chart (views over the last 30 days) with a period toggle (7d / 30d / 90d). Below
that: "Top Posts" — a list of 3 clips showing thumbnail, title, and a compact
per-platform views breakdown (TikTok/YouTube/Instagram with their brand colors).
Pull-to-refresh. Use large rounded cards on a dark background.
```

### 3.3 Connect Accounts
```
Design a "Connect Accounts" screen for ClipPipeline. A vertical list of 3 large
cards, one per platform: TikTok (source), YouTube, Instagram. Each card shows the
platform icon in its brand color, the account handle if connected (e.g. @creator,
with follower count) and a status: a green "Connected" pill with a Disconnect option,
or a violet "Connect" button if not connected. TikTok card is visually emphasized as
the required source. Include a short helper line under the title: "Connect TikTok to
import clips, then connect YouTube and Instagram to publish them."
```

### 3.4 Posts list
```
Design a "Posts" list screen for ClipPipeline. Each row is a card: left = vertical
video thumbnail (9:16), right = title + caption snippet + published date, and a row
of 3 small platform badges (YouTube, Instagram, and TikTok source) each colored by
their cross-post status (green Published, orange Processing/Pending, red Failed).
A filter chip row at top: All / Published / Processing / Failed. A floating action
button "+ New cross-post". Infinite scroll list on dark background.
```

### 3.5 Post detail
```
Design a post detail screen for ClipPipeline. Top: large 9:16 video preview with a
play button, title, and full caption. A "Distribution" section: one row per target
platform (YouTube Shorts, Instagram Reels) showing the status pill (Pending/
Processing/Published/Failed), a link to the live post when published, and an error
message line when failed with a "Retry" button. Below: an "Analytics" section with
per-platform stat tiles — Views, Likes, Comments, Shares, Watch time — grouped by
platform with their brand colors. Last-updated timestamp at the bottom.
```

### 3.6 New cross-post flow
```
Design a "New cross-post" flow for ClipPipeline (2 steps, mobile).
Step 1 "Pick a clip": a grid of the creator's imported TikTok videos (9:16
thumbnails) with the one selected highlighted by a violet border.
Step 2 "Choose where to post": the selected clip at top, an editable caption field,
and two large toggle cards — "YouTube Shorts" and "Instagram Reels" — each selectable
with their brand color when on. A sticky bottom primary button "Publish to selected".
Show which accounts are connected; disable a target that isn't connected with a
"Connect first" hint.
```

### 3.7 Insights / Analytics
```
Design an "Insights" screen for ClipPipeline. A platform selector segmented control
at top (All / TikTok / YouTube / Instagram). A big "Views trend" area chart. Below,
a 2x2 grid of KPI cards: Views, Likes, Comments, Shares, each with the number and a
small sparkline and % change. Below that a "Top performing clips" ranked list. Dark
theme, big tabular numbers, violet accent on the active segment.
```

### 3.8 Profile / Settings & Billing
```
Design a Profile & Settings screen for ClipPipeline. Top: avatar, name, email, and
current plan badge (Free / Starter / Pro). A "Plan & Billing" card showing the
current plan with an "Upgrade" button that opens a pricing sheet (3 plan cards with
price in the user's currency, feature list, and a Choose button). Settings list:
Notifications toggle, Connected accounts shortcut, Terms, Privacy, Log out (in red).
Clean grouped list rows on a dark background.
```

---

## 4. Tips for best results

- **Stitch**: generate screens one at a time, then use "edit" to refine spacing/
  colors. Export to Figma or copy the generated code as a starting point.
- **Claude**: ask for Expo + React Native + TypeScript components that use the §2
  tokens as a `theme.ts`, with loading skeletons and empty states (per the project's
  `instructions.md` frontend rules — no blank spinners, MaterialIcons only).
- Keep the **status pill** (Pending/Processing/Published/Failed) and **platform
  brand colors** consistent across every screen — they are the app's core visual
  language, straight from the `post_distributions` and `post_analytics` data model.
- Data each screen binds to (from the backend): `posts` (title, caption, video_url,
  published_at), `post_distributions` (platform, status, external_id, error_message),
  `post_analytics` (views, likes, comments, shares, watch_time), `social_accounts`
  (provider, handle, follower_count).
```
