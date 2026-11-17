# Third-party notices

ClipPipeline Studio includes or adapts the following third-party material.

| Component | Where | Licence |
|---|---|---|
| **remotion-motion-graphics** skill by haidrrrry (`claude-remotion-skill`) | `.claude/skills/remotion-motion-graphics/` (with its `LICENSE`); `motion/src/theme.ts` adapted from its `assets/theme.ts`; the 10 motion rules in `motion/src/templates/kit.tsx` | MIT, Copyright (c) 2025 haidrrrry |
| **video-use** by Browser Use | `.claude/skills/video-use/` (with its `LICENSE`); the HDR tone-map filter chain in `render-worker/tools/vkit.py` | MIT, Copyright (c) 2026 Browser Use |
| **Remotion agent skills** (remotion-dev/skills) | `.claude/skills/remotion-*` (see `skills-lock.json`) | as published by Remotion |
| **Remotion** | `motion/`, `render-worker/` (npm) | Remotion License: free for individuals and companies of up to 3 people; a Company License is required at 4+ people (see DEPLOY.md, Studio) |
| **Inter** font | `motion/public/fonts/Inter-*.woff2` (`Inter-OFL.txt`) | SIL Open Font License 1.1 |
| **JetBrains Mono** font | `motion/public/fonts/JetBrainsMono-500.woff2` (`JetBrainsMono-OFL.txt`) | SIL Open Font License 1.1 |
| **faster-whisper** (installed on the worker, not bundled) | `render-worker/Dockerfile` | MIT |

Not included: nothing from **anything2explainer** (PolyForm Noncommercial) is copied into these
repositories. prompt-motion.com was used as UX inspiration only; no videos or prompts from it are
included. Templates adapted from someone else's idea carry `meta.credit` and stay inactive until
permission is confirmed.
