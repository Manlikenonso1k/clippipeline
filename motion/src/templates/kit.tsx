// Shared motion helpers for templates. The 10 rules of remotion-motion-graphics apply:
// eased (never linear) multi-property staggered entrances, faster exits, a layered
// background, Ken Burns on stills, breathing idle motion, timing from fps.
import React from 'react';
import { AbsoluteFill, interpolate, random, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { alpha, theme } from '../theme';
import { useTemplate } from './ctx';

const clamp = { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' } as const;

/** 0→1 entrance progress, starting at `delay` seconds, lasting `dur` seconds (ease-out expo). */
export function useIn(delay = 0, dur = 0.7): number {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  return interpolate(frame, [delay * fps, (delay + dur) * fps], [0, 1], { ...clamp, easing: theme.ease.out });
}

/** 1→0 exit progress over the last `dur` seconds of the sequence (ease-in, faster than entrances). */
export function useOut(dur = 0.35): number {
  const frame = useCurrentFrame();
  const { fps, durationInFrames } = useVideoConfig();
  return interpolate(frame, [durationInFrames - dur * fps, durationInFrames - 1], [1, 0], { ...clamp, easing: theme.ease.in });
}

/** Spring 0→1 from `delay` seconds. */
export function useSpring(delay = 0, config: keyof typeof theme.spring = 'snappy'): number {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  return spring({ frame: frame - delay * fps, fps, config: theme.spring[config] });
}

/** Opacity + rise + scale for an entrance progress p and exit factor x. */
export function enter(p: number, x = 1, rise = 40): React.CSSProperties {
  return {
    opacity: p * x,
    translate: `0px ${(1 - p) * rise + (1 - x) * -rise * 0.5}px`,
    scale: String(0.94 + 0.06 * p),
  };
}

/** Gentle sin-wave idle motion (px). */
export function useBreathe(amp = 6, period = 3.2): number {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  return Math.sin((frame / fps / period) * Math.PI * 2) * amp;
}

/** Five-layer background: base, two drifting gradient blobs, grid, grain + vignette. */
export const Backdrop: React.FC<{ base?: string; accent?: string; accent2?: string; intensity?: number }> = ({ base, accent, accent2, intensity = 1 }) => {
  const { theme: t, width, height } = useTemplate();
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const a = accent ?? t.accent;
  const b = accent2 ?? t.accent2 ?? theme.colors.primary2;
  const s = frame / fps;
  const big = Math.max(width, height);
  return (
    <AbsoluteFill style={{ backgroundColor: base ?? t.bg, overflow: 'hidden' }}>
      <div style={{ position: 'absolute', width: big * 0.9, height: big * 0.9, borderRadius: '50%', left: width * 0.05 + Math.sin(s * 0.5) * width * 0.06, top: -big * 0.25 + Math.cos(s * 0.4) * height * 0.04, background: `radial-gradient(circle, ${alpha(a, 0.35 * intensity)} 0%, transparent 65%)`, filter: 'blur(20px)' }} />
      <div style={{ position: 'absolute', width: big * 0.8, height: big * 0.8, borderRadius: '50%', right: -big * 0.2 + Math.cos(s * 0.45) * width * 0.05, bottom: -big * 0.25, background: `radial-gradient(circle, ${alpha(b, 0.28 * intensity)} 0%, transparent 65%)`, filter: 'blur(24px)' }} />
      <AbsoluteFill style={{ backgroundImage: `linear-gradient(${alpha('#FFFFFF', 0.035)} 1px, transparent 1px), linear-gradient(90deg, ${alpha('#FFFFFF', 0.035)} 1px, transparent 1px)`, backgroundSize: `${big / 18}px ${big / 18}px`, maskImage: 'radial-gradient(circle at 50% 45%, black 20%, transparent 75%)' }} />
      <AbsoluteFill style={{ background: 'radial-gradient(ellipse at center, transparent 50%, rgba(0,0,0,0.55) 100%)' }} />
    </AbsoluteFill>
  );
};

/** Deterministic pseudo-random in [0,1). */
export const rnd = (seed: string | number) => random(seed);

export { clamp };
