import { rm } from 'node:fs/promises';
import { ApiError, claim, complete, fail, type ClaimedJob } from './api.js';
import { workDir } from './cache.js';
import { config } from './config.js';
import { Canceled, JobContext, Permanent, type JobOutput } from './jobs/context.js';
import { analyzeReference, fetchLink, probe, transcribe } from './jobs/media.js';
import { motionBundle, renderProject, renderTemplatePreview } from './jobs/render.js';

const HANDLERS: Record<string, (ctx: JobContext) => Promise<JobOutput>> = {
  probe,
  fetch_link: fetchLink,
  transcribe,
  analyze_reference: analyzeReference,
  render_preview: renderProject,
  render_final: renderProject,
  render_template_preview: renderTemplatePreview,
};

const once = process.argv.includes('--once');
let stopping = false;
let running = 0;

async function run(job: ClaimedJob): Promise<void> {
  const started = Date.now();
  const dir = await workDir(job.id);
  const ctx = new JobContext(job, dir);
  console.log(`[${job.id}] ${job.type} (attempt ${job.attempt}) started`);
  try {
    const handler = HANDLERS[job.type];
    if (!handler) throw new Permanent(`This worker cannot run ${job.type} jobs.`);
    const out = await handler(ctx);
    ctx.throwIfCanceled();
    await ctx.report(0.99, 'Uploading');
    await complete(job.id, out.result, out.files);
    console.log(`[${job.id}] ${job.type} done in ${((Date.now() - started) / 1000).toFixed(1)} s`);
  } catch (e) {
    const err = e as Error;
    if (e instanceof Canceled) {
      console.log(`[${job.id}] canceled`);
    } else {
      const retryable = !(e instanceof Permanent) && !(e instanceof ApiError && !e.retryable);
      console.error(`[${job.id}] ${job.type} failed: ${err.message}`);
      await fail(job.id, err.message || String(e), retryable).catch((x) => console.error(`[${job.id}] could not report failure: ${(x as Error).message}`));
    }
  } finally {
    ctx.stop();
    await rm(dir, { recursive: true, force: true }).catch(() => undefined);
  }
}

async function loop(): Promise<void> {
  console.log(`ClipPipeline render worker ${config.workerId} → ${config.apiUrl} (concurrency ${config.concurrency}, Remotion ${config.remotionConcurrency})`);
  if (config.capabilities.some((c) => c.startsWith('render'))) {
    await motionBundle().catch((e) => console.error(`[bundle] ${(e as Error).message}`));
  }
  while (!stopping) {
    if (running >= config.concurrency) {
      await new Promise((r) => setTimeout(r, 1000));
      continue;
    }
    let job: ClaimedJob | null = null;
    try {
      job = await claim();
    } catch (e) {
      console.error(`claim failed: ${(e as Error).message}`);
    }
    if (!job) {
      if (once && running === 0) break;
      await new Promise((r) => setTimeout(r, config.pollSeconds * 1000));
      continue;
    }
    running++;
    void run(job).finally(() => running--);
  }
  while (running > 0) await new Promise((r) => setTimeout(r, 500));
}

for (const sig of ['SIGINT', 'SIGTERM'] as const) {
  process.on(sig, () => {
    console.log(`${sig}: finishing running jobs, then stopping`);
    stopping = true;
  });
}

loop().then(() => process.exit(0));
