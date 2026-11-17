import { createHash } from 'node:crypto';
import { createReadStream, createWriteStream, existsSync } from 'node:fs';
import { mkdir, rename, rm, stat } from 'node:fs/promises';
import path from 'node:path';
import { Readable } from 'node:stream';
import { pipeline } from 'node:stream/promises';
import { config } from './config.js';

export async function ensureDir(dir: string): Promise<string> {
  await mkdir(dir, { recursive: true });
  return dir;
}

export function sha256File(file: string): Promise<string> {
  return new Promise((resolve, reject) => {
    const h = createHash('sha256');
    createReadStream(file).on('data', (d) => h.update(d)).on('error', reject).on('end', () => resolve(h.digest('hex')));
  });
}

/** Download to dest (atomic rename); throws above maxBytes. */
export async function download(url: string, dest: string, maxBytes = Infinity): Promise<void> {
  await ensureDir(path.dirname(dest));
  const res = await fetch(url);
  if (!res.ok || !res.body) throw new Error(`Download failed (${res.status}) for ${new URL(url).pathname}`);
  const len = Number(res.headers.get('content-length') || 0);
  if (len > maxBytes) throw new Error(`File is larger than ${Math.round(maxBytes / 1048576)} MB`);
  const tmp = `${dest}.part`;
  let seen = 0;
  const body = Readable.fromWeb(res.body as never);
  body.on('data', (chunk: Buffer) => {
    seen += chunk.length;
    if (seen > maxBytes) body.destroy(new Error(`File is larger than ${Math.round(maxBytes / 1048576)} MB`));
  });
  try {
    await pipeline(body, createWriteStream(tmp));
    await rename(tmp, dest);
  } catch (e) {
    await rm(tmp, { force: true });
    throw e;
  }
}

/**
 * Inputs are cached by asset id + sha256, so a clip is downloaded once and reused by
 * every later job (probe, transcribe, renders). Never streamed from production mid-render.
 */
export async function cachedInput(assetId: number, sha: string | null, url: string, ext: string, kind = 'original'): Promise<string> {
  const key = `${assetId}-${(sha || createHash('sha1').update(url.split('?')[0]).digest('hex')).slice(0, 16)}-${kind}${ext}`;
  const file = path.join(config.cacheDir, 'inputs', key);
  if (existsSync(file) && (await stat(file)).size > 0) return file;
  await download(url, file);
  return file;
}

export function extOf(name: string | null | undefined, mime: string | null | undefined): string {
  const m = /\.[a-z0-9]{2,5}$/i.exec(name ?? '');
  if (m) return m[0].toLowerCase();
  const map: Record<string, string> = {
    'video/mp4': '.mp4', 'video/quicktime': '.mov', 'video/webm': '.webm', 'audio/mpeg': '.mp3', 'audio/mp4': '.m4a',
    'audio/x-m4a': '.m4a', 'audio/wav': '.wav', 'audio/x-wav': '.wav', 'image/jpeg': '.jpg', 'image/png': '.png', 'image/webp': '.webp',
  };
  return map[mime ?? ''] ?? '.bin';
}

export async function workDir(jobId: string): Promise<string> {
  return ensureDir(path.join(config.cacheDir, 'jobs', jobId));
}
