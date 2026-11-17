import type { TransitionPresentation, TransitionPresentationComponentProps } from '@remotion/transitions';
import React from 'react';
import { AbsoluteFill } from 'remotion';
import type { z } from 'zod';
import { theme } from '../../theme';
import { TransitionDemo } from '../TransitionDemo';
import type { schema } from './schema';
import { meta } from './meta';

type Props = { strength: number };

/** Punch through: the old scene zooms and blurs out, the new one settles in from a blur. */
const ZoomBlur: React.FC<TransitionPresentationComponentProps<Props>> = ({ children, presentationDirection, presentationProgress: p, passedProps }) => {
  const k = passedProps.strength;
  if (presentationDirection === 'exiting') {
    const e = theme.ease.in(p);
    return <AbsoluteFill style={{ scale: String(1 + e * 0.6 * k), filter: `blur(${e * 30 * k}px)`, opacity: 1 - e }}>{children}</AbsoluteFill>;
  }
  const e = theme.ease.out(p);
  return <AbsoluteFill style={{ scale: String(1 - (1 - e) * 0.35 * k), filter: `blur(${(1 - e) * 30 * k}px)`, opacity: Math.min(1, e * 1.6) }}>{children}</AbsoluteFill>;
};

export const presentation = (props: z.output<typeof schema>): TransitionPresentation<Record<string, unknown>> =>
  ({ component: ZoomBlur, props: { strength: props.strength } }) as unknown as TransitionPresentation<Record<string, unknown>>;

export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => <TransitionDemo presentation={presentation(props)} seconds={meta.defaultDuration} />;
