#!/usr/bin/env python3
"""studio_tools: JSON command line for the ClipPipeline render worker.

Built on vkit.py (same folder). Every command prints one JSON object on stdout;
logs go to stderr. The Node worker (render-worker/src) calls these as subprocesses.

  probe <src> <outdir>                       facts + proxy.mp4, poster.jpg, thumbs.jpg, waveform.json
  transcribe <src> <out.json> [--model small] [--language xx]
  analyze <src> <outdir> [--threshold 0.3]   reference style profile + contact.jpg
  master <in.mp4> <out.mp4> [--lufs -14] [--tp -1]
  check <video> [--sheet out.jpg]            loudness, true peak, black frames, long silences
  frames <video> <out.jpg> --at 1,2.5        frames at given times in one image
  loop <in.mp4> <out.mp4> <poster.jpg>       4 s muted 480p gallery loop, <= 400 KB
"""
from __future__ import annotations

import argparse
import colorsys
import json
import math
import os
import re
import subprocess
import sys
import tempfile
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import vkit  # noqa: E402

# Colour-grade presets for style packs (EDL "grade"). Shadow lifts help phone footage shot against a window.
GRADES = {
    "none": "",
    "vice-night": "curves=all='0/0.02 0.12/0.2 0.3/0.42 0.6/0.68 1/0.86',eq=saturation=1.18:contrast=1.04,"
                  "colorbalance=rh=0.06:bh=0.12:gh=-0.05:rm=0.02:bm=0.03,unsharp=5:5:0.5",
    "warm": "curves=all='0/0.02 0.15/0.2 0.5/0.55 1/0.96',eq=contrast=1.04:saturation=1.1,"
            "colorbalance=rm=0.04:gm=0.01:bm=-0.04:rh=0.02:bh=-0.03",
    "punch": "curves=all='0/0 0.25/0.22 0.5/0.52 0.75/0.8 1/1',eq=contrast=1.08:saturation=1.15,unsharp=5:5:0.6",
}

X264_FAST = ["-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p", "-movflags", "+faststart"]


def out(obj: dict) -> None:
    sys.stdout.write(json.dumps(obj) + "\n")
    sys.stdout.flush()


def ff(args: list) -> None:
    p = subprocess.run(["ffmpeg", "-hide_banner", "-nostdin", "-y", "-loglevel", "error", *map(str, args)],
                       capture_output=True, text=True)
    if p.returncode != 0:
        raise RuntimeError("ffmpeg failed: " + p.stderr.strip()[-800:])


def streams(path: Path) -> tuple[dict, dict | None, dict | None]:
    d = vkit.probe(path)
    v = next((s for s in d.get("streams", []) if s.get("codec_type") == "video" and (s.get("disposition") or {}).get("attached_pic") != 1), None)
    a = next((s for s in d.get("streams", []) if s.get("codec_type") == "audio"), None)
    return d, v, a


def even(n: float) -> int:
    return max(2, int(round(n / 2)) * 2)


# ------------------------------------------------------------------ probe

