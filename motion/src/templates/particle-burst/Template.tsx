import type { TransitionPresentation, TransitionPresentationComponentProps } from '@remotion/transitions';
import React from 'react';
import { AbsoluteFill, random } from 'remotion';
import type { z } from 'zod';
import { alpha, theme } from '../../theme';
import { TransitionDemo } from '../TransitionDemo';
import type { schema } from './schema';
import { meta } from './meta';

type Props = { color: string; count: number };

/** Crossfade under a burst of particles from the centre. */
const Burst: React.FC<TransitionPresentationComponentProps<Props>> = ({ children, presentationDirection, presentationProgress: p, passedProps }) => {
  const e = theme.ease.out(p);
  if (presentationDirection === 'exiting') return <AbsoluteFill style={{ opacity: 1 - theme.ease.in(p), scale: String(1 + p * 0.06) }}>{children}</AbsoluteFill>;
  const c = passedProps.color || theme.colors.soft;
  return (
    <AbsoluteFill>
      <AbsoluteFill style={{ opacity: e }}>{children}</AbsoluteFill>
      <AbsoluteFill style={{ pointerEvents: 'none' }}>
        {Array.from({ length: passedProps.count }, (_, i) => {
          const ang = random(`a${i}`) * Math.PI * 2;
          const dist = (0.15 + random(`d${i}`) * 0.6) * e;
          const size = 6 + random(`s${i}`) * 18;
          return <div key={i} style={{ position: 'absolute', left: `${50 + Math.cos(ang) * dist * 100}%`, top: `${50 + Math.sin(ang) * dist * 100}%`, width: size, height: size, borderRadius: 99, backgroundColor: c, boxShadow: `0 0 ${size * 2}px ${alpha(c.startsWith('#') ? c : '#FFFFFF', 0.8)}`, opacity: (1 - p) * 1.4, translate: '-50% -50%' }} />;
        })}
      </AbsoluteFill>
    </AbsoluteFill>
  );
};

export const presentation = (props: z.output<typeof schema>): TransitionPresentation<Record<string, unknown>> =>
  ({ component: Burst, props: { color: props.color, count: props.count } }) as unknown as TransitionPresentation<Record<string, unknown>>;

export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => <TransitionDemo presentation={presentation(props)} seconds={meta.defaultDuration} />;
