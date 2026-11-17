import { z } from 'zod';
import { bool, choice, color } from '../types';

export const schema = z.object({
  position: choice(['bottom', 'top'] as const, 'bottom', 'Position'),
  size: choice(['s', 'm', 'l'] as const, 'm', 'Size'),
  background: bool(true, 'Box behind text'),
  text_color: color('Text colour'),
});
