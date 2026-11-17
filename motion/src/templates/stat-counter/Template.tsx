import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import { alpha, pick, theme } from '../../theme';
import { useTemplate } from '../ctx';
import { Backdrop, clamp, enter, useBreathe, useIn, useOut } from '../kit';
import type { schema } from './schema';

export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => {
  const frame = useCurrentFrame();
  const { fps, durationInFrames } = useVideoConfig();
  const { theme: t, unit } = useTemplate();
  const accent = pick(props.accent, t.accent);
  const count = interpolate(frame, [0.2 * fps, Math.min(durationInFrames * 0.7, 2 * fps)], [0, 1], { ...clamp, easing: theme.ease.out });
  const ring = useIn(0, 1.2);
  const lab = useIn(0.5, 0.6);
  const out = useOut(0.3);
  const breathe = useBreathe(5 * unit);
  const value = (props.value * count).toLocaleString('en-US', { minimumFractionDigits: props.decimals, maximumFractionDigits: props.decimals });
  const r = 330 * unit;
  const c = 2 * Math.PI * (r - 14 * unit);
  return (
    <AbsoluteFill>
      <Backdrop accent={accent} />
      <AbsoluteFill style={{ justifyContent: 'center', alignItems: 'center', opacity: out, translate: `0px ${breathe}px`, fontFamily: t.font || theme.fonts.display }}>
        <svg width={r * 2} height={r * 2} style={{ position: 'absolute', rotate: '-90deg' }}>
          <circle cx={r} cy={r} r={r - 14 * unit} stroke={alpha('#FFFFFF', 0.08)} strokeWidth={10 * unit} fill="none" />
          <circle cx={r} cy={r} r={r - 14 * unit} stroke={accent} strokeWidth={10 * unit} strokeLinecap="round" fill="none" strokeDasharray={c} strokeDashoffset={c * (1 - ring * 0.82)} />
        </svg>
        <div style={{ fontSize: (value.length > 7 ? 130 : 170) * unit, fontWeight: 900, color: t.text, letterSpacing: -4 * unit, fontVariantNumeric: 'tabular-nums', scale: String(0.9 + 0.1 * count) }}>
          <span style={{ color: accent }}>{props.prefix}</span>{value}<span style={{ color: accent }}>{props.suffix}</span>
        </div>
        <div style={{ ...enter(lab, 1, 20 * unit), fontSize: 44 * unit, fontWeight: 600, color: theme.colors.textDim, marginTop: 10 * unit, textAlign: 'center', maxWidth: r * 1.6 }}>{props.label}</div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
