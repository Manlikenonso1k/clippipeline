import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import { alpha, pick, theme } from '../../theme';
import { useTemplate } from '../ctx';
import { Backdrop, clamp, enter, useIn, useOut } from '../kit';
import type { schema } from './schema';

export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const { theme: t, unit, aspect, width, height } = useTemplate();
  const accent = pick(props.accent, t.accent);
  const accent2 = t.accent2 || theme.colors.primary2;
  const n = Math.max(1, Math.min(6, props.labels.length));
  const values = Array.from({ length: n }, (_, i) => Math.max(0, parseFloat(props.values[i] ?? '0') || 0));
  const max = Math.max(1, ...values);
  const title = useIn(0, 0.6);
  const out = useOut(0.3);
  const chartH = (aspect === '16:9' ? height * 0.55 : height * 0.42);
  const barW = Math.min(150 * unit, (width * 0.8) / n - 24 * unit);
  return (
    <AbsoluteFill>
      <Backdrop accent={accent} intensity={0.7} />
      <AbsoluteFill style={{ justifyContent: 'center', alignItems: 'center', fontFamily: t.font || theme.fonts.display, color: t.text, opacity: out, gap: 50 * unit }}>
        <div style={{ ...enter(title, 1, 24 * unit), fontSize: 64 * unit, fontWeight: 800, textAlign: 'center', padding: `0 ${60 * unit}px` }}>{props.title}</div>
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 24 * unit, height: chartH, borderBottom: `${3 * unit}px solid ${alpha('#FFFFFF', 0.18)}` }}>
          {values.map((v, i) => {
            const p = interpolate(frame, [(0.35 + i * 0.12) * fps, (1.25 + i * 0.12) * fps], [0, 1], { ...clamp, easing: theme.ease.out });
            const h = (v / max) * (chartH - 80 * unit) * p;
            return (
              <div key={i} style={{ width: barW, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'flex-end', height: '100%' }}>
                <div style={{ fontSize: 40 * unit, fontWeight: 800, opacity: p, marginBottom: 10 * unit, fontVariantNumeric: 'tabular-nums' }}>{Math.round(v * p)}{props.unit}</div>
                <div style={{ width: '100%', height: h, borderRadius: `${16 * unit}px ${16 * unit}px 0 0`, background: `linear-gradient(180deg, ${i === values.indexOf(max) ? accent : accent2}, ${alpha(accent, 0.35)})` }} />
              </div>
            );
          })}
        </div>
        <div style={{ display: 'flex', gap: 24 * unit, marginTop: -30 * unit }}>
          {values.map((_, i) => (
            <div key={i} style={{ width: barW, textAlign: 'center', fontSize: (n > 4 ? 26 : 32) * unit, fontWeight: 600, color: theme.colors.textDim }}>{props.labels[i]}</div>
          ))}
        </div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
