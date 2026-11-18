// theme.ts — single source of truth (adapted from remotion-motion-graphics/assets/theme.ts, MIT).
// Kinetic Obsidian palette. Linear easing is forbidden.
import { Easing } from 'remotion';

export const theme = {
  colors: {
    bg: '#0E0E12',
    bgAlt: '#16161D',
    raised: '#1E1E28',
    primary: '#7C5CFF',
    primary2: '#B14CFF',
    soft: '#CABEFF',
    text: '#FFFFFF',
    textDim: '#A1A1AE',
    success: '#28C76F',
  },
  fonts: {
    display: 'Inter',
    body: 'Inter',
    mono: 'JetBrains Mono',
  },
  ease: {
    out: Easing.bezier(0.16, 1, 0.3, 1), // entrances
    inOut: Easing.bezier(0.83, 0, 0.17, 1), // moves, Ken Burns
    in: Easing.bezier(0.7, 0, 0.84, 0), // exits only
  },
  spring: {
    snappy: { damping: 14, stiffness: 160, mass: 0.6 },
    smooth: { damping: 20, stiffness: 90, mass: 1 },
    bouncy: { damping: 11, stiffness: 170, mass: 0.7 },
  },
} as const;

/** Project theme from the EDL (CONTRACT §17). */
/** `display` is the headline font (e.g. Anton); `font` the body font. */
export type ProjectTheme = { accent: string; accent2?: string | null; bg: string; text: string; font?: string | null; display?: string | null };

export const DEFAULT_PROJECT_THEME: ProjectTheme = {
  accent: theme.colors.primary,
  accent2: theme.colors.primary2,
  bg: theme.colors.bg,
  text: theme.colors.text,
  font: 'Inter',
  display: 'Inter',
};

/** Hex colour with alpha (0..1). */
export function alpha(hex: string, a: number): string {
  const h = hex.replace('#', '');
  const n = parseInt(h.length === 3 ? h.split('').map((c) => c + c).join('') : h.slice(0, 6), 16);
  return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${a})`;
}

/** Readable text colour on a background. */
export function onColor(hex: string): string {
  const h = hex.replace('#', '');
  const n = parseInt(h.slice(0, 6), 16);
  const l = (0.299 * ((n >> 16) & 255) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) / 255;
  return l > 0.6 ? '#0E0E12' : '#FFFFFF';
}

/** Prop colour or the project theme fallback (templates never hard-code brand colours). */
export const pick = (value: unknown, fallback: string): string =>
  typeof value === 'string' && /^#[0-9A-Fa-f]{6}$/.test(value) ? value : fallback;
