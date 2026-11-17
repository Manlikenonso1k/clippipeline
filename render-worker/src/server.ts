import { createReadStream, statSync } from 'node:fs';
import http from 'node:http';
import type { AddressInfo } from 'node:net';
import path from 'node:path';

const TYPES: Record<string, string> = {
  '.mp4': 'video/mp4', '.mov': 'video/quicktime', '.webm': 'video/webm', '.mp3': 'audio/mpeg', '.m4a': 'audio/mp4', '.wav': 'audio/wav',
  '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png', '.webp': 'image/webp',
};

/**
 * Serves render inputs from local disk to the headless browser (CORS + Range), so
 * Remotion never streams from production mid-render. Only registered files are served.
 */
export class LocalMedia {
  private files = new Map<string, string>();
  private server: http.Server | null = null;
  private base = '';

  async start(): Promise<void> {
    this.server = http.createServer((req, res) => {
      const key = decodeURIComponent((req.url ?? '').split('?')[0].replace(/^\/+/, ''));
      const file = this.files.get(key);
      res.setHeader('Access-Control-Allow-Origin', '*');
      res.setHeader('Access-Control-Allow-Headers', 'Range');
      res.setHeader('Access-Control-Expose-Headers', 'Content-Length, Content-Range, Accept-Ranges');
      if (req.method === 'OPTIONS') return void res.writeHead(204).end();
      if (!file) return void res.writeHead(404).end();
      const size = statSync(file).size;
      const type = TYPES[path.extname(file).toLowerCase()] ?? 'application/octet-stream';
      const range = /bytes=(\d*)-(\d*)/.exec(req.headers.range ?? '');
      if (range) {
        const start = range[1] ? Number(range[1]) : 0;
        const end = range[2] ? Math.min(Number(range[2]), size - 1) : size - 1;
        res.writeHead(206, { 'Content-Type': type, 'Accept-Ranges': 'bytes', 'Content-Range': `bytes ${start}-${end}/${size}`, 'Content-Length': end - start + 1 });
        if (req.method === 'HEAD') return void res.end();
        createReadStream(file, { start, end }).pipe(res);
        return;
      }
      res.writeHead(200, { 'Content-Type': type, 'Accept-Ranges': 'bytes', 'Content-Length': size });
      if (req.method === 'HEAD') return void res.end();
      createReadStream(file).pipe(res);
    });
    await new Promise<void>((resolve) => this.server!.listen(0, '127.0.0.1', resolve));
    this.base = `http://127.0.0.1:${(this.server.address() as AddressInfo).port}`;
  }

  /** Register a file and get its URL. */
  add(key: string, file: string): string {
    const k = `${key}${path.extname(file)}`;
    this.files.set(k, file);
    return `${this.base}/${encodeURIComponent(k)}`;
  }

  async stop(): Promise<void> {
    this.files.clear();
    await new Promise<void>((resolve) => (this.server ? this.server.close(() => resolve()) : resolve()));
  }
}
