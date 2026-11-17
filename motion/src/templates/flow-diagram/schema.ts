import { z } from 'zod';
import { color, list, text } from '../types';

export const schema = z.object({
  title: text(60, 'How it works', 'Title'),
  steps: list(5, 24, ['Upload', 'Auto-cut', 'Add graphics', 'Post everywhere'], 'Steps (3–5)'),
  accent: color('Accent colour'),
});
