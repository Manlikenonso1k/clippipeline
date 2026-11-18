import { z } from 'zod';
import { choice, color, text } from '../types';

export const schema = z.object({
  text: text(28, 'Get your bag', 'Text'),
  highlight: text(16, 'bag', 'Highlighted word'),
  style: choice(['box', 'gradient', 'echo', 'slam'] as const, 'box', 'Style'),
  position: choice(['top', 'center'] as const, 'top', 'Position'),
  accent: color('Accent colour'),
});
