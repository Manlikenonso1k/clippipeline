import { z } from 'zod';
import { choice, color, text } from '../types';

export const schema = z.object({
  name: text(40, 'Your Name', 'Name'),
  role: text(60, 'Creator · ClipPipeline', 'Role'),
  accent: color('Accent colour'),
  side: choice(['left', 'right'] as const, 'left', 'Side'),
  position: choice(['bottom', 'top'] as const, 'bottom', 'Position'),
});