def waveform(src: Path, dest: Path, duration: float) -> None:
    """Peak envelope (0..1), ~20 values per second, max 2000."""
    n = int(min(2000, max(50, duration * 20)))
    rate = 2000
    p = subprocess.run(["ffmpeg", "-hide_banner", "-nostdin", "-loglevel", "error", "-i", str(src), "-vn", "-ac", "1",
                        "-ar", str(rate), "-f", "s16le", "-"], capture_output=True)
    raw = p.stdout
    samples = len(raw) // 2
    peaks = []
    if samples:
        import array
        arr = array.array("h")
        arr.frombytes(raw[: samples * 2])
        step = max(1, samples // n)
        for i in range(0, samples, step):
            chunk = arr[i:i + step]
            peaks.append(max(abs(min(chunk)), abs(max(chunk))) / 32768 if chunk else 0)
    top = max(peaks) if peaks else 0
    dest.write_text(json.dumps({"samples_per_second": round(len(peaks) / max(duration, 0.001), 3),
                                "peaks": [round(x / top, 3) if top else 0 for x in peaks]}))


def cmd_probe(args) -> None:
    src, outdir = Path(args.src), Path(args.outdir)
    outdir.mkdir(parents=True, exist_ok=True)
    d, v, a = streams(src)
    fmt = d.get("format", {})
    duration = float(fmt.get("duration") or (v or {}).get("duration") or (a or {}).get("duration") or 0)
    result: dict = {"duration": round(duration, 3), "has_audio": a is not None, "format": fmt.get("format_name"),
                    "bitrate": int(fmt.get("bit_rate") or 0) or None, "audio_codec": (a or {}).get("codec_name"),
                    "files": {}}
    is_image = v is not None and (v.get("codec_name") in ("mjpeg", "png", "webp") and duration < 0.1 or fmt.get("format_name") in ("image2", "png_pipe", "jpeg_pipe", "webp_pipe"))

    if v is not None:
        w, h = int(v["width"]), int(v["height"])
        rot = 0
        for sd in v.get("side_data_list") or []:
            if sd.get("rotation") is not None:
                rot = int(round(float(sd["rotation"])))
        if rot % 180 != 0:
            w, h = h, w
        num, _, den = (v.get("avg_frame_rate") or "0/1").partition("/")
        fps = float(num) / float(den or 1) if float(den or 1) else 0.0
        hdr = v.get("color_transfer") in vkit.HDR_TRANSFERS
        result.update({"width": w, "height": h, "fps": round(fps, 3), "video_codec": v.get("codec_name"),
                       "rotation": rot, "hdr": hdr})
        pre = (vkit.TONEMAP + ",") if hdr else ""

        if is_image:
            ff(["-i", src, "-vf", "scale='min(1080,iw)':-2", "-frames:v", "1", "-q:v", "3", outdir / "poster.jpg"])
            result["files"]["poster"] = "poster.jpg"
            result["duration"] = None
            out(result)
            return

        # 540p proxy: browsers and phones can play it (iPhone HEVC/MOV included).
        short = 540
        scale = f"scale={even(short * w / h)}:{short}" if w >= h else f"scale={short}:{even(short * h / w)}"
        audio = ["-c:a", "aac", "-b:a", "96k", "-ac", "2", "-ar", "48000"] if a is not None else ["-an"]
        ff(["-i", src, "-vf", f"{pre}{scale},fps=30", *X264_FAST, "-crf", "28", "-g", "30", *audio, outdir / "proxy.mp4"])
        result["files"]["proxy"] = "proxy.mp4"

        t = min(max(duration * 0.1, 0), max(duration - 0.1, 0))
        ff(["-ss", f"{t:.3f}", "-i", src, "-frames:v", "1", "-vf", f"{pre}{scale}", "-q:v", "4", outdir / "poster.jpg"])
        result["files"]["poster"] = "poster.jpg"

        # Thumbnail sprite for the trim bar: up to 40 frames, 10 per row, 120 px tall.
        count = int(min(40, max(4, math.ceil(duration / 2))))
        interval = duration / count if count else duration
        th = 120
        tw = even(th * w / h)
        cols = min(10, count)
        rows = math.ceil(count / cols)
        ff(["-i", src, "-vf", f"{pre}fps={count / max(duration, 0.1):.5f},scale={tw}:{th},tile={cols}x{rows}",
            "-frames:v", "1", "-q:v", "5", outdir / "thumbs.jpg"])
        result["files"]["thumbs"] = "thumbs.jpg"
        result["thumbs"] = {"cols": cols, "rows": rows, "count": count, "interval": round(interval, 3), "width": tw, "height": th}

    if a is not None and duration > 0:
        waveform(src, outdir / "waveform.json", duration)
        result["files"]["waveform"] = "waveform.json"
        sl = vkit.silence_and_loudness(src)
        result["silence"] = sl
        result["loudness_lufs"] = sl.get("lufs")

    out(result)


# ------------------------------------------------------------- transcribe

def cmd_transcribe(args) -> None:
    src, dest = Path(args.src), Path(args.out)
    with tempfile.TemporaryDirectory() as tmp:
        wav = Path(tmp) / "a.wav"
        ff(["-i", src, "-vn", "-ac", "1", "-ar", "16000", wav])
        key = os.environ.get("ELEVENLABS_API_KEY", "").strip()
        if key:
            d = vkit.transcribe_scribe(wav, key, args.language, os.environ.get("ELEVENLABS_STT_MODEL", "scribe_v1"))
        else:
            d = vkit.transcribe_whisper(wav, args.model, args.language)
    d["text"] = " ".join(w["w"] for w in d.get("words", []))
    dest.write_text(json.dumps(d, ensure_ascii=False))
    out({"language": d.get("language"), "engine": d.get("engine"), "words": len(d.get("words", []))})


# ---------------------------------------------------------------- analyze

def scene_cuts(src: Path, threshold: float) -> list[float]:
    p = subprocess.run(["ffmpeg", "-hide_banner", "-nostdin", "-i", str(src), "-vf",
                        f"scale=320:-2,select='gt(scene,{threshold})',showinfo", "-an", "-f", "null", "-"],
                       capture_output=True, text=True)
    return [round(float(x), 3) for x in re.findall(r"pts_time:([\d.]+)", p.stderr)]


def palette(src: Path, duration: float, k: int = 5) -> list[str]:
    """Simple k-means over pixels of 8 sampled frames."""
    fps = 8 / max(duration, 0.5)
    p = subprocess.run(["ffmpeg", "-hide_banner", "-nostdin", "-loglevel", "error", "-i", str(src), "-vf",
                        f"fps={fps:.5f},scale=40:40", "-frames:v", "8", "-f", "rawvideo", "-pix_fmt", "rgb24", "-"],
                       capture_output=True)
    raw = p.stdout
    px = [(raw[i], raw[i + 1], raw[i + 2]) for i in range(0, len(raw) - 2, 3)][::2]
    if not px:
        return []
    cents = [px[int(i * len(px) / k)] for i in range(k)]
    for _ in range(8):
        groups = [[] for _ in cents]
        for c in px:
            j = min(range(len(cents)), key=lambda n: sum((c[m] - cents[n][m]) ** 2 for m in range(3)))
            groups[j].append(c)
        cents = [tuple(sum(c[m] for c in g) // len(g) for m in range(3)) if g else cents[n] for n, g in enumerate(groups)]
    order = sorted(range(len(cents)), key=lambda n: -len(groups[n]))
    return ["#%02X%02X%02X" % cents[n] for n in order]


def saturation(hex_color: str) -> float:
    r, g, b = (int(hex_color[i:i + 2], 16) / 255 for i in (1, 3, 5))
    _, l, s = colorsys.rgb_to_hls(r, g, b)
    return s * (1 - abs(l - 0.5) * 1.4)


def cmd_analyze(args) -> None:
    src, outdir = Path(args.src), Path(args.outdir)
    outdir.mkdir(parents=True, exist_ok=True)
    d, v, a = streams(src)
    if v is None:
        raise SystemExit("The reference has no video stream.")
    duration = float(d["format"].get("duration") or 0)
    w, h = int(v["width"]), int(v["height"])
    for sd in v.get("side_data_list") or []:
        if sd.get("rotation") is not None and int(round(float(sd["rotation"]))) % 180 != 0:
            w, h = h, w
    ratio = w / h if h else 1
    aspect = "9:16" if ratio < 0.8 else ("1:1" if ratio < 1.25 else "16:9")
    hdr = v.get("color_transfer") in vkit.HDR_TRANSFERS

    cuts = [0.0] + [c for c in scene_cuts(src, args.threshold) if 0.2 < c < duration - 0.2]
    shots = len(cuts)
    avg = duration / shots if shots else duration
    pace = "fast" if avg < 2.5 else ("medium" if avg < 5 else "slow")
    colors = palette(src, duration)

    sl = vkit.silence_and_loudness(src) if a is not None else {"starts": [], "ends": [], "lufs": None}
    silent = sum(max(0.0, e - s) for s, e in zip(sl["starts"], sl["ends"]))
    has_speech = a is not None and silent / max(duration, 0.1) > 0.03
    has_music = a is not None and silent / max(duration, 0.1) < 0.05 and (sl.get("lufs") or -70) > -24

    # Contact sheet: 12 frames, 4x3.
    n = 12
    pre = (vkit.TONEMAP + ",") if hdr else ""
    ff(["-i", src, "-vf", f"{pre}fps={n / max(duration, 0.5):.5f},scale=320:320:force_original_aspect_ratio=decrease,"
        "pad=320:320:(ow-iw)/2:(oh-ih)/2:color=0x101014,tile=4x3:padding=6:color=0x101014", "-frames:v", "1", "-q:v", "4",
        outdir / "contact.jpg"])

    accent = max(colors, key=saturation) if colors else None
    profile = {
        "duration": round(duration, 2), "aspect": aspect, "shot_count": shots, "avg_shot_seconds": round(avg, 2),
        "cut_times": cuts[:200], "pace": pace, "loudness_lufs": sl.get("lufs"),
        "has_speech": has_speech, "has_music": has_music, "palette": colors,
        "suggested": {
            "captions_template": "captions-pop" if pace != "slow" else "captions-clean",
            "captions_props": {},
            "max_segment_seconds": round(min(6.0, max(1.2, avg * 1.15)), 1),
            "accent": accent if accent and saturation(accent) > 0.25 else None,
            "punch_in": pace != "slow",
        },
    }
    out({"style_profile": profile, "files": {"contact_sheet": "contact.jpg"}})


# ------------------------------------------------------------ master/check

def cmd_master(args) -> None:
    src, dest = Path(args.src), Path(args.dest)
    _, v, a = streams(src)
    if a is None:
        ff(["-i", src, "-c", "copy", "-movflags", "+faststart", dest])
        out({"normalized": False, "reason": "no audio"})
        return
    m = vkit.measure_loudnorm(src, args.lufs)
    if m is None:
        ff(["-i", src, "-c", "copy", "-movflags", "+faststart", dest])
        out({"normalized": False, "reason": "silent"})
        return
    af = (f"loudnorm=I={args.lufs}:TP={args.tp - 0.5}:LRA=11:measured_I={m['input_i']}:measured_TP={m['input_tp']}:"
          f"measured_LRA={m['input_lra']}:measured_thresh={m['input_thresh']}:offset={m['target_offset']}:linear=true,"
          f"alimiter=limit={10 ** ((args.tp - 0.7) / 20):.4f}:level=disabled,aresample=48000")  # AAC adds ~0.3-0.5 dB of inter-sample peak
    ff(["-i", src, "-c:v", "copy", "-af", af, "-c:a", "aac", "-b:a", "192k", "-ar", "48000", "-movflags", "+faststart", dest])
    out({"normalized": True, "input_lufs": float(m["input_i"])})


def measure(video: Path) -> dict:
    p = subprocess.run(["ffmpeg", "-hide_banner", "-nostdin", "-nostats", "-i", str(video), "-af",
                        "ebur128=peak=true,silencedetect=n=-45dB:d=2.5", "-vf", "blackdetect=d=0.4:pix_th=0.08",
                        "-f", "null", "-"], capture_output=True, text=True)
    err = p.stderr
    lufs = re.findall(r"I:\s+(-?[\d.]+) LUFS", err)
    tp = re.findall(r"Peak:\s+(-?[\d.]+) dBFS", err)
    blacks = [(float(a), float(b)) for a, b in re.findall(r"black_start:([\d.]+) black_end:([\d.]+)", err)]
    sil = [float(s) for s in re.findall(r"silence_start: ([\d.]+)", err)]
    return {"lufs": float(lufs[-1]) if lufs else None, "true_peak": float(tp[-1]) if tp else None,
            "blacks": blacks, "silences": sil}


def cmd_check(args) -> None:
    v = Path(args.video).resolve()
    info = vkit.clip_info(v)
    m = measure(v)
    dur = info["duration"]
    mid_blacks = [(a, b) for a, b in m["blacks"] if a > 0.5 and b < dur - 0.5]
    long_sil = [s for s in m["silences"] if 0.5 < s < dur - 3]
    warnings = []
    if m["lufs"] is not None and not -15.5 <= m["lufs"] <= -12.5:
        warnings.append(f"loudness {m['lufs']} LUFS (target -14)")
    if m["true_peak"] is not None and m["true_peak"] > -0.9:
        warnings.append(f"true peak {m['true_peak']} dBTP (limit -1)")
    warnings += [f"black frames {a:.1f}-{b:.1f}s" for a, b in mid_blacks]
    warnings += [f"silence of 2.5s+ at {s:.1f}s" for s in long_sil]
    res = {"duration": dur, "width": info["width"], "height": info["height"], "fps": info["fps"],
           "lufs": m["lufs"], "true_peak": m["true_peak"], "black_frames": len(mid_blacks),
           "long_silences": len(long_sil), "warnings": warnings}
    if args.sheet:
        step = max(dur / 12, 0.5)
        times = [min(dur - 0.1, step * k + 0.25) for k in range(int(dur // step) + 1)][:12]
        vkit.tile_frames(v, times, Path(args.sheet).resolve())
        res["sheet"] = args.sheet
    out(res)


def cmd_frames(args) -> None:
    times = [float(t) for t in args.at.split(",") if t.strip()]
    vkit.tile_frames(Path(args.video).resolve(), times, Path(args.dest).resolve())
    out({"image": args.dest, "times": times})


def cmd_conform(args) -> None:
    """H.264 SDR CFR copy a browser can decode (HEVC/HDR/VFR phone footage), short side <= --short."""
    src, dest = Path(args.src), Path(args.dest)
    _, v, a = streams(src)
    if v is None:
        raise SystemExit("No video stream.")
    w, h = int(v["width"]), int(v["height"])
    for sd in v.get("side_data_list") or []:
        if sd.get("rotation") is not None and int(round(float(sd["rotation"]))) % 180 != 0:
            w, h = h, w
    short = min(args.short, min(w, h))
    scale = f"scale={even(short * w / h)}:{short}" if w >= h else f"scale={short}:{even(short * h / w)}"
    pre = (vkit.TONEMAP + ",") if v.get("color_transfer") in vkit.HDR_TRANSFERS else ""
    grade = GRADES.get(args.grade or "none", "")
    post = f",{grade}" if grade else ""
    audio = ["-c:a", "aac", "-b:a", "192k", "-ar", "48000", "-ac", "2"] if a is not None else ["-an"]
    ff(["-i", src, "-vf", f"{pre}{scale},fps=30{post}", *X264_FAST, "-crf", str(args.crf), "-g", "30", *audio, dest])
    out({"width": even(short * w / h) if w >= h else short, "height": short if w >= h else even(short * h / w)})


def cmd_loop(args) -> None:
    """Gallery preview: 4 s, muted, 480 px on the short side, <= 400 KB."""
    src, dest, poster = Path(args.src), Path(args.dest), Path(args.poster)
    _, v, _ = streams(src)
    w, h = int(v["width"]), int(v["height"])
    scale = f"scale={even(480 * w / h)}:480" if w >= h else f"scale=480:{even(480 * h / w)}"
    size = 0
    for crf in (30, 34, 38, 42):
        ff(["-t", "4", "-i", src, "-an", "-vf", f"{scale},fps=30", *X264_FAST, "-crf", str(crf), "-maxrate", "700k",
            "-bufsize", "700k", dest])
        size = dest.stat().st_size
        if size <= 400_000:
            break
    # 2 s: entrances have settled and transition demos are mid-transition.
    ff(["-ss", "2.0", "-i", src, "-frames:v", "1", "-vf", scale, "-q:v", "5", poster])
    out({"bytes": size})


def main() -> None:
    ap = argparse.ArgumentParser(prog="studio_tools")
    sub = ap.add_subparsers(dest="cmd", required=True)
    p = sub.add_parser("probe"); p.add_argument("src"); p.add_argument("outdir"); p.set_defaults(fn=cmd_probe)
    p = sub.add_parser("transcribe"); p.add_argument("src"); p.add_argument("out")
    p.add_argument("--model", default=os.environ.get("STUDIO_WHISPER_MODEL", "small")); p.add_argument("--language", default=None)
    p.set_defaults(fn=cmd_transcribe)
    p = sub.add_parser("analyze"); p.add_argument("src"); p.add_argument("outdir"); p.add_argument("--threshold", type=float, default=0.3)
    p.set_defaults(fn=cmd_analyze)
    p = sub.add_parser("master"); p.add_argument("src"); p.add_argument("dest"); p.add_argument("--lufs", type=float, default=-14.0)
    p.add_argument("--tp", type=float, default=-1.0); p.set_defaults(fn=cmd_master)
    p = sub.add_parser("check"); p.add_argument("video"); p.add_argument("--sheet", default=None); p.set_defaults(fn=cmd_check)
    p = sub.add_parser("frames"); p.add_argument("video"); p.add_argument("dest"); p.add_argument("--at", required=True)
    p.set_defaults(fn=cmd_frames)
    p = sub.add_parser("conform"); p.add_argument("src"); p.add_argument("dest"); p.add_argument("--short", type=int, default=1080)
    p.add_argument("--crf", type=int, default=17); p.add_argument("--grade", default="none", choices=sorted(GRADES))
    p.set_defaults(fn=cmd_conform)
    p = sub.add_parser("loop"); p.add_argument("src"); p.add_argument("dest"); p.add_argument("poster"); p.set_defaults(fn=cmd_loop)
    args = ap.parse_args()
    try:
        args.fn(args)
    except Exception as e:  # one JSON error line for the worker
        sys.stderr.write(f"{type(e).__name__}: {e}\n")
        out({"error": str(e)})
        sys.exit(1)


if __name__ == "__main__":
    main()
