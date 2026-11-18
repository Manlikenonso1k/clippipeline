// Every template, keyed by slug@version. Old versions stay here so existing projects keep rendering.
import type { TemplateDef } from './types';
import { meta as kineticTitleMeta } from './kinetic-title/meta';
import { schema as kineticTitleSchema } from './kinetic-title/schema';
import { Template as kineticTitleTemplate } from './kinetic-title/Template';
import { meta as captionsPopMeta } from './captions-pop/meta';
import { schema as captionsPopSchema } from './captions-pop/schema';
import { Template as captionsPopTemplate } from './captions-pop/Template';
import { meta as lowerThirdMeta } from './lower-third/meta';
import { schema as lowerThirdSchema } from './lower-third/schema';
import { Template as lowerThirdTemplate } from './lower-third/Template';
import { meta as ctaOutroMeta } from './cta-outro/meta';
import { schema as ctaOutroSchema } from './cta-outro/schema';
import { Template as ctaOutroTemplate } from './cta-outro/Template';
import { meta as captionsCleanMeta } from './captions-clean/meta';
import { schema as captionsCleanSchema } from './captions-clean/schema';
import { Template as captionsCleanTemplate } from './captions-clean/Template';
import { meta as statCounterMeta } from './stat-counter/meta';
import { schema as statCounterSchema } from './stat-counter/schema';
import { Template as statCounterTemplate } from './stat-counter/Template';
import { meta as barChartMeta } from './bar-chart/meta';
import { schema as barChartSchema } from './bar-chart/schema';
import { Template as barChartTemplate } from './bar-chart/Template';
import { meta as flowDiagramMeta } from './flow-diagram/meta';
import { schema as flowDiagramSchema } from './flow-diagram/schema';
import { Template as flowDiagramTemplate } from './flow-diagram/Template';
import { meta as phoneMockupMeta } from './phone-mockup/meta';
import { schema as phoneMockupSchema } from './phone-mockup/schema';
import { Template as phoneMockupTemplate } from './phone-mockup/Template';
import { meta as uiCursorZoomMeta } from './ui-cursor-zoom/meta';
import { schema as uiCursorZoomSchema } from './ui-cursor-zoom/schema';
import { Template as uiCursorZoomTemplate } from './ui-cursor-zoom/Template';
import { meta as photoStackMeta } from './photo-stack/meta';
import { schema as photoStackSchema } from './photo-stack/schema';
import { Template as photoStackTemplate } from './photo-stack/Template';
import { meta as audioBarsMeta } from './audio-bars/meta';
import { schema as audioBarsSchema } from './audio-bars/schema';
import { Template as audioBarsTemplate } from './audio-bars/Template';
import { meta as codeTypingMeta } from './code-typing/meta';
import { schema as codeTypingSchema } from './code-typing/schema';
import { Template as codeTypingTemplate } from './code-typing/Template';
import { meta as particleBurstMeta } from './particle-burst/meta';
import { schema as particleBurstSchema } from './particle-burst/schema';
import { Template as particleBurstTemplate, presentation as particleBurstPresentation } from './particle-burst/Template';
import { meta as swipeMeta } from './swipe/meta';
import { schema as swipeSchema } from './swipe/schema';
import { Template as swipeTemplate, presentation as swipePresentation } from './swipe/Template';
import { meta as zoomBlurMeta } from './zoom-blur/meta';
import { schema as zoomBlurSchema } from './zoom-blur/schema';
import { Template as zoomBlurTemplate, presentation as zoomBlurPresentation } from './zoom-blur/Template';
import { meta as flashMeta } from './flash/meta';
import { schema as flashSchema } from './flash/schema';
import { Template as flashTemplate, presentation as flashPresentation } from './flash/Template';
import { meta as hookHeadlineMeta } from './hook-headline/meta';
import { schema as hookHeadlineSchema } from './hook-headline/schema';
import { Template as hookHeadlineTemplate } from './hook-headline/Template';
import { meta as popHeadlineMeta } from './pop-headline/meta';
import { schema as popHeadlineSchema } from './pop-headline/schema';
import { Template as popHeadlineTemplate } from './pop-headline/Template';
import { meta as moneyCounterMeta } from './money-counter/meta';
import { schema as moneyCounterSchema } from './money-counter/schema';
import { Template as moneyCounterTemplate } from './money-counter/Template';
import { meta as featurePillsMeta } from './feature-pills/meta';
import { schema as featurePillsSchema } from './feature-pills/schema';
import { Template as featurePillsTemplate } from './feature-pills/Template';
import { meta as captionsBoldMeta } from './captions-bold/meta';
import { schema as captionsBoldSchema } from './captions-bold/schema';
import { Template as captionsBoldTemplate } from './captions-bold/Template';
import { meta as neonOutroMeta } from './neon-outro/meta';
import { schema as neonOutroSchema } from './neon-outro/schema';
import { Template as neonOutroTemplate } from './neon-outro/Template';

