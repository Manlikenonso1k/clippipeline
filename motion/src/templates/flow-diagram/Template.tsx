import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import { alpha, onColor, pick, theme } from '../../theme';
import { useTemplate } from '../ctx';
import { Backdrop, clamp, enter, useIn, useOut } from '../kit';
import type { schema } from './schema';

export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const { theme: t, unit, aspect } = useTemplate();
  const accent = pick(props.accent, t.accent);
  const steps = props.steps.filter(Boolean).slice(0, 5);
  const row = aspect === '16:9';
  const title = useIn(0, 0.6);
  const out = useOut(0.3);
  const per = 0.45;
  return (
    <AbsoluteFill>
      <Backdrop accent={accent} intensity={0.8} />
      <AbsoluteFill style={{ justifyContent: 'center', alignItems: 'center', gap: 60 * unit, fontFamily: t.font || theme.fonts.display, color: t.text, opacity: out }}>
        <div style={{ ...enter(title, 1, 24 * unit), fontSize: 66 * unit, fontWeight: 800 }}>{props.title}</div>
        <div style={{ display: 'flex', flexDirection: row ? 'row' : 'column', alignItems: 'center' }}>
          {steps.map((s, i) => {
            const p = interpolate(frame, [(0.3 + i * per) * fps, (0.85 + i * per) * fps], [0, 1], { ...clamp, easing: theme.ease.out });
            const link = interpolate(frame, [(0.6 + i * per) * fps, (0.95 + i * per) * fps], [0, 1], { ...clamp, easing: theme.ease.inOut });
            const last = i === steps.length - 1;
            return (
              <div key={i} style={{ display: 'flex', flexDirection: row ? 'row' : 'column', alignItems: 'center' }}>
                <div style={{ ...enter(p, 1, 30 * unit), display: 'flex', alignItems: 'center', gap: 22 * unit, padding: `${26 * unit}px ${34 * unit}px`, borderRadius: 26 * unit, backgroundColor: alpha('#FFFFFF', 0.06), border: `${2 * unit}px solid ${alpha(accent, 0.5 * p)}`, minWidth: row ? 0 : 520 * unit }}>
                  <div style={{ width: 64 * unit, height: 64 * unit, borderRadius: 999, backgroundColor: accent, color: onColor(accent), display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 34 * unit, fontWeight: 900 }}>{i + 1}</div>
                  <div style={{ fontSize: (row ? 36 : 44) * unit, fontWeight: 700 }}>{s}</div>
                </div>
                {!last ? (
                  <div style={{ width: row ? 70 * unit : 6 * unit, height: row ? 6 * unit : 56 * unit, margin: row ? `0 ${10 * unit}px` : `${10 * unit}px 0`, borderRadius: 6 * unit, background: alpha('#FFFFFF', 0.1), overflow: 'hidden' }}>
                    <div style={{ width: row ? `${link * 100}%` : '100%', height: row ? '100%' : `${link * 100}%`, background: `linear-gradient(${row ? 90 : 180}deg, ${accent}, ${t.accent2 || theme.colors.primary2})` }} />
                  </div>
                ) : null}
              </div>
            );
          })}
        </div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
