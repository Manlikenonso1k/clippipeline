import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import type { CaptionWord } from '../../edl';
import { onColor, pick, theme } from '../../theme';
import { useTemplate } from '../ctx';
import { clamp } from '../kit';
import type { schema } from './schema';

const DEMO: CaptionWord[] = 'This is how your captions pop word by word'.split(' ').map((w, i) => ({ text: w, start: 0.2 + i * 0.4, end: 0.55 + i * 0.4 }));

/** Group words into short chunks, breaking at pauses and sentence ends. */
export function chunk(words: CaptionWord[], max: number, maxChars = 22): CaptionWord[][] {
  const out: CaptionWord[][] = [];
  let cur: CaptionWord[] = [];
  for (const w of words) {
    const prev = cur[cur.length - 1];
    const chars = cur.reduce((n, x) => n + x.text.length + 1, 0) + w.text.length;
    if (cur.length && (cur.length >= max || chars > maxChars || (prev && w.start - prev.end > 0.6) || /[.!?]$/.test(prev?.text ?? ''))) {
      out.push(cur);
      cur = [];
    }
    cur.push(w);
  }
  if (cur.length) out.push(cur);
  return out;
}

export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const { theme: t, unit, words: ctxWords, aspect, demo } = useTemplate();
  const words = demo ? DEMO : ctxWords;
  const now = frame / fps;
  const groups = chunk(words, props.max_words);
  const group = groups.find((g) => now >= g[0].start - 0.05 && now <= g[g.length - 1].end + 0.35);
  if (!group) return null;

  const active = pick(props.active_color, t.accent);
  const color = pick(props.text_color, t.text);
  const size = { s: 58, m: 74, l: 92 }[props.size] * unit * (aspect === '16:9' ? 0.85 : 1);
  const gStart = group[0].start * fps;
  const pop = interpolate(frame, [gStart - 2, gStart + 0.18 * fps], [0, 1], { ...clamp, easing: theme.ease.out });
  const justify = props.position === 'top' ? 'flex-start' : props.position === 'center' ? 'center' : 'flex-end';

  return (
    <AbsoluteFill style={{ justifyContent: justify, alignItems: 'center', padding: `${(aspect === '9:16' ? 300 : 120) * unit}px ${70 * unit}px` }}>
      <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'center', gap: `${10 * unit}px ${18 * unit}px`, opacity: pop, scale: String(0.85 + 0.15 * pop), fontFamily: t.font || theme.fonts.display, fontWeight: 900, fontSize: size, lineHeight: 1.1, textTransform: props.uppercase ? 'uppercase' : 'none', textAlign: 'center' }}>
        {group.map((w, i) => {
          const on = now >= w.start && now < (group[i + 1]?.start ?? w.end + 0.35);
          const k = interpolate(frame, [w.start * fps, w.start * fps + 0.12 * fps], [0, 1], { ...clamp, easing: theme.ease.out });
          return (
            <span key={i} style={{ padding: `${4 * unit}px ${14 * unit}px`, borderRadius: 14 * unit, color: on ? onColor(active) : color, backgroundColor: on ? active : 'transparent', scale: on ? String(1 + 0.08 * k) : '1', textShadow: on ? 'none' : `0 ${4 * unit}px ${18 * unit}px rgba(0,0,0,0.85), 0 0 ${3 * unit}px rgba(0,0,0,0.9)` }}>
              {w.text}
            </span>
          );
        })}
      </div>
    </AbsoluteFill>
  );
};
