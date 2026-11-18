// Project EDL v2 (STUDIO_SPEC §7) and its frame layout. Shared by the render worker.

export type Aspect = '9:16' | '1:1' | '16:9';
export type EdlTransition = { template: string; duration: number };
export type EdlClip = {
  id: string; type: 'clip'; asset_id: number; in: number; out: number;
  volume_db?: number; speed?: number; zoom?: number; transition_in?: EdlTransition | null; slot?: string;
};
export type EdlTemplateSeg = {
  id: string; type: 'template'; template: string; version?: number; duration: number;
  props?: Record<string, unknown>; transition_in?: EdlTransition | null; slot?: string;
};
export type EdlSegment = EdlClip | EdlTemplateSeg;
export type EdlOverlay = {
  id: string; template: string; version?: number; anchor?: string | null; offset?: number; duration: number; props?: Record<string, unknown>;
};
export type Edl = {
  version: 2;
  aspect: Aspect;
  fps: number;
  theme: { accent: string; accent2?: string | null; bg: string; text: string; font?: string | null; display?: string | null };
  /** Colour grade applied by the worker when it conforms clips (STUDIO express styles). */
  grade?: 'none' | 'vice-night' | 'warm' | 'punch' | null;
  /** Add sound effects on graphic entrances and transitions. */
  sfx?: boolean;
  fit: 'blur' | 'crop' | 'black';
  main: EdlSegment[];
  overlays: EdlOverlay[];
  captions: { enabled: boolean; template: string; version?: number; props?: Record<string, unknown>; fixes?: Record<string, string> };
  /** A track is either the user's own asset or a built-in library track (motion/public/music/<library>.mp3). */
  music: { asset_id?: number | null; library?: string | null; start?: number; end?: number | null; volume?: number; duck?: boolean; fade_in?: number; fade_out?: number }[];
  loudness: number;
};

/** Media the renderer can load (worker: localhost URLs; editor: signed proxy URLs). */
export type AssetRef = { src: string; kind: 'video' | 'image' | 'audio'; width?: number | null; height?: number | null; duration?: number | null; has_audio?: boolean | null };
export type Word = { w: string; s: number; e: number };
export type CaptionWord = { text: string; start: number; end: number };

export const SIZES: Record<Aspect, { width: number; height: number }> = {
  '9:16': { width: 1080, height: 1920 },
  '1:1': { width: 1080, height: 1080 },
  '16:9': { width: 1920, height: 1080 },
};

/** Output size: final = 1080 on the short side, preview = 540. */
export function outputSize(aspect: Aspect, quality: 'preview' | 'final' | 'template'): { width: number; height: number } {
  const s = SIZES[aspect] ?? SIZES['9:16'];
  const k = quality === 'final' ? 1 : quality === 'template' ? 480 / 1080 : 0.5;
  const even = (n: number) => Math.round((n * k) / 2) * 2;
  return { width: even(s.width), height: even(s.height) };
}

export function segmentSeconds(seg: EdlSegment): number {
  if (seg.type === 'clip') return Math.max(0, (seg.out - seg.in) / (seg.speed || 1));
  return Math.max(0, seg.duration);
}

export type LaidOut = { seg: EdlSegment; index: number; from: number; frames: number; transitionFrames: number };

/** Start frame of every main-track segment (a transition overlaps the previous segment). */
export function layout(edl: Edl): { items: LaidOut[]; total: number } {
  const fps = edl.fps || 30;
  const items: LaidOut[] = [];
  let cursor = 0;
  edl.main.forEach((seg, index) => {
    const frames = Math.max(1, Math.round(segmentSeconds(seg) * fps));
    let t = index > 0 && seg.transition_in ? Math.round(seg.transition_in.duration * fps) : 0;
    const prev = items[items.length - 1];
    if (prev) t = Math.min(t, Math.floor(prev.frames / 2), Math.floor(frames / 2));
    const from = cursor - t;
    items.push({ seg, index, from, frames, transitionFrames: t });
    cursor = from + frames;
  });
  return { items, total: Math.max(1, cursor) };
}

export function totalFrames(edl: Edl): number {
  return layout(edl).total;
}

/** Overlays sit relative to their anchor segment (so reordering keeps them attached). */
export function overlayFrom(edl: Edl, o: EdlOverlay, items: LaidOut[]): number {
  const fps = edl.fps || 30;
  const anchor = o.anchor ? items.find((i) => i.seg.id === o.anchor) : undefined;
  return Math.max(0, Math.round((anchor ? anchor.from : 0) + (o.offset ?? 0) * fps));
}

/** Caption words in timeline seconds, built from the kept ranges of every clip. */
export function captionWords(edl: Edl, transcripts: Record<string, Word[] | undefined>): CaptionWord[] {
  const fps = edl.fps || 30;
  const fixes = edl.captions?.fixes ?? {};
  const out: CaptionWord[] = [];
  for (const it of layout(edl).items) {
    if (it.seg.type !== 'clip') continue;
    const seg = it.seg;
    const speed = seg.speed || 1;
    const start = it.from / fps;
    for (const w of transcripts[String(seg.asset_id)] ?? []) {
      if (w.e <= seg.in || w.s >= seg.out) continue;
      let text = w.w.trim();
      for (const [wrong, right] of Object.entries(fixes)) {
        if (wrong) text = text.split(wrong).join(right);
      }
      out.push({ text, start: start + (Math.max(w.s, seg.in) - seg.in) / speed, end: start + (Math.min(w.e, seg.out) - seg.in) / speed });
    }
  }
  return out;
}

/** Speech ranges in timeline seconds (music ducks under them). */
export function speechRanges(words: CaptionWord[], gap = 0.6): [number, number][] {
  const ranges: [number, number][] = [];
  for (const w of words) {
    const last = ranges[ranges.length - 1];
    if (last && w.start - last[1] < gap) last[1] = Math.max(last[1], w.end);
    else ranges.push([w.start, w.end]);
  }
  return ranges;
}

export const dbToGain = (db: number | undefined) => Math.pow(10, (db ?? 0) / 20);
