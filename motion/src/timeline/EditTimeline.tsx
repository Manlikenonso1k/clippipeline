import { Audio, Video } from '@remotion/media';
import { TransitionSeries, springTiming } from '@remotion/transitions';
import React, { useMemo } from 'react';
import { AbsoluteFill, Img, Sequence, interpolate, staticFile, useVideoConfig } from 'remotion';
import { captionWords, dbToGain, layout, overlayFrom, speechRanges, type AssetRef, type Edl, type EdlClip, type Word } from '../edl';
import { ensureFonts } from '../fonts';
import { TemplateProvider } from '../templates/ctx';
import { getTemplate, normalizeProps } from '../templates/registry';
import type { TemplateCtx } from '../templates/types';
import { DEFAULT_PROJECT_THEME, theme } from '../theme';

export type EditTimelineProps = {
  edl: Edl;
  assets: Record<string, AssetRef>;
  transcripts: Record<string, Word[]>;
};

const clamp = { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' } as const;

/** One clip: a blurred fill behind (when the shape differs), the footage on top, optional punch-in zoom. */
const ClipView: React.FC<{ seg: EdlClip; asset: AssetRef | undefined; fit: Edl['fit'] }> = ({ seg, asset, fit }) => {
  const { fps, width, height } = useVideoConfig();
  if (!asset) return <AbsoluteFill style={{ backgroundColor: '#000' }} />;
  const zoom = seg.zoom ?? 1;
  const gain = dbToGain(seg.volume_db);
  if (asset.kind === 'image') {
    return (
      <AbsoluteFill style={{ backgroundColor: '#000' }}>
        <Img src={asset.src} style={{ width: '100%', height: '100%', objectFit: fit === 'crop' ? 'cover' : 'contain', scale: String(zoom) }} />
      </AbsoluteFill>
    );
  }
  const ratio = asset.width && asset.height ? asset.width / asset.height : width / height;
  const sameShape = Math.abs(ratio - width / height) < 0.03;
  const mode = fit === 'crop' || sameShape ? 'cover' : 'contain';
  return (
    <AbsoluteFill style={{ backgroundColor: '#000', overflow: 'hidden' }}>
      {fit === 'blur' && !sameShape ? (
        <AbsoluteFill style={{ scale: '1.15', filter: 'blur(40px) brightness(0.55) saturate(1.2)' }}>
          <Video src={asset.src} muted objectFit="cover" trimBefore={Math.round(seg.in * fps)} playbackRate={seg.speed ?? 1} style={{ width: '100%', height: '100%' }} />
        </AbsoluteFill>
      ) : null}
      <AbsoluteFill style={{ scale: String(zoom) }}>
        <Video
          src={asset.src}
          objectFit={mode}
          trimBefore={Math.round(seg.in * fps)}
          playbackRate={seg.speed ?? 1}
          volume={asset.has_audio === false ? 0 : gain}
          style={{ width: '100%', height: '100%' }}
        />
      </AbsoluteFill>
    </AbsoluteFill>
  );
};

const TemplateView: React.FC<{ slug: string; version?: number; props?: Record<string, unknown> }> = ({ slug, version, props }) => {
  const def = getTemplate(slug, version);
  if (!def) return null;
  const parsed = def.schema.safeParse(normalizeProps(props));
  const Comp = def.Component;
  return <Comp props={parsed.success ? parsed.data : def.schema.parse({})} />;
};

/** Music under the edit, ducked to 35 % while someone speaks, with fades. */
const MusicTrack: React.FC<{ src: string; item: Edl['music'][number]; speech: [number, number][]; total: number }> = ({ src, item, speech, total }) => {
  const { fps } = useVideoConfig();
  const from = Math.round((item.start ?? 0) * fps);
  const end = item.end != null ? Math.min(total, Math.round(item.end * fps)) : total;
  const len = Math.max(1, end - from);
  const base = item.volume ?? 0.25;
  const fadeIn = Math.max(1, (item.fade_in ?? 0.5) * fps);
  const fadeOut = Math.max(1, (item.fade_out ?? 1) * fps);
  return (
    <Sequence from={from} durationInFrames={len} name="Music">
      <Audio
        src={src}
        loop
        volume={(f) => {
          const t = (from + f) / fps;
          const talking = item.duck !== false && speech.some(([a, b]) => t >= a - 0.25 && t <= b + 0.3);
          const fade = Math.min(interpolate(f, [0, fadeIn], [0, 1], clamp), interpolate(f, [len - fadeOut, len], [1, 0], clamp));
          return base * fade * (talking ? 0.35 : 1);
        }}
      />
    </Sequence>
  );
};

const Look: React.FC<{ edl: Edl; total: number }> = ({ edl, total }) => {
  const bg = edl.theme.bg || theme.colors.bg;
  const a = edl.theme.accent;
  const b = edl.theme.accent2 || theme.colors.primary2;
  // The outro is a full-screen template: no scrim over it.
  const { items } = layout(edl);
  const outro = items.find((it) => it.seg.slot === 'outro');
  const until = outro ? outro.from + outro.transitionFrames : total;
  return (
    <Sequence from={0} durationInFrames={Math.max(1, until)} name="Look">
      <AbsoluteFill style={{ background: `linear-gradient(180deg, ${bg}E6 0%, ${bg}99 20%, transparent 42%, transparent 62%, ${bg}B3 85%, ${bg}E6 100%)` }} />
      {edl.grade === 'vice-night' ? (
        <AbsoluteFill style={{ background: `radial-gradient(ellipse 70% 55% at 0% 50%, ${a}33, transparent 70%), radial-gradient(ellipse 70% 55% at 100% 40%, ${b}40, transparent 70%)`, mixBlendMode: 'screen' }} />
      ) : null}
      <AbsoluteFill style={{ boxShadow: `inset 0 0 220px ${bg}` }} />
    </Sequence>
  );
};

/** Sound effects on graphic entrances, transitions and the outro (style packs with sfx: true). */
const SoundEffects: React.FC<{ edl: Edl; total: number }> = ({ edl, total }) => {
  const { fps } = useVideoConfig();
  const { items } = layout(edl);
  const cues: [number, string, number][] = [];
  for (const it of items) {
    if (it.transitionFrames > 0) cues.push([it.from, 'whoosh', 0.3]);
    if (it.seg.type === 'template' && it.index > 0) cues.push([it.from + 2, 'impact', 0.4]);
  }
  for (const o of edl.overlays) {
    const at = overlayFrom(edl, o, items);
    const style = (o.props as { style?: string } | undefined)?.style;
    if (o.template === 'hook-headline') cues.push([at, 'impact', 0.45]);
    else if (o.template === 'pop-headline') cues.push([at, style === 'slam' ? 'impact' : 'whoosh', style === 'slam' ? 0.4 : 0.28]);
    else if (o.template === 'money-counter') cues.push([at + Math.round(1 * fps), 'ding', 0.25]);
    else cues.push([at, 'pop', 0.3]);
  }
  return (
    <>
      {cues
        .filter(([f]) => f >= 0 && f < total - 2)
        .map(([f, name, vol], i) => (
          <Sequence key={i} from={f} durationInFrames={Math.min(Math.round(1.2 * fps), total - f)} name={`sfx ${name}`}>
            <Audio src={staticFile(`sfx/${name}.mp3`)} volume={vol} />
          </Sequence>
        ))}
    </>
  );
};

export const EditTimeline: React.FC<EditTimelineProps> = ({ edl, assets, transcripts }) => {
  ensureFonts();
  const { fps, width, height } = useVideoConfig();
  const { items, total } = useMemo(() => layout(edl), [edl]);
  const words = useMemo(() => captionWords(edl, transcripts), [edl, transcripts]);
  const speech = useMemo(() => speechRanges(words), [words]);
  const music = edl.music.find((m) => m.library || assets[String(m.asset_id)]);
  const musicSrc = music ? (music.library ? staticFile(`music/${music.library}.mp3`) : assets[String(music.asset_id)]?.src ?? null) : null;
  const ctx: TemplateCtx = {
    theme: { ...DEFAULT_PROJECT_THEME, ...edl.theme },
    aspect: edl.aspect,
    width,
    height,
    unit: Math.min(width, height) / 1080,
    resolveAsset: (ref) => {
      const id = ref && typeof ref === 'object' && 'asset_id' in ref ? (ref as { asset_id: unknown }).asset_id : null;
      return id != null ? assets[String(id)] ?? null : null;
    },
    words,
    musicSrc,
    demo: false,
  };

  return (
    <TemplateProvider value={ctx}>
      <AbsoluteFill style={{ backgroundColor: edl.theme.bg || theme.colors.bg }}>
        <TransitionSeries name="Main track">
          {items.flatMap((it) => {
            const seg = it.seg;
            const nodes: React.ReactNode[] = [];
            if (it.transitionFrames > 0 && seg.transition_in) {
              const tdef = getTemplate(seg.transition_in.template);
              if (tdef?.presentation) {
                nodes.push(
                  <TransitionSeries.Transition
                    key={`${seg.id}-t`}
                    presentation={tdef.presentation(tdef.schema.parse({}))}
                    timing={springTiming({ config: { damping: 200 }, durationInFrames: it.transitionFrames, durationRestThreshold: 0.001 })}
                  />,
                );
              }
            }
            nodes.push(
              <TransitionSeries.Sequence key={seg.id} name={seg.type === 'clip' ? `Clip ${seg.asset_id}` : seg.template} durationInFrames={it.frames}>
                {seg.type === 'clip' ? <ClipView seg={seg} asset={assets[String(seg.asset_id)]} fit={edl.fit} /> : <TemplateView slug={seg.template} version={seg.version} props={seg.props} />}
              </TransitionSeries.Sequence>,
            );
            return nodes;
          })}
        </TransitionSeries>

        {/* Style packs: scrims keep headlines and captions readable; the neon look adds an edge glow. */}
        {edl.grade && edl.grade !== 'none' ? <Look edl={edl} total={total} /> : null}

        {edl.overlays.map((o) => {
          const from = overlayFrom(edl, o, items);
          const frames = Math.max(1, Math.min(Math.round(o.duration * fps), total - from));
          return from < total ? (
            <Sequence key={o.id} from={from} durationInFrames={frames} name={o.template}>
              <TemplateView slug={o.template} version={o.version} props={o.props} />
            </Sequence>
          ) : null;
        })}

        {music && musicSrc ? <MusicTrack src={musicSrc} item={music} speech={speech} total={total} /> : null}

        {edl.sfx ? <SoundEffects edl={edl} total={total} /> : null}

        {/* Captions are applied last so nothing hides them. */}
        {edl.captions?.enabled ? (
          <Sequence from={0} durationInFrames={total} name="Captions">
            <TemplateView slug={edl.captions.template} version={edl.captions.version} props={edl.captions.props} />
          </Sequence>
        ) : null}
      </AbsoluteFill>
    </TemplateProvider>
  );
};
