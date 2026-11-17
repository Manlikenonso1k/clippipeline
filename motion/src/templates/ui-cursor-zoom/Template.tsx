import React from 'react';
import { AbsoluteFill, Img, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import { alpha, onColor, pick, theme } from '../../theme';
import { useTemplate } from '../ctx';
import { Backdrop, clamp, enter, useIn, useOut } from '../kit';
import type { schema } from './schema';

const FakeUi: React.FC<{ accent: string; cx: number; cy: number }> = ({ accent, cx, cy }) => (
  <AbsoluteFill style={{ backgroundColor: '#15151E', padding: '4%', gap: '4%', display: 'flex', flexDirection: 'column' }}>
    <div style={{ height: '10%', borderRadius: 12, backgroundColor: alpha('#FFFFFF', 0.07) }} />
    <div style={{ flex: 1, display: 'flex', gap: '4%' }}>
      <div style={{ width: '28%', borderRadius: 12, backgroundColor: alpha('#FFFFFF', 0.05) }} />
      <div style={{ flex: 1, borderRadius: 12, backgroundColor: alpha('#FFFFFF', 0.04) }} />
    </div>
    <div style={{ position: 'absolute', left: `${cx * 100}%`, top: `${cy * 100}%`, translate: '-50% -50%', padding: '1.2% 3%', borderRadius: 999, backgroundColor: accent, color: onColor(accent), fontWeight: 800, fontSize: '1.6vw', whiteSpace: 'nowrap' }}>Publish</div>
  </AbsoluteFill>
);

export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const { theme: t, unit, width, aspect, resolveAsset } = useTemplate();
  const accent = pick(props.accent, t.accent);
  const shot = resolveAsset(props.screenshot);
  const win = useIn(0, 0.6);
  const cap = useIn(0.3, 0.6);
  const out = useOut(0.3);
  const move = interpolate(frame, [0.5 * fps, 1.4 * fps], [0, 1], { ...clamp, easing: theme.ease.inOut });
  const click = interpolate(frame, [1.45 * fps, 1.6 * fps, 1.8 * fps], [1, 0.82, 1], { ...clamp, easing: theme.ease.out });
  const ripple = interpolate(frame, [1.5 * fps, 2.2 * fps], [0, 1], { ...clamp, easing: theme.ease.out });
  const zoom = interpolate(frame, [1.7 * fps, 2.6 * fps], [1, props.zoom], { ...clamp, easing: theme.ease.inOut });
  const w = width * (aspect === '16:9' ? 0.72 : 0.86);
  const h = w * 0.62;
  const cx = interpolate(move, [0, 1], [0.2, props.click_x]);
  const cy = interpolate(move, [0, 1], [0.85, props.click_y]);
  return (
    <AbsoluteFill>
      <Backdrop accent={accent} intensity={0.8} />
      <AbsoluteFill style={{ justifyContent: 'center', alignItems: 'center', gap: 60 * unit, opacity: out, fontFamily: t.font || theme.fonts.display }}>
        <div style={{ ...enter(win, 1, 40 * unit), width: w, height: h, borderRadius: 24 * unit, overflow: 'hidden', border: `${2 * unit}px solid ${alpha('#FFFFFF', 0.1)}`, boxShadow: `0 ${40 * unit}px ${100 * unit}px rgba(0,0,0,0.55)` }}>
          <div style={{ position: 'relative', width: '100%', height: '100%', scale: String(zoom), transformOrigin: `${props.click_x * 100}% ${props.click_y * 100}%` }}>
            {shot ? <Img src={shot.src} style={{ width: '100%', height: '100%', objectFit: 'cover' }} /> : <FakeUi accent={accent} cx={props.click_x} cy={props.click_y} />}
            <div style={{ position: 'absolute', left: `${props.click_x * 100}%`, top: `${props.click_y * 100}%`, width: 160 * unit, height: 160 * unit, translate: '-50% -50%', borderRadius: 999, border: `${4 * unit}px solid ${accent}`, opacity: (1 - ripple) * (ripple > 0 ? 1 : 0), scale: String(0.2 + ripple) }} />
            <svg width={56 * unit} height={56 * unit} viewBox="0 0 24 24" style={{ position: 'absolute', left: `${cx * 100}%`, top: `${cy * 100}%`, scale: String(click), transformOrigin: 'top left', filter: 'drop-shadow(0 4px 8px rgba(0,0,0,0.5))' }}>
              <path d="M4 2 L4 20 L9 15 L12.5 22 L15.5 20.6 L12 14 L19 14 Z" fill="#FFFFFF" stroke="#000" strokeWidth={1.2} strokeLinejoin="round" />
            </svg>
          </div>
        </div>
        {props.caption ? <div style={{ ...enter(cap, 1, 24 * unit), fontSize: 58 * unit, fontWeight: 800, color: t.text, textAlign: 'center', padding: `0 ${60 * unit}px` }}>{props.caption}</div> : null}
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
