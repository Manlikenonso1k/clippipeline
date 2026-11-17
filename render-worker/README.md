# ClipPipeline Studio render worker

Pull-model worker (docs/STUDIO_SPEC.md §3, §6). It polls Laravel for Studio jobs, downloads inputs
through signed URLs into a local cache (asset id + sha256), runs ffmpeg / faster-whisper / Remotion,
and uploads results. It needs outbound HTTPS only, so it works behind home NAT.

| Job | What it does |
|---|---|
| `probe` | ffprobe facts, 540p H.264 proxy, poster, thumbnail sprite, waveform, silence + loudness |
| `fetch_link` | direct HTTPS media (or yt-dlp when the server allows it) |
| `transcribe` | faster-whisper word timestamps (ElevenLabs Scribe when `ELEVENLABS_API_KEY` is set) |
| `analyze_reference` | scene cuts, pace, palette, loudness → style profile + contact sheet |
| `render_preview` / `render_final` | `motion/` EditTimeline → loudnorm -14 LUFS / -1 dBTP → check → upload |
| `render_template_preview` | 4 s muted 480p gallery loop (≤ 400 KB) + poster |

Run locally: `cp .env.example .env`, fill in the token (`php artisan studio:worker-token`), then
`npm install` here and in `../motion`, and `npm start`. `npm run once` drains the queue and exits.

`tools/studio_tools.py` (JSON CLI) is built on `tools/vkit.py`, ported from the video-editing-
workspace. Install and service files: `Dockerfile`, `clippipeline-render.service`; see DEPLOY.md.
