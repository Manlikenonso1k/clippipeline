import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { rename, stat } from 'node:fs/promises';
import path from 'node:path';
import type { WorkerAsset } from '../api.js';
import { cachedInput, download, extOf, sha256File } from '../cache.js';
import { config } from '../config.js';
import { tool } from '../tools.js';
import { Permanent, type JobContext, type JobOutput } from './context.js';

async function original(asset: WorkerAsset | null): Promise<{ asset: WorkerAsset; file: string }> {
  if (!asset?.url) throw new Permanent('The source file is no longer on the server.');
  return { asset, file: await cachedInput(asset.id, asset.sha256, asset.url, extOf(asset.original_name, asset.mime)) };
}

export async function probe(ctx: JobContext): Promise<JobOutput> {
  const { file } = await original(ctx.job.asset);
  await ctx.report(0.2, 'Reading the file');
  const r = await tool<Record<string, unknown> & { files: Record<string, string> }>(['probe', file, ctx.dir]);
  const files: Record<string, string> = {};
  for (const [k, name] of Object.entries(r.files ?? {})) files[k] = path.join(ctx.dir, name);
  const { files: _ignored, ...result } = r;
  return { result, files };
}

export async function transcribe(ctx: JobContext): Promise<JobOutput> {
  const { file } = await original(ctx.job.asset);
  await ctx.report(0.1, 'Transcribing');
  const out = path.join(ctx.dir, 'transcript.json');
  const lang = typeof ctx.job.input.language === 'string' ? ['--language', ctx.job.input.language] : [];
  const r = await tool(['transcribe', file, out, ...lang]);
  return { result: r, files: { transcript: out } };
}

export async function analyzeReference(ctx: JobContext): Promise<JobOutput> {
  const { file } = await original(ctx.job.asset);
  await ctx.report(0.15, 'Analysing the reference style');
  const threshold = Number(ctx.job.input.scene_threshold ?? 0.3);
  const r = await tool<{ style_profile: Record<string, unknown>; files: { contact_sheet: string } }>(['analyze', file, ctx.dir, '--threshold', String(threshold)]);
  return { result: { style_profile: r.style_profile }, files: { contact_sheet: path.join(ctx.dir, r.files.contact_sheet) } };
}

const MIME: Record<string, string> = { '.mp4': 'video/mp4', '.mov': 'video/quicktime', '.webm': 'video/webm', '.mp3': 'audio/mpeg', '.m4a': 'audio/mp4', '.wav': 'audio/wav', '.jpg': 'image/jpeg', '.png': 'image/png', '.webp': 'image/webp' };

/** fetch_link: direct HTTPS media, or yt-dlp when the server allowed it (STUDIO_LINK_FETCHER=ytdlp). */
export async function fetchLink(ctx: JobContext): Promise<JobOutput> {
  const url = String(ctx.job.input.url ?? '');
  const maxBytes = (ctx.job.limits?.max_upload_mb ?? 500) * 1024 * 1024;
  let file: string;
  if (ctx.job.input.ytdlp) {
    await ctx.report(0.1, 'Downloading with yt-dlp');
    const tmpl = path.join(ctx.dir, 'link.%(ext)s');
    await new Promise<void>((resolve, reject) => {
      const p = spawn(config.ytdlp, ['-f', 'mp4/bestvideo[ext=mp4]+bestaudio[ext=m4a]/best', '--max-filesize', String(maxBytes), '--no-playlist', '-o', tmpl, url], { stdio: ['ignore', 'ignore', 'pipe'] });
      let err = '';
      p.stderr.on('data', (d) => (err += d));
      p.on('error', (e) => reject(new Permanent(`yt-dlp is not installed on the worker (${e.message}).`)));
      p.on('close', (code) => (code === 0 ? resolve() : reject(new Error(`yt-dlp failed: ${err.trim().slice(-400)}`))));
    });
    const found = ['.mp4', '.webm', '.mov', '.mkv'].map((e) => path.join(ctx.dir, `link${e}`)).find((f) => existsSync(f));
    if (!found) throw new Permanent('yt-dlp did not produce a video file.');
    file = found;
  } else {
    await ctx.report(0.1, 'Downloading');
    const name = path.basename(new URL(url).pathname) || 'link';
    const ext = path.extname(name) || '.mp4';
    file = path.join(ctx.dir, `link${ext}`);
    await download(url, file, maxBytes);
  }
  if ((await stat(file)).size > maxBytes) throw new Permanent('The linked file is too large.');
  const ext = path.extname(file).toLowerCase();
  const final = path.join(ctx.dir, `original${ext}`);
  await rename(file, final);
  return { result: { sha256: await sha256File(final), mime: MIME[ext] ?? 'video/mp4', name: path.basename(new URL(url).pathname) || `link${ext}` }, files: { original: final } };
}
