import { z } from 'zod';
import { color, num, text } from '../types';

export const schema = z.object({
  value: num(0, 1000000000, 1250, 'Number'),
  decimals: num(0, 2, 0, 'Decimals'),
  prefix: text(4, '', 'Prefix'),
  suffix: text(6, '+', 'Suffix'),
  label: text(60, 'happy creators', 'Label'),
  accent: color('Accent colour'),
});
