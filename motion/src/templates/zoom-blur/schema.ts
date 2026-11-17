import { z } from 'zod';
import { num } from '../types';

export const schema = z.object({
  strength: num(0.1, 1, 0.5, 'Strength'),
});
