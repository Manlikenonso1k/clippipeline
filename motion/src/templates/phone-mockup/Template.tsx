import { Video } from '@remotion/media';
import React from 'react';
import { AbsoluteFill, Img, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import { alpha, pick, theme } from '../../theme';
import { useTemplate } from '../ctx';
import { Backdrop, clamp, enter, useBreathe, useIn, useOut, useSpring } from '../kit';
import type { schema } from './schema';

const FakeScreen: React.FC<{ accent: string; unit: number }> = ({ accent, unit }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  return (
    <AbsoluteFill style={{ background: `linear-gradient(160deg, ${alpha(accent, 0.55)}, #0E0E12 70%)`, padding: 36 * unit, gap: 22 * unit }}>
      {[0, 1, 2, 3].map((i) => {
        const p = interpolate(frame, [(0.5 + i * 0.15) * fps, (1 + i * 0.15) * fps], [0, 1], { ...clamp, easing: theme.ease.out });
        return <div key={i} style={{ height: (i === 0 ? 260 : 90) * unit, borderRadius: 26 * unit, backgroundColor: alpha('#FFFFFF', i === 0 ? 0.14 : 0.08), opacity: p, translate: `0px ${(1 - p) * 30 * unit}px` }} />;
      })}
    </AbsoluteFill>
  );
};

export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => {
  const { theme: t, unit, aspect, height } = useTemplate();
  const { resolveAsset } = useTemplate();
  const accent = pick(props.accent, t.accent);
  const media = resolveAsset(props.clip) ?? resolveAsset(props.screenshot);
  const rise = useSpring(0, 'smooth');
  const cap = useIn(0.45, 0.6);
  const out = useOut(0.3);
  const float = useBreathe(10 * unit, 4);
  const ph = Math.min(height * (aspect === '9:16' ? 0.66 : 0.78), 1250 * unit);
  const pw = ph * 0.49;
  const row = aspect === '16:9';
  return (
    <AbsoluteFill>
      <Backdrop accent={accent} />
      <AbsoluteFill style={{ flexDirection: row ? 'row' : 'column', justifyContent: 'center', alignItems: 'center', gap: 70 * unit, opacity: out, fontFamily: t.font || theme.fonts.display }}>
        <div style={{ width: pw, height: ph, borderRadius: pw * 0.16, padding: pw * 0.035, backgroundColor: '#05050A', border: `${3 * unit}px solid ${alpha('#FFFFFF', 0.14)}`, boxShadow: `0 ${50 * unit}px ${120 * unit}px rgba(0,0,0,0.6), 0 0 ${90 * unit}px ${alpha(accent, 0.3)}`, translate: `0px ${(1 - rise) * height * 0.35 + float}px`, rotate: `${(1 - rise) * 8 - 2}deg` }}>
          <div style={{ position: 'relative', width: '100%', height: '100%', borderRadius: pw * 0.13, overflow: 'hidden', backgroundColor: '#000' }}>
            {media?.kind === 'video' ? <Video src={media.src} muted loop objectFit="cover" style={{ width: '100%', height: '100%' }} /> : media ? <Img src={media.src} style={{ width: '100%', height: '100%', objectFit: 'cover' }} /> : <FakeScreen accent={accent} unit={unit} />}
            <div style={{ position: 'absolute', top: pw * 0.03, left: '50%', translate: '-50% 0px', width: pw * 0.3, height: pw * 0.075, borderRadius: 99, backgroundColor: '#000' }} />
          </div>
        </div>
        {props.caption ? <div style={{ ...enter(cap, 1, 24 * unit), maxWidth: row ? 640 * unit : '86%', fontSize: 62 * unit, fontWeight: 800, color: t.text, textAlign: row ? 'left' : 'center', lineHeight: 1.1 }}>{props.caption}</div> : null}
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
