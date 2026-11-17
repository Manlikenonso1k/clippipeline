import { z } from 'zod';
import { color, image, text, video } from '../types';

export const schema = z.object({
  clip: video('Video in the phone'),
  screenshot: image('Or a screenshot'),
  caption: text(60, 'Edit on your phone', 'Caption'),
  accent: color('Accent colour'),
});
