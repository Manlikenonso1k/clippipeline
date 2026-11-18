import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import { pick, theme } from '../../theme';
import { useTemplate } from '../ctx';
import { displayFont, tint } from '../fx';
import { clamp } from '../kit';
import type { schema } from './schema';

/** A money/number count-up, glowing in the accent colour. */
export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => {
  const frame = useCurrentFrame();
  const { fps, durationInFrames } = useVideoConfig();
  const ctx = useTemplate();
  const { unit, aspect } = ctx;
  const a = pick(props.accent, ctx.theme.accent);
  const s = frame / fps;
  const end = durationInFrames / fps;
  const p = interpolate(s, [0, Math.min(1.1, end * 0.6)], [0, 1], { ...clamp, easing: theme.ease.out });
  const show = interpolate(s, [0, 0.15], [0, 1], clamp) * interpolate(s, [end - 0.2, end], [1, 0], { ...clamp, easing: theme.ease.in });
  const value = Math.round(props.value * p).toLocaleString('en-US');
  return (
    <AbsoluteFill style={{ alignItems: 'center', justifyContent: 'flex-start', paddingTop: (aspect === '9:16' ? 520 : 360) * unit, opacity: show }}>
      <div style={{ fontFamily: displayFont(ctx), fontSize: 120 * unit, color: tint(a, 0.25), fontVariantNumeric: 'tabular-nums', textShadow: `0 0 ${30 * unit}px ${a}, 0 ${6 * unit}px 0 rgba(0,0,0,0.6)`, scale: String(0.85 + 0.15 * p) }}>
        {props.prefix}
        {value}
      </div>
      {props.label ? <div style={{ fontFamily: theme.fonts.body, fontWeight: 800, fontSize: 38 * unit, color: '#FFFFFF', letterSpacing: 4 * unit, textTransform: 'uppercase' }}>{props.label}</div> : null}
    </AbsoluteFill>
  );
};
