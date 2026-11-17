import type { TransitionPresentation, TransitionPresentationComponentProps } from '@remotion/transitions';
import React from 'react';
import { AbsoluteFill } from 'remotion';
import type { z } from 'zod';
import { theme } from '../../theme';
import { TransitionDemo } from '../TransitionDemo';
import type { schema } from './schema';
import { meta } from './meta';

type Props = { direction: 'left' | 'right' | 'up' | 'down'; color: string };

/** The next scene slides in with a colour strip on its leading edge; the old one is pushed away. */
const Swipe: React.FC<TransitionPresentationComponentProps<Props>> = ({ children, presentationDirection, presentationProgress: p, passedProps }) => {
  const e = theme.ease.inOut(p);
  const d = passedProps.direction;
  const horizontal = d === 'left' || d === 'right';
  // Content travels toward `direction`: "left" means the new scene enters from the right.
  const sign = d === 'left' || d === 'up' ? -1 : 1;
  const at = (v: number) => (horizontal ? `${v}% 0%` : `0% ${v}%`);
  if (presentationDirection === 'exiting') {
    return <AbsoluteFill style={{ translate: at(sign * e * 35) }}>{children}</AbsoluteFill>;
  }
  const strip: React.CSSProperties = horizontal
    ? { top: 0, bottom: 0, width: '14%', left: sign < 0 ? '-14%' : '100%' }
    : { left: 0, right: 0, height: '14%', top: sign < 0 ? '-14%' : '100%' };
  return (
    <AbsoluteFill style={{ translate: at(-sign * (1 - e) * 114) }}>
      {children}
      <div style={{ position: 'absolute', ...strip, backgroundColor: passedProps.color || theme.colors.primary }} />
    </AbsoluteFill>
  );
};

export const presentation = (props: z.output<typeof schema>): TransitionPresentation<Record<string, unknown>> =>
  ({ component: Swipe, props: { direction: props.direction, color: props.color } }) as unknown as TransitionPresentation<Record<string, unknown>>;

export const Template: React.FC<{ props: z.output<typeof schema> }> = ({ props }) => <TransitionDemo presentation={presentation(props)} seconds={meta.defaultDuration} />;
