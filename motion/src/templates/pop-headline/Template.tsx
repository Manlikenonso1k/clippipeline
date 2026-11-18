import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import { pick, theme } from '../../theme';
import { useTemplate } from '../ctx';
import { accent2, displayFont, displayScale, displayWeight, gradientText, tint } from '../fx';
import { clamp } from '../kit';
import type { schema } from './schema';

/** A short headline that pops in when a key word is spoken (placed by "smart headlines"). */
export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => {
  const frame = useCurrentFrame();
  const { fps, durationInFrames } = useVideoConfig();
  const ctx = useTemplate();
  const { unit, aspect } = ctx;
  const a = pick(props.accent, ctx.theme.accent);
  const b = accent2(ctx);
  const s = frame / fps;
  const end = durationInFrames / fps;
  const out = interpolate(s, [end - 0.2, end], [1, 0], { ...clamp, easing: theme.ease.in });
  const p = interpolate(s, [0, props.style === 'slam' ? 0.3 : 0.45], [0, 1], { ...clamp, easing: theme.ease.out });
  const font = displayFont(ctx);
  const words = props.text.split(/\s+/).filter(Boolean);
  const hl = props.highlight.trim().toLowerCase();
  const long = props.text.length > 14;
  const size = (props.style === 'slam' ? (long ? 170 : 280) : long ? 130 : 170) * unit * (aspect === '16:9' ? 0.8 : 1) * displayScale(ctx);

  const base: React.CSSProperties = { fontFamily: font, fontWeight: displayWeight(ctx), fontSize: size, lineHeight: 0.98, textTransform: 'uppercase', color: '#FFFFFF', textAlign: 'center' };
  const motion: React.CSSProperties =
    props.style === 'slam'
      ? { scale: String(2 - p), opacity: Math.min(1, p * 1.6) * out, filter: `blur(${(1 - p) * 12}px)` }
      : { translate: `0px ${(1 - p) * 70 * unit - (1 - out) * 50 * unit}px`, scale: String(0.9 + 0.1 * p), opacity: Math.min(p, out) };

  return (
    <AbsoluteFill style={{ alignItems: 'center', justifyContent: props.position === 'center' ? 'center' : 'flex-start', paddingTop: (aspect === '9:16' ? 150 : 70) * unit, padding: `${(aspect === '9:16' ? 150 : 70) * unit}px ${60 * unit}px 0` }}>
      <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'center', columnGap: 26 * unit, rowGap: 10 * unit, maxWidth: '100%', ...motion }}>
        {words.map((w, i) => {
          const isHl = hl !== '' && w.toLowerCase().replace(/[^a-z0-9$]/g, '') === hl.replace(/[^a-z0-9$]/g, '');
          const k = interpolate(s, [0.08 * i, 0.08 * i + 0.4], [0, 1], { ...clamp, easing: theme.ease.out });
          if (props.style === 'gradient' || (props.style === 'slam' && isHl)) {
            return <span key={i} style={{ ...base, ...gradientText(tint(a, 0.35), a, b), filter: `drop-shadow(0 0 ${36 * unit}px ${a}AA)` }}>{w}</span>;
          }
          if (props.style === 'echo') {
            return (
              <span key={i} style={{ position: 'relative', display: 'inline-block' }}>
                {[2, 1].map((l) => (
                  <span key={l} style={{ ...base, position: 'absolute', inset: 0, color: 'transparent', WebkitTextStroke: `${3 * unit}px ${l === 1 ? a : b}`, translate: `${l * 18 * unit * k}px ${l * 8 * unit}px`, opacity: 0.7 }}>{w}</span>
                ))}
                <span style={{ ...base, position: 'relative', ...(isHl ? gradientText(tint(a, 0.35), a, b) : {}) }}>{w}</span>
              </span>
            );
          }
          if (isHl) {
            return <span key={i} style={{ ...base, background: `linear-gradient(90deg, ${a}, ${b})`, padding: `${6 * unit}px ${22 * unit}px 0`, borderRadius: 16 * unit, rotate: '-4deg', boxShadow: `0 ${18 * unit}px ${50 * unit}px ${a}88`, scale: String(0.6 + 0.4 * k) }}>{w}</span>;
          }
          return <span key={i} style={{ ...base, textShadow: `0 ${8 * unit}px ${30 * unit}px rgba(0,0,0,0.6)` }}>{w}</span>;
        })}
      </div>
    </AbsoluteFill>
  );
};