export const TEMPLATES: TemplateDef[] = [
  { meta: kineticTitleMeta, schema: kineticTitleSchema, Component: kineticTitleTemplate as TemplateDef['Component'] },
  { meta: captionsPopMeta, schema: captionsPopSchema, Component: captionsPopTemplate as TemplateDef['Component'] },
  { meta: lowerThirdMeta, schema: lowerThirdSchema, Component: lowerThirdTemplate as TemplateDef['Component'] },
  { meta: ctaOutroMeta, schema: ctaOutroSchema, Component: ctaOutroTemplate as TemplateDef['Component'] },
  { meta: captionsCleanMeta, schema: captionsCleanSchema, Component: captionsCleanTemplate as TemplateDef['Component'] },
  { meta: statCounterMeta, schema: statCounterSchema, Component: statCounterTemplate as TemplateDef['Component'] },
  { meta: barChartMeta, schema: barChartSchema, Component: barChartTemplate as TemplateDef['Component'] },
  { meta: flowDiagramMeta, schema: flowDiagramSchema, Component: flowDiagramTemplate as TemplateDef['Component'] },
  { meta: phoneMockupMeta, schema: phoneMockupSchema, Component: phoneMockupTemplate as TemplateDef['Component'] },
  { meta: uiCursorZoomMeta, schema: uiCursorZoomSchema, Component: uiCursorZoomTemplate as TemplateDef['Component'] },
  { meta: photoStackMeta, schema: photoStackSchema, Component: photoStackTemplate as TemplateDef['Component'] },
  { meta: audioBarsMeta, schema: audioBarsSchema, Component: audioBarsTemplate as TemplateDef['Component'] },
  { meta: codeTypingMeta, schema: codeTypingSchema, Component: codeTypingTemplate as TemplateDef['Component'] },
  { meta: particleBurstMeta, schema: particleBurstSchema, Component: particleBurstTemplate as TemplateDef['Component'], presentation: particleBurstPresentation as TemplateDef['presentation'] },
  { meta: swipeMeta, schema: swipeSchema, Component: swipeTemplate as TemplateDef['Component'], presentation: swipePresentation as TemplateDef['presentation'] },
  { meta: zoomBlurMeta, schema: zoomBlurSchema, Component: zoomBlurTemplate as TemplateDef['Component'], presentation: zoomBlurPresentation as TemplateDef['presentation'] },
  { meta: flashMeta, schema: flashSchema, Component: flashTemplate as TemplateDef['Component'], presentation: flashPresentation as TemplateDef['presentation'] },
  { meta: hookHeadlineMeta, schema: hookHeadlineSchema, Component: hookHeadlineTemplate as TemplateDef['Component'] },
  { meta: popHeadlineMeta, schema: popHeadlineSchema, Component: popHeadlineTemplate as TemplateDef['Component'] },
  { meta: moneyCounterMeta, schema: moneyCounterSchema, Component: moneyCounterTemplate as TemplateDef['Component'] },
  { meta: featurePillsMeta, schema: featurePillsSchema, Component: featurePillsTemplate as TemplateDef['Component'] },
  { meta: captionsBoldMeta, schema: captionsBoldSchema, Component: captionsBoldTemplate as TemplateDef['Component'] },
  { meta: neonOutroMeta, schema: neonOutroSchema, Component: neonOutroTemplate as TemplateDef['Component'] },
];

const BY_KEY = new Map(TEMPLATES.map((t) => [`${t.meta.slug}@${t.meta.version}`, t]));

/** Exact version, else the newest version of that slug. */
export function getTemplate(slug: string, version?: number | null): TemplateDef | undefined {
  if (version != null) {
    const exact = BY_KEY.get(`${slug}@${version}`);
    if (exact) return exact;
  }
  return TEMPLATES.filter((t) => t.meta.slug === slug).sort((a, b) => b.meta.version - a.meta.version)[0];
}

/** PHP encodes an empty props object as []: treat any non-object as {}. */
export function normalizeProps(props: unknown): Record<string, unknown> {
  return props && typeof props === 'object' && !Array.isArray(props) ? (props as Record<string, unknown>) : {};
}

export type PropsCheck = { ok: true; props: Record<string, unknown> } | { ok: false; error: string };

/** Validate (and default) props with the template's zod schema. */
export function checkProps(slug: string, version: number | undefined | null, props: unknown): PropsCheck {
  const t = getTemplate(slug, version);
  if (!t) return { ok: false, error: `Unknown template "${slug}"` };
  const res = t.schema.safeParse(normalizeProps(props));
  if (!res.success) {
    const issue = res.error.issues[0];
    return { ok: false, error: `Template "${slug}" prop "${issue?.path.join('.')}": ${issue?.message}` };
  }
  return { ok: true, props: res.data as Record<string, unknown> };
}
