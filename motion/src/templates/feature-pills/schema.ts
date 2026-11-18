import { z } from 'zod';
import { color, list, text } from '../types';

export const schema = z.object({
  title: text(28, 'Upload once', 'Title'),
  items: list(6, 22, ['Edit', 'Clip', 'Motion graphics', 'Post everywhere', 'Track analytics'], 'Pills'),
  accent: color('Accent colour'),
});
