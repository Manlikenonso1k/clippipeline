import { bundle } from '@remotion/bundler';
import { makeCancelSignal, renderMedia, selectComposition } from '@remotion/renderer';
import { createHash } from 'node:crypto';
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import path from 'node:path';
import type { WorkerAsset } from '../api.js';
import { cachedInput, download, ensureDir, extOf } from '../cache.js';
import { config } from '../config.js';
import { LocalMedia } from '../server.js';
import { tool } from '../tools.js';
import { Canceled, Permanent, type JobContext, type JobOutput } from './context.js';
// The motion package is the single source of truth for EDL layout and template schemas.
import { checkProps } from '../../../motion/src/templates/registry.js';

type Aspect = '9:16' | '1:1' | '16:9';
type EdlLike = {
  aspect: Aspect; loudness?: number;
  main: { type: string; template?: string; version?: number; props?: unknown; transition_in?: { template: string } | null }[];
  overlays: { template: string; version?: number; props?: unknown }[];
  captions?: { enabled?: boolean; template: string; version?: number; props?: unknown };
};

// ---- Bundle motion/ once per source hash ----
let bundled: { key: string; url: string } | null = null;

function sourceHash(): string {
  const h = createHash('sha256');
  const root = path.dirname(path.dirname(config.motionEntry));
  const walk = (dir: string) => {
    for (const name of readdirSync(dir).sort()) {
      const p = path.join(dir, name);
      if (statSync(p).isDirectory()) walk(p);
      else h.update(p).update(readFileSync(p));
    }
  };
  walk(path.join(root, 'src'));
  h.update(readFileSync(path.join(root, 'package.json')));
  return h.digest('hex').slice(0, 16);
}

export async function motionBundle(): Promise<string> {
  const key = sourceHash();
  if (bundled?.key === key) return bundled.url;
  const outDir = path.join(config.cacheDir, 'bundles', key);
  if (!existsSync(path.join(outDir, 'index.html'))) {
    console.log(`[bundle] building motion bundle ${key}…`);
    await bundle({ entryPoint: config.motionEntry, publicDir: config.motionPublic, outDir, enableCaching: true, onProgress: () => undefined });
  }
  bundled = { key, url: outDir };
  return outDir;
}

/** Validate every template's props with its zod schema before rendering. */
export function validateTemplates(edl: EdlLike): void {
  const checks: [string, number | undefined, unknown][] = [];
  for (const s of edl.main) {
    if (s.type === 'template' && s.template) checks.push([s.template, s.version, s.props]);
    if (s.transition_in?.template) checks.push([s.transition_in.template, undefined, {}]);
  }
  for (const o of edl.overlays) checks.push([o.template, o.version, o.props]);
  if (edl.captions?.enabled) checks.push([edl.captions.template, edl.captions.version, edl.captions.props]);
  for (const [slug, version, props] of checks) {
    const r = checkProps(slug, version, props);
    if (!r.ok) throw new Permanent(r.error);
  }
}

const kindOf = (a: WorkerAsset): 'video' | 'image' | 'audio' =>
  (a.mime ?? '').startsWith('image/') ? 'image' : (a.mime ?? '').startsWith('audio/') ? 'audio' : 'video';

async function remotionRender(ctx: JobContext, composition: string, inputProps: Record<string, unknown>, out: string, opts: { crf: number; preset: 'veryfast' | 'medium'; scale?: number; muted?: boolean; from: number; to: number }): Promise<{ width: number; height: number; durationInFrames: number; fps: number }> {
  const serveUrl = await motionBundle();
  const { cancelSignal, cancel } = makeCancelSignal();
  ctx.onCancel = cancel;
  const comp = await selectComposition({ serveUrl, id: composition, inputProps, browserExecutable: config.browserExecutable });
  try {
    await renderMedia({
      serveUrl,
      composition: comp,
      inputProps,
      codec: 'h264',
      crf: opts.crf,
      x264Preset: opts.preset,
      pixelFormat: 'yuv420p',
      imageFormat: 'jpeg',
      jpegQuality: 90,
      scale: opts.scale ?? 1,
      muted: opts.muted ?? false,
      enforceAudioTrack: !opts.muted,
      audioBitrate: '192k',
      concurrency: config.remotionConcurrency,
      outputLocation: out,
      overwrite: true,
      cancelSignal,
      browserExecutable: config.browserExecutable,
      timeoutInMilliseconds: 120_000,
      onProgress: ({ progress }) => void ctx.report(opts.from + (opts.to - opts.from) * progress, `Rendering ${Math.round(progress * 100)}%`),
    });
  } catch (e) {
    if (ctx.canceled) throw new Canceled('Job was canceled.');
    throw e;
  }
  return { width: Math.round(comp.width * (opts.scale ?? 1)), height: Math.round(comp.height * (opts.scale ?? 1)), durationInFrames: comp.durationInFrames, fps: comp.fps };
}

