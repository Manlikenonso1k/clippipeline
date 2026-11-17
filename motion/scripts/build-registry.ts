// npm run templates:build — validates every template and writes dist/templates.json (CONTRACT §17.1 schema subset).
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { z } from 'zod';
import { TEMPLATES } from '../src/templates/registry';
import { CATEGORIES } from '../src/templates/types';

const here = dirname(fileURLToPath(import.meta.url));
type Json = Record<string, unknown>;

/** Flatten zod's JSON Schema to the subset clients understand. */
function simplify(prop: Json): Json {
  const out: Json = {};
  for (const k of ['title', 'description', 'format', 'default', 'enum', 'minimum', 'maximum', 'maxLength', 'maxItems']) {
    if (prop[k] !== undefined) out[k] = prop[k];
  }
  const fmt = String(prop.format ?? '');
  if (fmt.startsWith('asset:')) {
    out.type = 'object';
    return out;
  }
  out.type = prop.type;
  if (prop.type === 'array') out.items = { type: 'string', maxLength: (prop.items as Json | undefined)?.maxLength };
  return out;
}

const errors: string[] = [];
const seen = new Set<string>();
const templates = TEMPLATES.map((t) => {
  const key = `${t.meta.slug}@${t.meta.version}`;
  if (seen.has(key)) errors.push(`${key}: duplicate`);
  seen.add(key);
  if (!/^[a-z0-9-]+$/.test(t.meta.slug)) errors.push(`${key}: slug must be kebab-case`);
  for (const c of t.meta.categories) if (!(CATEGORIES as readonly string[]).includes(c)) errors.push(`${key}: unknown category ${c}`);
  if (t.meta.kind === 'transition' && !t.presentation) errors.push(`${key}: transitions need a presentation`);
  for (const a of ['9:16', '1:1', '16:9']) if (!t.meta.aspects.includes(a as never)) errors.push(`${key}: must support ${a}`);

  const defaults = t.schema.safeParse({});
  if (!defaults.success) errors.push(`${key}: defaults do not validate (${defaults.error.issues[0]?.message})`);
  const json = z.toJSONSchema(t.schema, { io: 'input', unrepresentable: 'any' }) as Json;
  const properties: Record<string, Json> = {};
  for (const [name, prop] of Object.entries((json.properties ?? {}) as Record<string, Json>)) {
    const p = simplify(prop);
    if (!['string', 'number', 'integer', 'boolean', 'array', 'object'].includes(String(p.type))) errors.push(`${key}.${name}: unsupported type`);
    if (p.type === 'object' && !String(p.format ?? '').startsWith('asset:')) errors.push(`${key}.${name}: objects must be asset pickers`);
    if (p.type === 'string' && p.format !== 'color' && !p.enum && p.maxLength === undefined) errors.push(`${key}.${name}: strings need a max length`);
    properties[name] = p;
  }

  return {
    slug: t.meta.slug, version: t.meta.version, name: t.meta.name, categories: t.meta.categories, kind: t.meta.kind,
    aspects: t.meta.aspects, defaultDuration: t.meta.defaultDuration, minDuration: t.meta.minDuration, maxDuration: t.meta.maxDuration,
    credit: t.meta.credit, isActive: t.meta.credit === null, sort: t.meta.sort ?? 100,
    propsSchema: { type: 'object', properties },
    defaultProps: defaults.success ? defaults.data : {},
  };
});

if (errors.length) {
  console.error('Template validation failed:\n  ' + errors.join('\n  '));
  process.exit(1);
}

const dest = join(here, '..', 'dist', 'templates.json');
mkdirSync(dirname(dest), { recursive: true });
writeFileSync(dest, JSON.stringify({ generated_at: new Date().toISOString().slice(0, 10), templates }, null, 2) + '\n');
console.log(`templates:build — ${templates.length} templates OK → dist/templates.json`);
