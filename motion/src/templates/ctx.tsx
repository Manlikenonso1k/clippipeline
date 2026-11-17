import React, { createContext, useContext } from 'react';
import { useVideoConfig } from 'remotion';
import type { Aspect } from '../edl';
import { DEFAULT_PROJECT_THEME } from '../theme';
import type { TemplateCtx } from './types';

const Ctx = createContext<TemplateCtx | null>(null);

export const TemplateProvider: React.FC<{ value: TemplateCtx; children: React.ReactNode }> = ({ value, children }) => (
  <Ctx.Provider value={value}>{children}</Ctx.Provider>
);

/** Template context; falls back to the composition size and default theme (gallery previews). */
export function useTemplate(): TemplateCtx {
  const value = useContext(Ctx);
  const { width, height } = useVideoConfig();
  if (value) return value;
  const ratio = width / height;
  const aspect: Aspect = ratio < 0.8 ? '9:16' : ratio < 1.25 ? '1:1' : '16:9';
  return { theme: DEFAULT_PROJECT_THEME, aspect, width, height, unit: Math.min(width, height) / 1080, resolveAsset: () => null, words: [], musicSrc: null, demo: true };
}
