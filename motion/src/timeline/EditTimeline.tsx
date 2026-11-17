import { Audio, Video } from '@remotion/media';
import { TransitionSeries, springTiming } from '@remotion/transitions';
import React, { useMemo } from 'react';
import { AbsoluteFill, Img, Sequence, interpolate, useVideoConfig } from 'remotion';
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
const MusicTrack: React.FC<{ edl: Edl; asset: AssetRef; item: Edl['music'][number]; speech: [number, number][]; total: number }> = ({ edl, asset, item, speech, total }) => {
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
        src={asset.src}
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

export const EditTimeline: React.FC<EditTimelineProps> = ({ edl, assets, transcripts }) => {
  ensureFonts();
  const { fps, width, height } = useVideoConfig();
  const { items, total } = useMemo(() => layout(edl), [edl]);
  const words = useMemo(() => captionWords(edl, transcripts), [edl, transcripts]);
  const speech = useMemo(() => speechRanges(words), [words]);
  const music = edl.music.find((m) => assets[String(m.asset_id)]);
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
    musicSrc: music ? assets[String(music.asset_id)]?.src ?? null : null,
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

        {edl.overlays.map((o) => {
          const from = overlayFrom(edl, o, items);
          const frames = Math.max(1, Math.min(Math.round(o.duration * fps), total - from));
          return from < total ? (
            <Sequence key={o.id} from={from} durationInFrames={frames} name={o.template}>
              <TemplateView slug={o.template} version={o.version} props={o.props} />
            </Sequence>
          ) : null;
        })}

        {music ? <MusicTrack edl={edl} asset={assets[String(music.asset_id)]} item={music} speech={speech} total={total} /> : null}

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
