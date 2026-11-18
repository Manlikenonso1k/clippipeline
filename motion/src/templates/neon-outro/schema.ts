import { z } from 'zod';
import { bool, color, image, list, text } from '../types';

export const schema = z.object({
  logo: image('Logo'),
  title: text(24, 'Your brand', 'Title'),
  subtitle: text(24, 'Follow for more', 'Subtitle'),
  items: list(5, 12, [], 'Icons row labels'),
  show_chart: bool(true, 'Growth chart'),
  cta: text(24, 'Follow now', 'Button'),
  note: text(60, '', 'Small line'),
  accent: color('Accent colour'),
});
