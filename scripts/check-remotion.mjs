// npm run check:remotion — every remotion / @remotion/* package must be the exact same version
// in motion/, render-worker/ and the root package.json (STUDIO_SPEC §3).
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const versions = new Map();
for (const dir of ['.', 'motion', 'render-worker']) {
  const file = join(root, dir, 'package.json');
  if (!existsSync(file)) continue;
  const pkg = JSON.parse(readFileSync(file, 'utf8'));
  for (const [name, v] of Object.entries({ ...pkg.dependencies, ...pkg.devDependencies })) {
    if (name === 'remotion' || name.startsWith('@remotion/')) {
      if (!/^\d+\.\d+\.\d+$/.test(v)) {
        console.error(`${dir}/package.json: ${name} must be pinned exactly (got ${v})`);
        process.exitCode = 1;
      }
      versions.set(`${dir}:${name}`, v);
    }
  }
}
const distinct = new Set(versions.values());
if (distinct.size > 1) {
  console.error('Remotion versions differ:\n  ' + [...versions].map(([k, v]) => `${k} ${v}`).join('\n  '));
  process.exitCode = 1;
} else if (!process.exitCode) {
  console.log(`Remotion ${[...distinct][0] ?? '(none)'} in ${versions.size} package entries.`);
}
