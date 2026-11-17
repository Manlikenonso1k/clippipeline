import { z } from 'zod';
import { choice, color, text } from '../types';

export const schema = z.object({
  title: text(40, 'app.ts', 'File name'),
  language: choice(['ts', 'js', 'py', 'php', 'text'] as const, 'ts', 'Language'),
  code: text(600, "const clip = await studio.cut(video)\nconst post = clip.addCaptions('pop')\nawait post.publish(['tiktok', 'reels'])", 'Code'),
  accent: color('Accent colour'),
});
