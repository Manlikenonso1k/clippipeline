import type { TransitionPresentation, TransitionPresentationComponentProps } from '@remotion/transitions';
import React from 'react';
import { AbsoluteFill } from 'remotion';
import type { z } from 'zod';
import { TransitionDemo } from '../TransitionDemo';
import type { schema } from './schema';
import { meta } from './meta';

type Props = { color: string };

/** Hard cut under a quick bright flash (peaks mid-transition). */
const Flash: React.FC<TransitionPresentationComponentProps<Props>> = ({ children, presentationDirection, presentationProgress: p, passedProps }) => {
  const peak = 1 - Math.abs(p - 0.5) * 2;
  const glow = Math.pow(Math.max(0, peak), 0.7);
  return (
    <AbsoluteFill>
      <AbsoluteFill style={{ opacity: presentationDirection === 'exiting' ? (p < 0.5 ? 1 : 0) : p >= 0.5 ? 1 : 0 }}>{children}</AbsoluteFill>
      {presentationDirection === 'entering' ? <AbsoluteFill style={{ backgroundColor: passedProps.color || '#FFFFFF', opacity: glow }} /> : null}
    </AbsoluteFill>
  );
};

export const presentation = (props: z.output<typeof schema>): TransitionPresentation<Record<string, unknown>> =>
  ({ component: Flash, props: { color: props.color } }) as unknown as TransitionPresentation<Record<string, unknown>>;

export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => <TransitionDemo presentation={presentation(props)} seconds={meta.defaultDuration} />;
