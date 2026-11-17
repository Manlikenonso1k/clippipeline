import type React from 'react';
import type { TransitionPresentation } from '@remotion/transitions';
import { z } from 'zod';
import type { Aspect, AssetRef, CaptionWord } from '../edl';
import type { ProjectTheme } from '../theme';

export type TemplateKind = 'segment' | 'overlay' | 'captions' | 'transition';
export const CATEGORIES = ['Kinetic type', 'Product UI', 'Phone', 'Charts', 'Diagrams', 'Shapes', 'Particles', 'Characters', 'Photos', 'Music', 'Code'] as const;
export type Category = (typeof CATEGORIES)[number];

export type TemplateMeta = {
  slug: string;
  version: number;
  name: string;
  categories: Category[];
  kind: TemplateKind;
  aspects: Aspect[];
  defaultDuration: number;
  minDuration: number;
  maxDuration: number;
  /** Set for permissioned adaptations of someone else's idea (stays inactive until confirmed). */
  credit: { handle: string; url?: string; note?: string } | null;
  sort?: number;
};

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type AnySchema = z.ZodObject<any>;

export type TemplateDef<S extends AnySchema = AnySchema> = {
  meta: TemplateMeta;
  schema: S;
  Component: React.FC<{ props: z.output<S> }>;
  /** Transitions also provide a presentation for TransitionSeries. */
  presentation?: (props: z.output<S>) => TransitionPresentation<Record<string, unknown>>;
};

/** What every template can read (theme, size, media, captions). */
export type TemplateCtx = {
  theme: ProjectTheme;
  aspect: Aspect;
  width: number;
  height: number;
  /** min(width, height) / 1080: multiply design sizes by this. */
  unit: number;
  resolveAsset: (ref: unknown) => AssetRef | null;
  words: CaptionWord[];
  musicSrc: string | null;
  /** True only in gallery previews (no project): templates may show sample content. */
  demo: boolean;
};

// ---- Props schema subset helpers (CONTRACT §17.1) ----
export const text = (max: number, def: string, title: string) => z.string().max(max).default(def).meta({ title });
export const num = (min: number, max: number, def: number, title: string) => z.number().min(min).max(max).default(def).meta({ title });
export const bool = (def: boolean, title: string) => z.boolean().default(def).meta({ title });
export const choice = <T extends readonly [string, ...string[]]>(values: T, def: T[number], title: string) =>
  z.enum(values).default(def as never).meta({ title });
export const color = (title: string) => z.string().regex(/^(#[0-9A-Fa-f]{6})?$/).default('').meta({ title, format: 'color' });
export const image = (title: string) => z.object({ asset_id: z.number().int() }).nullable().default(null).meta({ title, format: 'asset:image' });
export const video = (title: string) => z.object({ asset_id: z.number().int() }).nullable().default(null).meta({ title, format: 'asset:video' });
export const list = (maxItems: number, itemMax: number, def: string[], title: string) =>
  z.array(z.string().max(itemMax)).max(maxItems).default(def).meta({ title });

export const ALL_ASPECTS: Aspect[] = ['9:16', '1:1', '16:9'];
