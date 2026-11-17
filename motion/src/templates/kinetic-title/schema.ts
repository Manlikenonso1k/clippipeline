import { z } from 'zod';
import { choice, color, text } from '../types';

export const schema = z.object({
  kicker: text(40, 'NEW VIDEO', 'Kicker'),
  title: text(60, 'Make it move', 'Title'),
  subtitle: text(80, '', 'Subtitle'),
  accent: color('Accent colour'),
  align: choice(['center', 'left'] as const, 'center', 'Alignment'),
  style: choice(['stack', 'slam'] as const, 'stack', 'Animation'),
});
