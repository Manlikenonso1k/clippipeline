import React from 'react';
import { AbsoluteFill } from 'remotion';
import type { z } from 'zod';
import { alpha, pick, theme } from '../../theme';
import { useTemplate } from '../ctx';
import { enter, useIn, useOut } from '../kit';
import type { schema } from './schema';

export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => {
  const { theme: t, unit, aspect } = useTemplate();
  const accent = pick(props.accent, t.accent);
  const bar = useIn(0, 0.55);
  const name = useIn(0.18, 0.6);
  const role = useIn(0.3, 0.6);
  const out = useOut(0.3);
  const right = props.side === 'right';
  const bottomPad = (aspect === '9:16' ? 660 : 110) * unit;
  return (
    <AbsoluteFill style={{ justifyContent: props.position === 'top' ? 'flex-start' : 'flex-end', alignItems: right ? 'flex-end' : 'flex-start', padding: `${props.position === 'top' ? 140 * unit : 0}px ${70 * unit}px ${props.position === 'top' ? 0 : bottomPad}px` }}>
      <div style={{ display: 'flex', flexDirection: right ? 'row-reverse' : 'row', alignItems: 'stretch', gap: 22 * unit, opacity: out, translate: `${(1 - out) * (right ? 40 : -40) * unit}px 0px` }}>
        <div style={{ width: 12 * unit, borderRadius: 8 * unit, background: `linear-gradient(180deg, ${accent}, ${t.accent2 || theme.colors.primary2})`, scale: `1 ${bar}`, transformOrigin: 'top' }} />
        <div style={{ backgroundColor: alpha(t.bg, 0.78), border: `${2 * unit}px solid ${alpha('#FFFFFF', 0.08)}`, borderRadius: 22 * unit, padding: `${22 * unit}px ${34 * unit}px`, clipPath: `inset(0 ${right ? 0 : (1 - bar) * 100}% 0 ${right ? (1 - bar) * 100 : 0}%)`, fontFamily: t.font || theme.fonts.body, textAlign: right ? 'right' : 'left' }}>
          <div style={{ ...enter(name, 1, 18 * unit), fontSize: 56 * unit, fontWeight: 800, color: t.text, letterSpacing: -0.5 * unit }}>{props.name}</div>
          {props.role ? <div style={{ ...enter(role, 1, 14 * unit), fontSize: 32 * unit, fontWeight: 600, color: accent, marginTop: 6 * unit }}>{props.role}</div> : null}
        </div>
      </div>
    </AbsoluteFill>
  );
};
