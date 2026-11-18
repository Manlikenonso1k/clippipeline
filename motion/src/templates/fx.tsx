// Shared look for the bold "creator" templates (hook, pop headline, captions-bold, neon outro).
import type React from 'react';
import { theme } from '../theme';
import type { TemplateCtx } from './types';

export const displayFont = (ctx: TemplateCtx) => ctx.theme.display || 'Anton';
export const accent2 = (ctx: TemplateCtx) => ctx.theme.accent2 || theme.colors.primary2;

export const gradientText = (top: string, mid: string, bottom: string): React.CSSProperties => ({
  backgroundImage: `linear-gradient(180deg, ${top} 0%, ${mid} 42%, ${bottom} 100%)`,
  WebkitBackgroundClip: 'text',
  backgroundClip: 'text',
  color: 'transparent',
});

/** Lighter tint of a hex colour (for gradient highlights). */
export function tint(hex: string, amount: number): string {
  const n = parseInt(hex.replace('#', '').slice(0, 6), 16);
  const mix = (c: number) => Math.round(c + (255 - c) * amount);
  const r = mix((n >> 16) & 255), g = mix((n >> 8) & 255), b = mix(n & 255);
  return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1).toUpperCase()}`;
}
