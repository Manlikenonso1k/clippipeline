import React from 'react';
import { AbsoluteFill, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import { alpha, pick, theme } from '../../theme';
import { useTemplate } from '../ctx';
import { Backdrop, enter, useBreathe, useIn, useOut } from '../kit';
import type { schema } from './schema';

const KW = /\b(const|let|var|await|async|function|return|import|from|export|def|class|if|else|for|in|new|public|private|echo|fn)\b/;

function tokens(line: string, accent: string): { t: string; c: string }[] {
  const parts = line.split(/("[^"]*"|'[^']*'|\b\w+\b|\s+|.)/).filter(Boolean);
  return parts.map((p) => ({
    t: p,
    c: /^["']/.test(p) ? '#9AE6B4' : KW.test(p) && p.match(KW)?.[0] === p ? accent : /^\d+$/.test(p) ? '#F6AD55' : /^[A-Z]/.test(p) ? '#90CDF4' : '#E2E8F0',
  }));
}

export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => {
  const frame = useCurrentFrame();
  const { fps, durationInFrames } = useVideoConfig();
  const { theme: t, unit, aspect } = useTemplate();
  const accent = pick(props.accent, t.accent);
  const win = useIn(0, 0.6);
  const out = useOut(0.3);
  const float = useBreathe(5 * unit);
  const total = props.code.length;
  const typeEnd = Math.max(0.6 * fps, durationInFrames - 1.1 * fps);
  const shown = Math.floor(Math.min(1, Math.max(0, (frame - 0.5 * fps) / (typeEnd - 0.5 * fps))) * total);
  const lines = props.code.slice(0, shown).split('\n');
  const w = aspect === '16:9' ? 1300 * unit : 940 * unit;
  const blink = Math.floor(frame / (fps * 0.5)) % 2 === 0;
  return (
    <AbsoluteFill>
      <Backdrop accent={accent} intensity={0.7} />
      <AbsoluteFill style={{ justifyContent: 'center', alignItems: 'center', opacity: out }}>
        <div style={{ ...enter(win, 1, 40 * unit), translate: `0px ${float + (1 - win) * 40 * unit}px`, width: w, borderRadius: 28 * unit, overflow: 'hidden', backgroundColor: '#11111A', border: `${2 * unit}px solid ${alpha('#FFFFFF', 0.08)}`, boxShadow: `0 ${40 * unit}px ${100 * unit}px rgba(0,0,0,0.5), 0 0 ${80 * unit}px ${alpha(accent, 0.18)}` }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12 * unit, padding: `${20 * unit}px ${26 * unit}px`, backgroundColor: '#181824' }}>
            {['#FF5F57', '#FEBC2E', '#28C840'].map((c) => <div key={c} style={{ width: 20 * unit, height: 20 * unit, borderRadius: 99, backgroundColor: c }} />)}
            <div style={{ marginLeft: 16 * unit, fontFamily: theme.fonts.body, fontSize: 28 * unit, color: theme.colors.textDim, fontWeight: 600 }}>{props.title}</div>
          </div>
          <div style={{ padding: `${30 * unit}px ${34 * unit}px ${40 * unit}px`, fontFamily: theme.fonts.mono, fontSize: (aspect === '16:9' ? 36 : 32) * unit, lineHeight: 1.6, minHeight: 360 * unit, whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>
            {lines.map((l, i) => (
              <div key={i}>
                <span style={{ color: alpha('#FFFFFF', 0.25), marginRight: 26 * unit }}>{String(i + 1).padStart(2, ' ')}</span>
                {tokens(l, accent).map((tk, j) => <span key={j} style={{ color: tk.c }}>{tk.t}</span>)}
                {i === lines.length - 1 ? <span style={{ display: 'inline-block', width: 16 * unit, height: 38 * unit, translate: `0px ${6 * unit}px`, backgroundColor: accent, opacity: blink ? 1 : 0.2 }} /> : null}
              </div>
            ))}
          </div>
        </div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
