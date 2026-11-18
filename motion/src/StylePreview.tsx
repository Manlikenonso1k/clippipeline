import React from 'react';
import { AbsoluteFill, Sequence, useVideoConfig } from 'remotion';
import { ensureFonts } from './fonts';
import { STYLES } from './styles';
import { TemplateProvider } from './templates/ctx';
import { getTemplate } from './templates/registry';
import { DEFAULT_PROJECT_THEME, alpha } from './theme';

/** Poster for a style pack in the app: its colours, hook, a headline and captions over a stand-in "speaker". */
export const StylePreview: React.FC<{ style: string }> = ({ style }) => {
  ensureFonts();
  const { width, height } = useVideoConfig();
  const st = STYLES.find((s) => s.slug === style) ?? STYLES[0];
  const t = { ...DEFAULT_PROJECT_THEME, ...st.theme };
  const ctx = { theme: t, aspect: '9:16' as const, width, height, unit: Math.min(width, height) / 1080, resolveAsset: () => null, words: [], musicSrc: null, demo: true };
  const Hook = getTemplate(st.hook?.template ?? 'hook-headline')!;
  const Caps = getTemplate(st.captions.template)!;
  return (
    <TemplateProvider value={ctx}>
      <AbsoluteFill style={{ background: `radial-gradient(circle at 50% 62%, ${alpha(t.accent2 || t.accent, 0.55)} 0%, ${t.bg} 70%)` }}>
        {/* stand-in speaker silhouette */}
        <div style={{ position: 'absolute', left: '50%', top: '38%', width: 420, height: 520, translate: '-50% 0', borderRadius: '50% 50% 45% 45%', background: alpha('#000000', 0.55) }} />
        <div style={{ position: 'absolute', left: '50%', top: '62%', width: 900, height: 900, translate: '-50% 0', borderRadius: '50%', background: alpha('#000000', 0.55) }} />
        <AbsoluteFill style={{ background: `linear-gradient(180deg, ${t.bg}E6 0%, transparent 42%, transparent 62%, ${t.bg}E6 100%)` }} />
        <Sequence from={0} durationInFrames={150}>
          <Hook.Component props={Hook.schema.parse({ title: 'Go viral', kicker: '', punch: 'Today', punch_at: 0.6, accent: '' })} />
        </Sequence>
        <Sequence from={0} durationInFrames={150}>
          <Caps.Component props={Caps.schema.parse(st.captions.props ?? {})} />
        </Sequence>
      </AbsoluteFill>
    </TemplateProvider>
  );
};
