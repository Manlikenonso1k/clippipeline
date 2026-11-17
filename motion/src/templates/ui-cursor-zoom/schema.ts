import { z } from 'zod';
import { color, image, num, text } from '../types';

export const schema = z.object({
  screenshot: image('Screenshot'),
  click_x: num(0, 1, 0.68, 'Click position (left → right)'),
  click_y: num(0, 1, 0.42, 'Click position (top → bottom)'),
  zoom: num(1, 3, 1.8, 'Zoom'),
  caption: text(60, 'One tap to publish', 'Caption'),
  accent: color('Accent colour'),
});
