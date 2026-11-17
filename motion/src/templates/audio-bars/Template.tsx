import { useWindowedAudioData, visualizeAudio } from '@remotion/media-utils';
import React from 'react';
import { AbsoluteFill, useCurrentFrame, useVideoConfig } from 'remotion';
import type { z } from 'zod';
import { alpha, pick } from '../../theme';
import { useTemplate } from '../ctx';
import { rnd, useIn, useOut } from '../kit';
import type { schema } from './schema';

type P = { props: z.output<typeof schema> };

const Bars: React.FC<P & { values: number[] }> = ({ props, values }) => {
  const { theme: t, unit, width, aspect } = useTemplate();
  const c = pick(props.color, t.accent);
  const a = useIn(0, 0.5);
  const out = useOut(0.3);
  const w = width * 0.8;
  const bh = 260 * unit;
  const mirror = props.style === 'mirror';
  return (
    <AbsoluteFill style={{ justifyContent: props.position === 'center' ? 'center' : 'flex-end', alignItems: 'center', paddingBottom: props.position === 'bottom' ? (aspect === '9:16' ? 420 : 80) * unit : 0, opacity: a * out }}>
      <div style={{ width: w, height: bh * (mirror ? 2 : 1), display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        {values.map((v, i) => {
          const h = Math.max(6 * unit, Math.min(1, v) * bh);
          return <div key={i} style={{ width: (w / values.length) * 0.6, height: mirror ? h * 2 : h, alignSelf: mirror ? 'center' : 'flex-end', borderRadius: 99, background: `linear-gradient(180deg, ${c}, ${alpha(c, 0.4)})`, boxShadow: `0 0 ${18 * unit}px ${alpha(c, 0.5)}` }} />;
        })}
      </div>
    </AbsoluteFill>
  );
};

const FromMusic: React.FC<P & { src: string }> = ({ props, src }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const { audioData, dataOffsetInSeconds } = useWindowedAudioData({ src, frame, fps, windowInSeconds: 30 });
  if (!audioData) return null;
  const freq = visualizeAudio({ fps, frame, audioData, numberOfSamples: 128, optimizeFor: 'speed', dataOffsetInSeconds });
  const n = props.bars;
  const values = Array.from({ length: n }, (_, i) => Math.pow(freq[Math.floor((i / n) * freq.length * 0.7)] ?? 0, 0.6) * 1.6);
  return <Bars props={props} values={values} />;
};

/** Without music (gallery preview) the bars dance on a deterministic pattern. */
const Synthetic: React.FC<P> = ({ props }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const s = frame / fps;
  const values = Array.from({ length: props.bars }, (_, i) => 0.25 + 0.6 * Math.abs(Math.sin(s * (2 + rnd(i) * 3) + i * 0.7)) * (0.5 + 0.5 * Math.sin(s * 1.3 + i * 0.2)));
  return <Bars props={props} values={values} />;
};

export const Template: React.FC<P> = ({ props }) => {
  const { musicSrc } = useTemplate();
  return musicSrc ? <FromMusic props={props} src={musicSrc} /> : <Synthetic props={props} />;
};
