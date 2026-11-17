import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import { pick, theme } from '../../theme';
import { useTemplate } from '../ctx';
import { Backdrop, clamp, enter, useBreathe, useIn, useOut } from '../kit';
import type { schema } from './schema';

const Word: React.FC<{ word: string; i: number; slam: boolean; size: number }> = ({ word, i, slam, size }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const start = (0.25 + i * 0.09) * fps;
  const p = interpolate(frame, [start, start + 0.55 * fps], [0, 1], { ...clamp, easing: theme.ease.out });
  return (
    <span style={{ display: 'inline-block', marginRight: size * 0.22, opacity: p,
      translate: slam ? `0px 0px` : `0px ${(1 - p) * size * 0.6}px`,
      scale: slam ? String(1.6 - 0.6 * p) : String(0.9 + 0.1 * p),
      filter: `blur(${(1 - p) * 10}px)` }}>
      {word}
    </span>
  );
};

export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => {
  const { theme: t, unit, aspect } = useTemplate();
  const accent = pick(props.accent, t.accent);
  const out = useOut(0.35);
  const kick = useIn(0.05, 0.6);
  const bar = useIn(0.45, 0.8);
  const sub = useIn(0.6, 0.7);
  const drift = useBreathe(4 * unit);
  const words = props.title.split(/\s+/).filter(Boolean);
  const size = (aspect === '16:9' ? 120 : aspect === '1:1' ? 104 : 112) * unit * (props.title.length > 28 ? 0.78 : 1);
  const left = props.align === 'left';
  return (
    <AbsoluteFill>
      <Backdrop accent={accent} />
      <AbsoluteFill style={{ justifyContent: 'center', alignItems: left ? 'flex-start' : 'center', padding: `0 ${90 * unit}px`, fontFamily: t.font || theme.fonts.display, color: t.text, opacity: out, translate: `0px ${drift}px` }}>
        {props.kicker ? (
          <div style={{ ...enter(kick, 1, 30 * unit), fontSize: 34 * unit, fontWeight: 700, letterSpacing: 8 * unit, color: accent, textTransform: 'uppercase', marginBottom: 26 * unit }}>{props.kicker}</div>
        ) : null}
        <div style={{ fontSize: size, fontWeight: 900, lineHeight: 1.02, letterSpacing: -2 * unit, textAlign: left ? 'left' : 'center', maxWidth: '100%' }}>
          {words.map((w, i) => <Word key={i} word={w} i={i} slam={props.style === 'slam'} size={size} />)}
        </div>
        <div style={{ height: 10 * unit, width: 220 * unit * bar, borderRadius: 10 * unit, marginTop: 34 * unit, background: `linear-gradient(90deg, ${accent}, ${t.accent2 || theme.colors.primary2})` }} />
        {props.subtitle ? (
          <div style={{ ...enter(sub, 1, 24 * unit), fontSize: 40 * unit, fontWeight: 600, color: theme.colors.textDim, marginTop: 30 * unit, textAlign: left ? 'left' : 'center' }}>{props.subtitle}</div>
        ) : null}
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
