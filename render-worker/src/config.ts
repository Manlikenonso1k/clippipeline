import 'dotenv/config';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));

function required(name: string): string {
  const v = process.env[name]?.trim();
  if (!v) throw new Error(`Set ${name} in render-worker/.env (see .env.example).`);
  return v;
}

export const config = {
  apiUrl: required('CLIPPIPELINE_API_URL').replace(/\/+$/, ''),
  token: required('CLIPPIPELINE_WORKER_TOKEN'),
  workerId: process.env.WORKER_ID?.trim() || os.hostname(),
  concurrency: Math.max(1, Number(process.env.WORKER_CONCURRENCY || 1)),
  cacheDir: process.env.WORKER_CACHE_DIR?.trim() || path.join(os.tmpdir(), 'clippipeline-render'),
  pollSeconds: Math.max(2, Number(process.env.WORKER_POLL_SECONDS || 5)),
  /** Remotion render concurrency: about half the cores by default. */
  remotionConcurrency: Number(process.env.REMOTION_CONCURRENCY || 0) || Math.max(1, Math.floor(os.cpus().length / 2)),
  /** Optional: a Chrome Headless Shell to use instead of the one Remotion downloads. */
  browserExecutable: process.env.REMOTION_BROWSER_EXECUTABLE?.trim() || null,
  python: process.env.PYTHON?.trim() || 'python3',
  toolsScript: path.resolve(here, '..', 'tools', 'studio_tools.py'),
  motionEntry: path.resolve(here, '..', '..', 'motion', 'src', 'index.ts'),
  motionPublic: path.resolve(here, '..', '..', 'motion', 'public'),
  capabilities: (process.env.WORKER_CAPABILITIES?.split(',').map((s) => s.trim()).filter(Boolean)) ?? [
    'probe', 'fetch_link', 'transcribe', 'analyze_reference', 'render_preview', 'render_final', 'render_template_preview',
  ],
  ytdlp: process.env.YTDLP_BIN?.trim() || 'yt-dlp',
};
