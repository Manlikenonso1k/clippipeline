import { z } from 'zod';
import { choice, color, num, text } from '../types';

export const schema = z.object({
  title: text(24, 'GTA 6', 'Big title'),
  kicker: text(40, 'is going to make', 'Line under it'),
  punch: text(24, 'Millionaires', 'Punch word'),
  punch_at: num(0, 10, 1.1, 'Punch appears at (s)'),
  position: choice(['top', 'center'] as const, 'top', 'Position'),
  accent: color('Accent colour'),
});
