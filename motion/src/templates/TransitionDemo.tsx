import { TransitionSeries, springTiming, type TransitionPresentation } from '@remotion/transitions';
import React from 'react';
import { AbsoluteFill, useVideoConfig } from 'remotion';
import { theme } from '../theme';
import { useTemplate } from './ctx';
import { Backdrop } from './kit';

const Scene: React.FC<{ label: string; accent: string }> = ({ label, accent }) => {
  const { unit, theme: t } = useTemplate();
  return (
    <AbsoluteFill>
      <Backdrop accent={accent} />
      <AbsoluteFill style={{ justifyContent: 'center', alignItems: 'center', fontFamily: t.font || theme.fonts.display, fontSize: 130 * unit, fontWeight: 900, color: t.text }}>{label}</AbsoluteFill>
    </AbsoluteFill>
  );
};

/** Gallery preview for a transition: scene A → transition → scene B. */
export const TransitionDemo: React.FC<{ presentation: TransitionPresentation<Record<string, unknown>>; seconds: number }> = ({ presentation, seconds }) => {
  const { fps, durationInFrames } = useVideoConfig();
  const { theme: t } = useTemplate();
  const tf = Math.max(2, Math.round(seconds * fps));
  const a = Math.round((durationInFrames + tf) / 2);
  return (
    <TransitionSeries>
      <TransitionSeries.Sequence durationInFrames={a}>
        <Scene label="Before" accent={t.accent} />
      </TransitionSeries.Sequence>
      <TransitionSeries.Transition presentation={presentation} timing={springTiming({ config: { damping: 200 }, durationInFrames: tf, durationRestThreshold: 0.001 })} />
      <TransitionSeries.Sequence durationInFrames={durationInFrames - a + tf}>
        <Scene label="After" accent={t.accent2 || theme.colors.primary2} />
      </TransitionSeries.Sequence>
    </TransitionSeries>
  );
};
