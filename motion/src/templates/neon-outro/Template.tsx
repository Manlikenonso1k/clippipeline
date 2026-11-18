import React from 'react';
import { AbsoluteFill, Img, interpolate, random, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import { pick, theme } from '../../theme';
import { useTemplate } from '../ctx';
import { accent2, displayFont, gradientText, tint } from '../fx';
import { clamp } from '../kit';
import type { schema } from './schema';

/** End card on a neon horizon: logo, title, icon row, growth chart, pulsing button. */
export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const ctx = useTemplate();
  const { unit, width, height, aspect, resolveAsset } = ctx;
  const a = pick(props.accent, ctx.theme.accent);
  const b = accent2(ctx);
  const t = frame / fps;
  const at = (x: number, d = 0.5) => interpolate(t, [x, x + d], [0, 1], { ...clamp, easing: theme.ease.out });
  const logo = resolveAsset(props.logo);
  const chart = interpolate(t, [1.1, 2.3], [0, 1], { ...clamp, easing: theme.ease.inOut });
  const pts = [0.08, 0.18, 0.14, 0.32, 0.28, 0.5, 0.62, 0.58, 0.82, 0.95];
  const path = pts.map((v, i) => `${i === 0 ? 'M' : 'L'} ${(i / (pts.length - 1)) * 600} ${200 - v * 180}`).join(' ');
  const pulse = 1 + Math.sin(t * Math.PI * 2 * 1.1) * 0.035 * at(1.9, 0.4);
  const horizon = aspect === '16:9' ? 0.78 : 0.72;
  const font = displayFont(ctx);
  const enter = (x: number): React.CSSProperties => ({ opacity: at(x), translate: `0px ${(1 - at(x)) * 50 * unit}px` });
  return (
    <AbsoluteFill style={{ overflow: 'hidden' }}>
      <AbsoluteFill style={{ background: `linear-gradient(180deg, ${ctx.theme.bg} 0%, ${tint(ctx.theme.bg, 0.08)} 40%, ${b}AA 72%, ${a} 100%)` }} />
      <AbsoluteFill style={{ background: `radial-gradient(circle at 50% ${horizon * 100 - 6}%, ${a}66 0%, transparent 38%)` }} />
      <svg width={width} height={height * (1 - horizon)} style={{ position: 'absolute', left: 0, top: height * horizon }} viewBox={`0 0 ${width} ${height * (1 - horizon)}`}>
        {Array.from({ length: 12 }, (_, i) => {
          const k = ((i + ((t * 1.2) % 1)) / 12) ** 2.2;
          const y = k * height * (1 - horizon);
          return <line key={`h${i}`} x1={0} x2={width} y1={y} y2={y} stroke={tint(a, 0.3)} strokeOpacity={0.25 + 0.6 * k} strokeWidth={(2 + 3 * k) * unit} />;
        })}
        {Array.from({ length: 17 }, (_, i) => {
          const x = (i / 16) * width;
          return <line key={`v${i}`} x1={width / 2 + (x - width / 2) * 0.08} y1={0} x2={width / 2 + (x - width / 2) * 2.4} y2={height * (1 - horizon)} stroke={tint(a, 0.3)} strokeOpacity={0.55} strokeWidth={2.5 * unit} />;
        })}
      </svg>
      <div style={{ position: 'absolute', left: 0, right: 0, top: height * horizon - 3 * unit, height: 6 * unit, background: tint(a, 0.3), boxShadow: `0 0 ${40 * unit}px ${10 * unit}px ${a}` }} />
      {Array.from({ length: 36 }, (_, i) => (
        <div key={i} style={{ position: 'absolute', left: `${random(`sx${i}`) * 100}%`, top: `${random(`sy${i}`) * horizon * 90}%`, width: 4 * unit, height: 4 * unit, borderRadius: 2, background: '#FFFFFF', opacity: 0.2 + 0.45 * Math.abs(Math.sin(t * 2 + i)) }} />
      ))}

      <AbsoluteFill style={{ alignItems: 'center', justifyContent: 'center', paddingBottom: 140 * unit, gap: 26 * unit }}>
        <div style={{ width: 260 * unit, height: 260 * unit, borderRadius: 64 * unit, overflow: 'hidden', scale: String(at(0.15, 0.5)), rotate: `${(1 - at(0.15, 0.5)) * 12}deg`, background: logo ? 'transparent' : `linear-gradient(135deg, ${a}, ${b})`, boxShadow: `0 ${20 * unit}px ${60 * unit}px ${a}AA`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          {logo ? <Img src={logo.src} style={{ width: '100%', height: '100%', objectFit: 'cover' }} /> : <div style={{ fontFamily: font, fontSize: 150 * unit, color: '#FFFFFF' }}>{(props.title[0] || '★').toUpperCase()}</div>}
        </div>
        <div style={{ ...enter(0.4), fontFamily: font, fontSize: (props.title.length > 14 ? 120 : 160) * unit, lineHeight: 1, textTransform: 'uppercase', ...gradientText(tint(a, 0.35), a, b), filter: `drop-shadow(0 ${6 * unit}px 0 ${ctx.theme.bg})` }}>{props.title}</div>
        {props.subtitle ? <div style={{ ...enter(0.55), fontFamily: theme.fonts.body, fontWeight: 900, fontSize: 40 * unit, letterSpacing: 14 * unit, textTransform: 'uppercase', color: '#FFFFFF' }}>{props.subtitle}</div> : null}
        {props.items.filter(Boolean).length ? (
          <div style={{ display: 'flex', gap: 18 * unit, flexWrap: 'wrap', justifyContent: 'center' }}>
            {props.items.filter(Boolean).slice(0, 5).map((label, i) => (
              <div key={i} style={{ ...enter(0.7 + i * 0.1), padding: `${12 * unit}px ${24 * unit}px`, borderRadius: 999, background: i === 0 ? `linear-gradient(90deg, ${a}, ${b})` : 'rgba(255,255,255,0.08)', border: `${2 * unit}px solid ${a}66`, fontFamily: theme.fonts.body, fontWeight: 800, fontSize: 30 * unit, color: '#FFFFFF', textTransform: 'uppercase', letterSpacing: 2 * unit }}>{label}</div>
            ))}
          </div>
        ) : null}
        {props.show_chart ? (
          <div style={{ ...enter(1.0), width: 820 * unit, padding: `${26 * unit}px ${36 * unit}px`, borderRadius: 30 * unit, background: 'rgba(14,4,24,0.7)', border: `${2 * unit}px solid ${b}88` }}>
            <svg width={748 * unit} height={210 * unit} viewBox="0 0 600 210" style={{ overflow: 'visible' }}>
              <defs>
                <linearGradient id="neon-outro-line" x1="0" x2="1">
                  <stop offset="0" stopColor={b} />
                  <stop offset="1" stopColor={a} />
                </linearGradient>
              </defs>
              <path d={path} fill="none" stroke="url(#neon-outro-line)" strokeWidth={9} strokeLinecap="round" strokeLinejoin="round" pathLength={1} strokeDasharray={1} strokeDashoffset={1 - chart} />
              {chart > 0.98 ? <circle cx={600} cy={200 - 0.95 * 180} r={13} fill={a} /> : null}
            </svg>
          </div>
        ) : null}
        {props.cta ? (
          <div style={{ marginTop: 14 * unit, scale: String(at(1.7, 0.4) * pulse), padding: `${26 * unit}px ${66 * unit}px`, borderRadius: 999, background: `linear-gradient(90deg, ${a}, ${b})`, color: '#FFFFFF', fontFamily: theme.fonts.body, fontWeight: 900, fontSize: 54 * unit, letterSpacing: 4 * unit, textTransform: 'uppercase', boxShadow: `0 ${12 * unit}px ${40 * unit}px ${a}66` }}>{props.cta}</div>
        ) : null}
        {props.note ? <div style={{ fontFamily: theme.fonts.body, fontWeight: 800, fontSize: 40 * unit, color: '#FFFFFF', opacity: at(2.2, 0.4), textShadow: `0 ${4 * unit}px ${20 * unit}px ${ctx.theme.bg}`, textAlign: 'center', padding: `0 ${60 * unit}px` }}>{props.note}</div> : null}
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
