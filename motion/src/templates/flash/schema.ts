import { z } from 'zod';
import { color } from '../types';

export const schema = z.object({
  color: color('Flash colour'),
});
