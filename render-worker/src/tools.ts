import { spawn } from 'node:child_process';
import { config } from './config.js';

/** Run render-worker/tools/studio_tools.py and parse its one-line JSON answer. */
export function tool<T = Record<string, unknown>>(args: string[], env: Record<string, string> = {}): Promise<T> {
  return new Promise((resolve, reject) => {
    const p = spawn(config.python, [config.toolsScript, ...args], { env: { ...process.env, ...env }, stdio: ['ignore', 'pipe', 'pipe'] });
    let out = '';
    let err = '';
    p.stdout.on('data', (d) => (out += d));
    p.stderr.on('data', (d) => (err += d));
    p.on('error', reject);
    p.on('close', (code) => {
      const line = out.trim().split('\n').pop() ?? '';
      let parsed: unknown = null;
      try {
        parsed = JSON.parse(line);
      } catch {
        /* handled below */
      }
      if (code !== 0 || !parsed || (parsed as { error?: string }).error) {
        reject(new Error(((parsed as { error?: string } | null)?.error || err.trim().split('\n').slice(-3).join(' ') || `studio_tools ${args[0]} failed (${code})`).slice(0, 1500)));
        return;
      }
      resolve(parsed as T);
    });
  });
}
