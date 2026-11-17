import { z } from 'zod';
import { color, image, text } from '../types';

export const schema = z.object({
  logo: image('Logo'),
  handle: text(40, '@yourhandle', 'Handle'),
  cta: text(40, 'Follow for more', 'Call to action'),
  accent: color('Accent colour'),
});
