import { z } from 'zod';
import { color, list, text } from '../types';

export const schema = z.object({
  title: text(60, 'Views per platform', 'Title'),
  labels: list(6, 14, ['TikTok', 'Reels', 'Shorts', 'Facebook'], 'Labels'),
  values: list(6, 12, ['82', '64', '45', '23'], 'Values'),
  unit: text(6, 'k', 'Unit'),
  accent: color('Accent colour'),
});
