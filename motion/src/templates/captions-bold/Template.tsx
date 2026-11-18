import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import type { CaptionWord } from '../../edl';
import { pick, theme } from '../../theme';
import { chunk } from '../captions-pop/Template';
import { useTemplate } from '../ctx';
import { displayFont, displayScale, displayWeight } from '../fx';
import { clamp } from '../kit';
import type { schema } from './schema';

const DEMO: CaptionWord[] = 'Bold captions that hit on every word'.split(' ').map((w, i) => ({ text: w, start: 0.2 + i * 0.42, end: 0.55 + i * 0.42 }));

/** Heavy display-font captions with a dark stroke; the spoken word lights up and lifts. */
export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const ctx = useTemplate();
  const { unit, aspect, demo } = ctx;
  const now = frame / fps;
  const words = demo ? DEMO : ctx.words;
  const group = chunk(words, props.max_words, 16).find((g) => now >= g[0].start - 0.06 && now <= g[g.length - 1].end + 0.3);
  if (!group) return null;
  const active = pick(props.active_color, ctx.theme.accent);
  const pop = interpolate(now, [group[0].start - 0.06, group[0].start + 0.12], [0, 1], { ...clamp, easing: theme.ease.out });
  const size = { m: 84, l: 104, xl: 124 }[props.size] * unit * (aspect === '16:9' ? 0.8 : 1);
  const stroke = Math.max(4, size * 0.1);
  return (
    <AbsoluteFill style={{ justifyContent: props.position === 'middle' ? 'center' : 'flex-end', alignItems: 'center', paddingBottom: props.position === 'middle' ? 0 : (aspect === '9:16' ? 330 : 90) * unit }}>
      <div style={{ display: 'flex', columnGap: 34 * unit, rowGap: 6 * unit, flexWrap: 'wrap', justifyContent: 'center', maxWidth: '90%', scale: String(0.82 + 0.18 * pop), opacity: Math.min(1, pop * 1.5) }}>
        {group.map((w, i) => {
          const on = now >= w.start && now < (group[i + 1]?.start ?? w.end + 0.3);
          const k = interpolate(now, [w.start, w.start + 0.12], [0, 1], clamp);
          return (
            <span key={i} style={{ display: 'inline-block', fontFamily: displayFont(ctx), fontWeight: displayWeight(ctx), fontSize: size, lineHeight: 1.05, textTransform: 'uppercase', letterSpacing: unit, color: on ? active : '#FFFFFF', WebkitTextStroke: `${stroke}px #0E0418`, paintOrder: 'stroke fill', textShadow: on ? `0 0 ${28 * unit}px ${active}99, 0 ${8 * unit}px 0 #0E0418` : `0 ${8 * unit}px 0 #0E0418`, scale: on ? String(1 + 0.08 * k) : '1', translate: on ? `0px ${-6 * k * unit}px` : '0px 0px', transformOrigin: '50% 60%' }}>
              {w.text}
            </span>
          );
        })}
      </div>
    </AbsoluteFill>
  );
};
