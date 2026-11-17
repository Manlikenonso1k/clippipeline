import React from 'react';
import { Composition, type CalculateMetadataFunction } from 'remotion';
import { outputSize, totalFrames, type Aspect, type Edl } from './edl';
import { ensureFonts } from './fonts';
import { TEMPLATES } from './templates/registry';
import { EditTimeline, type EditTimelineProps } from './timeline/EditTimeline';
import type { TemplateDef } from './templates/types';
import { DEFAULT_PROJECT_THEME } from './theme';

type TimelineInput = EditTimelineProps & { quality?: 'preview' | 'final' };

const DEMO_EDL: Edl = {
  version: 2, aspect: '9:16', fps: 30, theme: { ...DEFAULT_PROJECT_THEME, accent: '#7C5CFF' }, fit: 'blur',
  main: [
    { id: 's1', type: 'template', template: 'kinetic-title', version: 1, duration: 2.5, props: { kicker: 'CLIPPIPELINE', title: 'Studio is here', subtitle: 'Cut, style, post' } },
    { id: 's2', type: 'template', template: 'stat-counter', version: 1, duration: 3, props: { value: 4, suffix: 'x', label: 'faster edits' }, transition_in: { template: 'swipe', duration: 0.45 } },
    { id: 's3', type: 'template', template: 'cta-outro', version: 1, duration: 3, props: { handle: '@clippipeline' }, transition_in: { template: 'flash', duration: 0.3 } },
  ],
  overlays: [{ id: 'o1', template: 'lower-third', version: 1, anchor: 's2', offset: 0.3, duration: 2.4, props: { name: 'Nonso', role: 'Founder' } }],
  captions: { enabled: false, template: 'captions-pop', version: 1, props: {}, fixes: {} },
  music: [],
  loudness: -14,
};

const timelineMetadata: CalculateMetadataFunction<TimelineInput> = ({ props }) => {
  const size = outputSize(props.edl.aspect, props.quality ?? 'final');
  return { durationInFrames: totalFrames(props.edl), fps: props.edl.fps || 30, width: size.width, height: size.height };
};

type PreviewInput = { aspect: Aspect; props: Record<string, unknown>; seconds?: number; quality?: 'final' | 'template' };

/** One composition per template for gallery previews and Studio. */
function templateComposition(def: TemplateDef) {
  const Comp = def.Component;
  const Preview: React.FC<PreviewInput> = ({ props }) => {
    ensureFonts();
    const parsed = def.schema.safeParse(props ?? {});
    return <Comp props={parsed.success ? parsed.data : def.schema.parse({})} />;
  };
  const meta: CalculateMetadataFunction<PreviewInput> = ({ props }) => {
    const size = outputSize(props.aspect ?? '9:16', props.quality ?? 'final');
    return { durationInFrames: Math.round((props.seconds ?? Math.max(4, def.meta.defaultDuration)) * 30), width: size.width, height: size.height };
  };
  return (
    <Composition
      key={`${def.meta.slug}-${def.meta.version}`}
      id={`tpl-${def.meta.slug}-v${def.meta.version}`}
      component={Preview}
      durationInFrames={120}
      fps={30}
      width={1080}
      height={1920}
      defaultProps={{ aspect: '9:16' as Aspect, props: def.schema.parse({}) as Record<string, unknown> }}
      calculateMetadata={meta}
    />
  );
}

export const Root: React.FC = () => (
  <>
    <Composition
      id="EditTimeline"
      component={EditTimeline as unknown as React.FC<TimelineInput>}
      durationInFrames={300}
      fps={30}
      width={1080}
      height={1920}
      defaultProps={{ edl: DEMO_EDL, assets: {}, transcripts: {}, quality: 'final' } as TimelineInput}
      calculateMetadata={timelineMetadata}
    />
    {TEMPLATES.map(templateComposition)}
  </>
);
