import React from 'react';
import { AbsoluteFill, Img, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import { alpha, onColor, pick, theme } from '../../theme';
import { useTemplate } from '../ctx';
import { Backdrop, enter, useIn, useOut, useSpring } from '../kit';
import type { schema } from './schema';

export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => {
  const { theme: t, unit, resolveAsset } = useTemplate();
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const accent = pick(props.accent, t.accent);
  const logo = resolveAsset(props.logo);
  const pop = useSpring(0.05, 'bouncy');
  const handleIn = useIn(0.35, 0.6);
  const btn = useSpring(0.6, 'snappy');
  const out = useOut(0.3);
  const typed = Math.max(0, Math.floor((frame / fps - 0.35) * 26));
  const pulse = 1 + Math.sin((frame / fps) * Math.PI * 2 * 0.9) * 0.03 * btn;
  const size = 220 * unit;
  return (
    <AbsoluteFill>
      <Backdrop accent={accent} />
      <AbsoluteFill style={{ justifyContent: 'center', alignItems: 'center', gap: 40 * unit, fontFamily: t.font || theme.fonts.display, opacity: out }}>
        <div style={{ width: size, height: size, borderRadius: size * 0.28, overflow: 'hidden', scale: String(pop), rotate: `${(1 - pop) * -12}deg`, background: logo ? alpha('#FFFFFF', 0.06) : `linear-gradient(135deg, ${accent}, ${t.accent2 || theme.colors.primary2})`, boxShadow: `0 ${30 * unit}px ${80 * unit}px ${alpha(accent, 0.45)}`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          {logo ? <Img src={logo.src} style={{ width: '100%', height: '100%', objectFit: 'cover' }} /> : <div style={{ fontSize: size * 0.42, fontWeight: 900, color: onColor(accent) }}>{(props.handle.replace('@', '')[0] || '★').toUpperCase()}</div>}
        </div>
        <div style={{ ...enter(handleIn, 1, 24 * unit), fontSize: 64 * unit, fontWeight: 800, color: t.text, letterSpacing: -1 * unit }}>
          {props.handle.slice(0, typed)}
          <span style={{ opacity: typed < props.handle.length ? 1 : 0, color: accent }}>|</span>
        </div>
        <div style={{ scale: String(btn * pulse), padding: `${24 * unit}px ${54 * unit}px`, borderRadius: 999, backgroundColor: accent, color: onColor(accent), fontSize: 40 * unit, fontWeight: 800 }}>{props.cta}</div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
