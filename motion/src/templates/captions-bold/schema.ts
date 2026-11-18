import { z } from 'zod';
import { choice, color, num } from '../types';

export const schema = z.object({
  active_color: color('Active word colour'),
  size: choice(['m', 'l', 'xl'] as const, 'l', 'Size'),
  position: choice(['low', 'middle'] as const, 'low', 'Position'),
  max_words: num(1, 4, 3, 'Words per line'),
});
