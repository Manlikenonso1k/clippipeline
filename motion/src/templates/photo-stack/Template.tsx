import React from 'react';
import { AbsoluteFill, Img, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import { alpha, pick, theme } from '../../theme';
import { useTemplate } from '../ctx';
import { Backdrop, clamp, enter, rnd, useIn, useOut } from '../kit';
import type { schema } from './schema';

export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => {
  const frame = useCurrentFrame();
  const { fps, durationInFrames } = useVideoConfig();
  const { theme: t, unit, width, height, aspect, resolveAsset } = useTemplate();
  const accent = pick(props.accent, t.accent);
  const photos = [props.photo1, props.photo2, props.photo3, props.photo4].map((p) => resolveAsset(p)).filter(Boolean);
  const cards = photos.length ? photos : [null, null, null];
  const cap = useIn(0.4, 0.6);
  const out = useOut(0.3);
  const per = Math.max(0.6, (durationInFrames / fps - 0.8) / cards.length);
  // Leave room for the caption below the stack in every aspect.
  const maxH = height * (aspect === '9:16' ? 0.56 : 0.66);
  const cw = Math.min(width * (aspect === '16:9' ? 0.42 : 0.74), maxH / 1.22);
  const ch = cw * 1.22;
  const lift = props.caption ? -height * (aspect === '9:16' ? 0.05 : 0.07) : 0;
  return (
    <AbsoluteFill>
      <Backdrop accent={accent} intensity={0.7} />
      <AbsoluteFill style={{ justifyContent: 'center', alignItems: 'center', opacity: out }}>
        {cards.map((p, i) => {
          const start = (0.1 + i * per) * fps;
          const land = interpolate(frame, [start, start + 0.6 * fps], [0, 1], { ...clamp, easing: theme.ease.out });
          const kb = interpolate(frame, [start, durationInFrames], [1, 1.08], { ...clamp, easing: theme.ease.inOut });
          const rot = (rnd(`r${i}`) - 0.5) * 14;
          return (
            <div key={i} style={{ position: 'absolute', width: cw, height: ch, padding: 18 * unit, paddingBottom: 70 * unit, backgroundColor: '#F4F1EA', borderRadius: 10 * unit, boxShadow: `0 ${30 * unit}px ${70 * unit}px rgba(0,0,0,0.5)`, opacity: land, rotate: `${rot + (1 - land) * 18}deg`, translate: `${(rnd(`x${i}`) - 0.5) * 60 * unit}px ${lift + (1 - land) * height * 0.5}px`, scale: String(0.9 + 0.1 * land) }}>
              <div style={{ width: '100%', height: '100%', overflow: 'hidden', borderRadius: 4 * unit, background: `linear-gradient(${120 + i * 40}deg, ${accent}, ${alpha(t.accent2 || theme.colors.primary2, 0.6)})` }}>
                {p ? <Img src={p.src} style={{ width: '100%', height: '100%', objectFit: 'cover', scale: String(kb), translate: `${(kb - 1) * -120 * unit}px 0px` }} /> : null}
              </div>
            </div>
          );
        })}
        {props.caption ? <div style={{ ...enter(cap, 1, 20 * unit), position: 'absolute', bottom: (aspect === '9:16' ? 300 : 50) * unit, fontSize: 60 * unit, fontWeight: 800, color: t.text, fontFamily: t.font || theme.fonts.display, textShadow: '0 6px 24px rgba(0,0,0,0.6)', textAlign: 'center', padding: `0 ${60 * unit}px` }}>{props.caption}</div> : null}
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
