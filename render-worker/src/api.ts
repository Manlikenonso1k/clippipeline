import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { config } from './config.js';

export type JobType = 'probe' | 'fetch_link' | 'transcribe' | 'analyze_reference' | 'render_preview' | 'render_final' | 'render_template_preview';

export type WorkerAsset = {
  id: number; role: string; mime: string | null; sha256: string | null; size_bytes: number | null; original_name: string | null;
  duration_seconds: number | null; width: number | null; height: number | null; has_audio: boolean | null;
  url: string | null; proxy_url: string | null; transcript_url: string | null;
  silence: { starts: number[]; ends: number[]; lufs: number | null } | null;
};

export type ClaimedJob = {
  id: string;
  type: JobType;
  attempt: number;
  input: Record<string, unknown>;
  asset: WorkerAsset | null;
  project: null | { id: number; title: string; aspect: '9:16' | '1:1' | '16:9'; fps: number; revision: number; edl: Record<string, unknown> };
  assets: Record<string, WorkerAsset>;
  limits: { max_upload_mb: number };
};

export class ApiError extends Error {
  constructor(public status: number, message: string, public retryable: boolean) {
    super(message);
  }
}

async function call(method: string, pathname: string, body?: BodyInit, json = true): Promise<Response> {
  const res = await fetch(`${config.apiUrl}${pathname}`, {
    method,
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${config.token}`,
      ...(json && body ? { 'Content-Type': 'application/json' } : {}),
    },
    body,
  });
  if (res.status >= 400) {
    let text = await res.text();
    if (text.includes('failed to upload')) {
      text += ' — the server rejected the file size: raise PHP upload_max_filesize and post_max_size (see DEPLOY.md, Studio).';
    }
    throw new ApiError(res.status, `${method} ${pathname} → ${res.status}: ${text.slice(0, 300)}`, res.status >= 500 || res.status === 429);
  }
  return res;
}

export async function claim(): Promise<ClaimedJob | null> {
  const res = await call('POST', '/worker/claim', JSON.stringify({ worker_id: config.workerId, capabilities: config.capabilities }));
  if (res.status === 204) return null;
  return (await res.json()) as ClaimedJob;
}

/** Returns the job status the server holds (a `canceled` job should stop). */
export async function heartbeat(id: string, progress: number, message: string): Promise<string> {
  const res = await call('POST', `/worker/jobs/${id}/heartbeat`, JSON.stringify({ progress: Math.max(0, Math.min(1, progress)), message: message.slice(0, 250) }));
  return ((await res.json()) as { status: string }).status;
}

/** Upload result files (name → local path) and the result JSON. Retries on network errors. */
export async function complete(id: string, result: Record<string, unknown>, files: Record<string, string>): Promise<void> {
  for (let attempt = 1; ; attempt++) {
    try {
      const form = new FormData();
      form.append('result', JSON.stringify(result));
      for (const [name, file] of Object.entries(files)) {
        const data = await readFile(file);
        form.append(`files[${name}]`, new Blob([data]), path.basename(file));
      }
      await call('POST', `/worker/jobs/${id}/complete`, form, false);
      return;
    } catch (e) {
      if (attempt >= 4 || (e instanceof ApiError && !e.retryable)) throw e;
      await new Promise((r) => setTimeout(r, attempt * 3000));
    }
  }
}

export async function fail(id: string, error: string, retryable: boolean): Promise<void> {
  await call('POST', `/worker/jobs/${id}/fail`, JSON.stringify({ error: error.slice(0, 4000), retryable }));
}
