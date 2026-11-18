import { z } from 'zod';
import { color, num, text } from '../types';

export const schema = z.object({
  value: num(1, 1000000000, 1000000, 'Amount'),
  prefix: text(4, '$', 'Currency'),
  label: text(40, '', 'Label'),
  accent: color('Accent colour'),
});
