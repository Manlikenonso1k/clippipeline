import { loadFont } from '@remotion/fonts';
import { continueRender, delayRender, staticFile } from 'remotion';

// OFL fonts committed in public/fonts (licences next to them).
const FACES = [
  { family: 'Inter', weight: '400', file: 'fonts/Inter-400.woff2' },
  { family: 'Inter', weight: '600', file: 'fonts/Inter-600.woff2' },
  { family: 'Inter', weight: '700', file: 'fonts/Inter-700.woff2' },
  { family: 'Inter', weight: '800', file: 'fonts/Inter-800.woff2' },
  { family: 'Inter', weight: '900', file: 'fonts/Inter-900.woff2' },
  { family: 'JetBrains Mono', weight: '500', file: 'fonts/JetBrainsMono-500.woff2' },
  { family: 'Anton', weight: '400', file: 'fonts/Anton-400.woff2' },
];

let started = false;

/** Load every font once and hold the render until they are ready; fail loudly if one is missing. */
export function ensureFonts(): void {
  if (started || typeof document === 'undefined') return;
  started = true;
  const handle = delayRender('Loading fonts');
  Promise.all(FACES.map((f) => loadFont({ family: f.family, url: staticFile(f.file), weight: f.weight, display: 'block' })))
    .then(() => {
      for (const f of FACES) {
        if (!document.fonts.check(`${f.weight} 32px "${f.family}"`)) {
          throw new Error(`Font ${f.family} ${f.weight} did not load`);
        }
      }
      continueRender(handle);
    })
    .catch((err: unknown) => {
      // cancelRender makes the render fail with this message instead of timing out.
      import('remotion').then((r) => r.cancelRender(err instanceof Error ? err : new Error(String(err))));
    });
}
