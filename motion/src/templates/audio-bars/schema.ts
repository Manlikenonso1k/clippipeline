import { z } from 'zod';
import { choice, color, num } from '../types';

export const schema = z.object({
  color: color('Bar colour'),
  bars: num(8, 64, 32, 'Number of bars'),
  position: choice(['bottom', 'center'] as const, 'bottom', 'Position'),
  style: choice(['bars', 'mirror'] as const, 'mirror', 'Style'),
});
