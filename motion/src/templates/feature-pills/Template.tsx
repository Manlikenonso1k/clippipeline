import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import { pick, theme } from '../../theme';
import { useTemplate } from '../ctx';
import { accent2, displayFont, gradientText, tint } from '../fx';
import { clamp } from '../kit';
import type { schema } from './schema';

/** A title plus pills that drop in one by one (features, steps, list items). */
export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => {
  const frame = useCurrentFrame();
  const { fps, durationInFrames } = useVideoConfig();
  const ctx = useTemplate();
  const { unit, aspect, width } = ctx;
  const a = pick(props.accent, ctx.theme.accent);
  const b = accent2(ctx);
  const s = frame / fps;
  const end = durationInFrames / fps;
  const out = interpolate(s, [end - 0.22, end], [1, 0], { ...clamp, easing: theme.ease.in });
  const t = interpolate(s, [0, 0.45], [0, 1], { ...clamp, easing: theme.ease.out });
  const items = props.items.filter(Boolean).slice(0, 6);
  const step = Math.min(0.3, Math.max(0.12, (end - 1) / Math.max(1, items.length)));
  return (
    <AbsoluteFill style={{ alignItems: 'center', justifyContent: 'flex-start', paddingTop: (aspect === '9:16' ? 110 : 60) * unit, gap: 18 * unit, opacity: out }}>
      {props.title ? (
        <div style={{ fontFamily: displayFont(ctx), fontSize: 110 * unit, textTransform: 'uppercase', color: '#FFFFFF', opacity: t, translate: `0px ${(1 - t) * 40 * unit}px`, textShadow: `0 ${6 * unit}px 0 rgba(0,0,0,0.5)` }}>
          {props.title.split(' ').slice(0, -1).join(' ')} <span style={gradientText(tint(a, 0.35), a, b)}>{props.title.split(' ').slice(-1)[0]}</span>
        </div>
      ) : null}
      <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'center', gap: 16 * unit, maxWidth: width * 0.92 }}>
        {items.map((label, i) => {
          const k = interpolate(s, [0.35 + i * step, 0.75 + i * step], [0, 1], { ...clamp, easing: theme.ease.out });
          const solid = i % 3 === 2;
          return (
            <div key={i} style={{ opacity: k, translate: `0px ${(1 - k) * 50 * unit}px`, scale: String(0.9 + 0.1 * k), padding: `${16 * unit}px ${30 * unit}px`, borderRadius: 999, background: solid ? `linear-gradient(90deg, ${a}, ${b})` : 'rgba(14,4,24,0.72)', border: solid ? 'none' : `${3 * unit}px solid ${a}`, color: '#FFFFFF', fontFamily: theme.fonts.body, fontWeight: 900, fontSize: 36 * unit, letterSpacing: 4 * unit, textTransform: 'uppercase', boxShadow: solid ? `0 ${12 * unit}px ${40 * unit}px ${a}66` : 'none' }}>
              {label}
            </div>
          );
        })}
      </div>
    </AbsoluteFill>
  );
};
