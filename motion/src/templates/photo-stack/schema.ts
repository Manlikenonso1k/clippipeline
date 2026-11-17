import { z } from 'zod';
import { color, image, text } from '../types';

export const schema = z.object({
  photo1: image('Photo 1'),
  photo2: image('Photo 2'),
  photo3: image('Photo 3'),
  photo4: image('Photo 4'),
  caption: text(60, 'Moments that matter', 'Caption'),
  accent: color('Accent colour'),
});