/** render_preview / render_final: EDL → Remotion → loudnorm (-14 LUFS, -1 dBTP) → check → upload. */
export async function renderProject(ctx: JobContext): Promise<JobOutput> {
  const job = ctx.job;
  if (!job.project) throw new Permanent('The project is gone.');
  const final = job.type === 'render_final';
  const edl = job.project.edl as unknown as EdlLike;
  validateTemplates(edl);

  // 1. Inputs to local disk (cached by asset + sha256). Previews use the 540p proxy.
  const server = new LocalMedia();
  await server.start();
  try {
    const assets: Record<string, unknown> = {};
    const transcripts: Record<string, unknown> = {};
    const list = Object.values(job.assets);
    let i = 0;
    for (const a of list) {
      ctx.throwIfCanceled();
      await ctx.report(0.02 + (0.18 * i++) / Math.max(1, list.length), `Preparing media ${i}/${list.length}`);
      const kind = kindOf(a);
      let file: string;
      if (kind === 'video') {
        if (!final && a.proxy_url) {
          file = await cachedInput(a.id, a.sha256, a.proxy_url, '.mp4', 'proxy');
        } else {
          if (!a.url) throw new Permanent(`"${a.original_name}" is no longer on the server.`);
          const src = await cachedInput(a.id, a.sha256, a.url, extOf(a.original_name, a.mime));
          file = path.join(config.cacheDir, 'inputs', `${a.id}-${(a.sha256 ?? 'x').slice(0, 16)}-conform1080.mp4`);
          if (!existsSync(file)) await tool(['conform', src, file, '--short', '1080']);
        }
      } else {
        if (!a.url) throw new Permanent(`"${a.original_name}" is no longer on the server.`);
        file = await cachedInput(a.id, a.sha256, a.url, extOf(a.original_name, a.mime));
      }
      assets[String(a.id)] = { src: server.add(`a${a.id}`, file), kind, width: a.width, height: a.height, duration: a.duration_seconds, has_audio: a.has_audio };
      if (a.transcript_url) {
        const tf = path.join(ctx.dir, `t${a.id}.json`);
        await download(a.transcript_url, tf);
        transcripts[String(a.id)] = (JSON.parse(readFileSync(tf, 'utf8')) as { words?: unknown[] }).words ?? [];
      }
    }

    // 2. Render.
    const raw = path.join(ctx.dir, 'raw.mp4');
    const meta = await remotionRender(ctx, 'EditTimeline', { edl: job.project.edl, assets, transcripts, quality: final ? 'final' : 'preview' }, raw, {
      crf: final ? 18 : 28, preset: final ? 'medium' : 'veryfast', from: 0.2, to: 0.88,
    });

    // 3. Master loudness, check, poster.
    await ctx.report(0.9, 'Mastering audio');
    const video = path.join(ctx.dir, 'video.mp4');
    await tool(['master', raw, video, '--lufs', String(edl.loudness ?? -14), '--tp', '-1']);
    await ctx.report(0.95, 'Checking the render');
    const check = await tool<Record<string, unknown>>(['check', video]);
    const poster = path.join(ctx.dir, 'poster.jpg');
    const duration = meta.durationInFrames / meta.fps;
    await tool(['frames', video, poster, '--at', String(Math.min(1, duration / 2).toFixed(2))]);
    return { result: { duration: Number(duration.toFixed(3)), width: meta.width, height: meta.height, check }, files: { video, poster } };
  } finally {
    await server.stop();
  }
}

/** render_template_preview: 4 s muted 480p loop ≤ 400 KB + poster, for the gallery. */
export async function renderTemplatePreview(ctx: JobContext): Promise<JobOutput> {
  const { slug, version, aspect, props } = ctx.job.input as { slug: string; version: number; aspect: Aspect; props?: unknown };
  const check = checkProps(slug, version, props ?? {});
  if (!check.ok) throw new Permanent(check.error);
  const raw = path.join(ctx.dir, 'raw.mp4');
  await remotionRender(ctx, `tpl-${slug}-v${version}`, { aspect, props: check.props, seconds: 4, quality: 'template' }, raw, { crf: 23, preset: 'veryfast', muted: true, from: 0.05, to: 0.85 });
  const video = path.join(ctx.dir, 'loop.mp4');
  const poster = path.join(ctx.dir, 'poster.jpg');
  await ensureDir(ctx.dir);
  const r = await tool<{ bytes: number }>(['loop', raw, video, poster]);
  return { result: { bytes: r.bytes }, files: { video, poster } };
}
