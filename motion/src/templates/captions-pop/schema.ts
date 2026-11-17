import { z } from 'zod';
import { bool, choice, color, num } from '../types';

export const schema = z.object({
  position: choice(['bottom', 'center', 'top'] as const, 'bottom', 'Position'),
  active_color: color('Active word colour'),
  text_color: color('Text colour'),
  size: choice(['s', 'm', 'l'] as const, 'm', 'Size'),
  uppercase: bool(true, 'Uppercase'),
  max_words: num(1, 6, 3, 'Words per line'),
});
