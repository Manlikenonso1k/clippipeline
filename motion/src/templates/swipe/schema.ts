import { z } from 'zod';
import { choice, color } from '../types';

export const schema = z.object({
  direction: choice(['left', 'right', 'up', 'down'] as const, 'left', 'Direction'),
  color: color('Panel colour'),
});
