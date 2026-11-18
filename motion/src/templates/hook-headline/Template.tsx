import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import { pick, theme } from '../../theme';
import { useTemplate } from '../ctx';
import { accent2, displayFont, gradientText, tint } from '../fx';
import { clamp } from '../kit';
import type { schema } from './schema';

/** The opening hook: a huge gradient title with an echo outline, a kicker, then a punch word on a skewed bar. */
export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => {
  const frame = useCurrentFrame();
  const { fps, durationInFrames } = useVideoConfig();
  const ctx = useTemplate();
  const { unit, aspect } = ctx;
  const a = pick(props.accent, ctx.theme.accent);
  const b = accent2(ctx);
  const s = frame / fps;
  const end = durationInFrames / fps;
  const settle = interpolate(s, [0, 0.5], [0, 1], { ...clamp, easing: theme.ease.out });
  const out = interpolate(s, [end - 0.25, end], [1, 0], { ...clamp, easing: theme.ease.in });
  const kick = interpolate(s, [0.35, 0.8], [0, 1], { ...clamp, easing: theme.ease.out });
  const punchAt = Math.min(Math.max(0.3, props.punch_at - 0.1), end - 0.6);
  const punch = interpolate(s, [punchAt, punchAt + 0.32], [0, 1], { ...clamp, easing: theme.ease.out });
  const size = (props.title.length > 10 ? 200 : 300) * unit * (aspect === '16:9' ? 0.8 : 1);
  const font = displayFont(ctx);
  return (
    <AbsoluteFill style={{ alignItems: 'center', justifyContent: props.position === 'center' ? 'center' : 'flex-start', paddingTop: (aspect === '9:16' ? 110 : 60) * unit, gap: 6 * unit, opacity: out, translate: `0px ${(1 - out) * -80 * unit}px` }}>
      <div style={{ position: 'relative', scale: String(1.12 - 0.12 * settle), opacity: Math.min(1, settle * 2) }}>
        <div style={{ position: 'absolute', inset: 0, fontFamily: font, fontSize: size, lineHeight: 0.95, textTransform: 'uppercase', color: 'transparent', WebkitTextStroke: `${4 * unit}px ${b}`, translate: `${14 * unit}px ${14 * unit}px` }}>{props.title}</div>
        <div style={{ fontFamily: font, fontSize: size, lineHeight: 0.95, textTransform: 'uppercase', ...gradientText(tint(a, 0.35), a, b), filter: `drop-shadow(0 0 ${40 * unit}px ${a}88)` }}>{props.title}</div>
      </div>
      {props.kicker ? (
        <div style={{ fontFamily: theme.fonts.body, fontWeight: 900, fontSize: 46 * unit, letterSpacing: 7 * unit, textTransform: 'uppercase', color: '#FFFFFF', opacity: kick, translate: `0px ${(1 - kick) * 30 * unit}px` }}>{props.kicker}</div>
      ) : null}
      {props.punch ? (
        <div style={{ position: 'relative', marginTop: 14 * unit, opacity: Math.min(1, punch * 1.6), scale: String(1.6 - 0.6 * punch), filter: `blur(${(1 - punch) * 12}px)` }}>
          <div style={{ position: 'absolute', inset: `${-6 * unit}px ${-26 * unit}px`, background: `linear-gradient(90deg, ${a}, ${b})`, transform: 'skewX(-10deg)', borderRadius: 10 * unit }} />
          <div style={{ position: 'relative', fontFamily: font, fontSize: 150 * unit * (props.punch.length > 12 ? 0.75 : 1), lineHeight: 0.95, textTransform: 'uppercase', color: '#FFFFFF' }}>{props.punch}</div>
        </div>
      ) : null}
    </AbsoluteFill>
  );
};
