import { heartbeat, type ClaimedJob } from '../api.js';

export class Canceled extends Error {}

/** Progress reporting (throttled heartbeats; also keeps the lease alive) and cancel detection. */
export class JobContext {
  private last = 0;
  private stopped = false;
  canceled = false;
  onCancel: (() => void) | null = null;
  private timer: NodeJS.Timeout;

  constructor(public job: ClaimedJob, public dir: string) {
    // Keep the 10-minute lease alive even when a step reports no progress.
    this.timer = setInterval(() => void this.beat(this.progress, this.message, true), 60_000);
  }

  progress = 0;
  message = 'Started';

  async report(progress: number, message: string): Promise<void> {
    this.progress = progress;
    this.message = message;
    await this.beat(progress, message, false);
  }

  private async beat(progress: number, message: string, force: boolean): Promise<void> {
    if (this.stopped) return;
    const now = Date.now();
    if (!force && now - this.last < 5000) return;
    this.last = now;
    try {
      const status = await heartbeat(this.job.id, progress, message);
      if (status === 'canceled' && !this.canceled) {
        this.canceled = true;
        this.onCancel?.();
      }
    } catch (e) {
      console.warn(`[${this.job.id}] heartbeat failed: ${(e as Error).message}`);
    }
  }

  throwIfCanceled(): void {
    if (this.canceled) throw new Canceled('Job was canceled.');
  }

  stop(): void {
    this.stopped = true;
    clearInterval(this.timer);
  }
}

/** A job's output: result JSON + files to upload (name → local path). */
export type JobOutput = { result: Record<string, unknown>; files: Record<string, string> };

/** Failures the worker should not retry (bad input, not a flaky machine). */
export class Permanent extends Error {}
