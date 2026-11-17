import { z } from 'zod';
import { color, num } from '../types';

export const schema = z.object({
  color: color('Particle colour'),
  count: num(10, 80, 40, 'Particles'),
});
