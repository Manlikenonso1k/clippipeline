import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import type { CaptionWord } from '../../edl';
import { alpha, pick, theme } from '../../theme';
import { useTemplate } from '../ctx';
import { clamp } from '../kit';
import type { schema } from './schema';

const DEMO: CaptionWord[] = 'Clean two line subtitles that stay out of the way and read easily'.split(' ').map((w, i) => ({ text: w, start: 0.2 + i * 0.3, end: 0.48 + i * 0.3 }));

/** Up to two lines of ~32 characters, broken at pauses and sentence ends. */
function blocks(words: CaptionWord[], maxChars = 64): CaptionWord[][] {
  const out: CaptionWord[][] = [];
  let cur: CaptionWord[] = [];
  for (const w of words) {
    const prev = cur[cur.length - 1];
    const len = cur.reduce((n, x) => n + x.text.length + 1, 0) + w.text.length;
    if (cur.length && (len > maxChars || (prev && w.start - prev.end > 0.8) || /[.!?]$/.test(prev?.text ?? ''))) {
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
  const now = frame / fps;
  const all = blocks(demo ? DEMO : ctxWords, aspect === '16:9' ? 84 : 56);
  const block = all.find((b) => now >= b[0].start - 0.05 && now <= b[b.length - 1].end + 0.4);
  if (!block) return null;
  const a = interpolate(frame, [block[0].start * fps - 3, block[0].start * fps + 4], [0, 1], { ...clamp, easing: theme.ease.out });
  const size = { s: 40, m: 50, l: 62 }[props.size] * unit;
  return (
    <AbsoluteFill style={{ justifyContent: props.position === 'top' ? 'flex-start' : 'flex-end', alignItems: 'center', padding: `${(aspect === '9:16' ? 260 : 90) * unit}px ${80 * unit}px` }}>
      <div style={{ opacity: a, translate: `0px ${(1 - a) * 10 * unit}px`, maxWidth: '88%', textAlign: 'center', fontFamily: t.font || theme.fonts.body, fontWeight: 700, fontSize: size, lineHeight: 1.3, color: pick(props.text_color, t.text), backgroundColor: props.background ? alpha('#000000', 0.62) : 'transparent', padding: `${12 * unit}px ${24 * unit}px`, borderRadius: 14 * unit, textShadow: props.background ? 'none' : `0 ${3 * unit}px ${12 * unit}px rgba(0,0,0,0.9)` }}>
        {block.map((w) => w.text).join(' ')}
      </div>
    </AbsoluteFill>
  );
};
