#!/usr/bin/env python3
"""music_gen: original, royalty-free background tracks for ClipPipeline Studio.

Every track is synthesised from scratch (no samples), so creators can post with it anywhere.
Each preset writes a seamless loop (a whole number of bars, at least --seconds long).

  python music_gen.py --list
  python music_gen.py neon-drive out.wav [--seconds 60]

Needs numpy only. Convert to mp3 with ffmpeg afterwards.
"""
from __future__ import annotations

import argparse
import json
import sys
import wave

import numpy as np

SR = 44100


def midi(n: float) -> float:
    return 440.0 * 2 ** ((n - 69) / 12)


def lowpass(x: np.ndarray, cutoff: float, res: float = 0.0, order: int = 8) -> np.ndarray:
    X = np.fft.rfft(x)
    f = np.fft.rfftfreq(len(x), 1 / SR)
    g = 1 / np.sqrt(1 + (f / cutoff) ** order)
    if res:
        g = g * (1 + res * np.exp(-((f - cutoff) / (cutoff * 0.25)) ** 2))
    return np.fft.irfft(X * g, len(x))


def highpass(x: np.ndarray, cutoff: float) -> np.ndarray:
    X = np.fft.rfft(x)
    f = np.fft.rfftfreq(len(x), 1 / SR)
    return np.fft.irfft(X * (1 - 1 / np.sqrt(1 + (f / cutoff) ** 8)), len(x))


def bandpass(x: np.ndarray, lo: float, hi: float) -> np.ndarray:
    return highpass(lowpass(x, hi), lo)


def saw(freq: float, t: np.ndarray, detune: float = 0.0) -> np.ndarray:
    ph = freq * (1 + detune) * t
    return 2 * (ph - np.floor(ph + 0.5))


def tri(freq: float, t: np.ndarray) -> np.ndarray:
    return 2 * np.abs(saw(freq, t)) - 1


def env(n: int, a: float, d: float, s: float = 0.0, curve: float = 4.0, r: float = 0.0) -> np.ndarray:
    tt = np.arange(n) / SR
    att = np.clip(tt / max(a, 1e-4), 0, 1)
    dec = s + (1 - s) * np.exp(-curve * np.clip(tt - a, 0, None) / max(d, 1e-4))
    out = att * dec
    if r > 0:
        out *= np.clip((tt[-1] - tt) / r, 0, 1)
    return out


class Mix:
    def __init__(self, seconds: float, bpm: float, swing: float = 0.0):
        self.bpm = bpm
        self.beat = 60.0 / bpm
        self.bar = 4 * self.beat
        self.bars = int(np.ceil(seconds / self.bar))
        self.dur = self.bars * self.bar
        self.n = int(round(self.dur * SR))
        self.swing = swing
        self.rng = np.random.default_rng(11)
        self.stems: dict[str, np.ndarray] = {}
        self.kicks: list[float] = []

    def stem(self, name: str) -> np.ndarray:
        if name not in self.stems:
            self.stems[name] = np.zeros(self.n)
        return self.stems[name]

    def place(self, name: str, sig: np.ndarray, at: float) -> None:
        buf = self.stem(name)
        i = int(round(at * SR)) % self.n
        j = min(self.n, i + len(sig))
        buf[i:j] += sig[: j - i]
        rest = len(sig) - (j - i)
        if rest > 0:  # wrap around so the loop stays seamless
            buf[:rest] += sig[j - i:]

    def step(self, bar: int, sixteenth: int) -> float:
        t = bar * self.bar + sixteenth * self.beat / 4
        if self.swing and sixteenth % 2 == 1:
            t += self.swing * self.beat / 4
        return t

    # ---- instruments ----
    def kick(self, at: float, gain: float = 0.95, low: float = 45, punch: float = 95, decay: float = 7.5) -> None:
        n = int(0.45 * SR)
        tt = np.arange(n) / SR
        f = low + punch * np.exp(-tt * 28)
        k = np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-tt * decay)
        k += 0.2 * self.rng.standard_normal(n) * np.exp(-tt * 140)
        self.place('drums', gain * k, at)
        self.kicks.append(at)

    def clap(self, at: float, gain: float = 0.5) -> None:
        n = int(0.3 * SR)
        tt = np.arange(n) / SR
        noise = self.rng.standard_normal(n)
        c = noise * (np.exp(-tt * 22) + 0.6 * np.exp(-((tt - 0.012) * 300) ** 2) + 0.5 * np.exp(-((tt - 0.024) * 300) ** 2))
        c = bandpass(c, 900, 5000) + 0.4 * np.sin(2 * np.pi * 190 * tt) * np.exp(-tt * 30)
        self.place('drums', gain * c, at)

    def snare(self, at: float, gain: float = 0.45, soft: bool = False) -> None:
        n = int(0.25 * SR)
        tt = np.arange(n) / SR
        s = bandpass(self.rng.standard_normal(n), 400 if soft else 1200, 4000 if soft else 8000) * np.exp(-tt * (18 if soft else 25))
        s += 0.5 * np.sin(2 * np.pi * 185 * tt) * np.exp(-tt * 35)
        self.place('drums', gain * s, at)

    def hat(self, at: float, gain: float = 0.18, open_: bool = False) -> None:
        n = int(0.15 * SR)
        tt = np.arange(n) / SR
        h = highpass(self.rng.standard_normal(n), 7000) * np.exp(-tt * (22 if open_ else 65))
        self.place('drums', gain * h, at)

    def shaker(self, at: float, gain: float = 0.12) -> None:
        n = int(0.09 * SR)
        tt = np.arange(n) / SR
        s = bandpass(self.rng.standard_normal(n), 3500, 11000) * np.sin(np.pi * np.clip(tt / 0.09, 0, 1)) ** 2
        self.place('drums', gain * s, at)

    def rim(self, at: float, gain: float = 0.25) -> None:
        n = int(0.06 * SR)
        tt = np.arange(n) / SR
        r = (np.sin(2 * np.pi * 1700 * tt) + 0.6 * np.sin(2 * np.pi * 820 * tt)) * np.exp(-tt * 90)
        self.place('drums', gain * r, at)

    def saw_bass(self, at: float, note: int, length: float, gain: float = 1.0) -> None:
        n = int(length * SR)
        tt = np.arange(n) / SR
        s = saw(midi(note), tt) + 0.5 * saw(midi(note), tt, 0.006)
        self.place('bass', gain * s * env(n, 0.004, 0.18, 0.35, 3, r=0.01), at)

    def log_drum(self, at: float, note: int, length: float, gain: float = 1.0) -> None:
        """Amapiano-style 'log drum': pitched sine with a downward glide and a woody overtone."""
        n = int(length * SR)
        tt = np.arange(n) / SR
        f = midi(note) * (1 + 0.6 * np.exp(-tt * 35))
        ph = 2 * np.pi * np.cumsum(f) / SR
        s = np.sin(ph) + 0.35 * np.sin(2 * ph + 0.5) + 0.12 * np.sin(3 * ph)
        s = np.tanh(1.8 * s)
        self.place('bass', gain * s * env(n, 0.003, length * 0.9, 0.25, 2.2, r=0.02), at)

    def pad(self, at: float, notes: list[int], length: float, gain: float = 1.0) -> None:
        n = int(length * SR)
        tt = np.arange(n) / SR
        l = sum(saw(midi(m), tt, -0.004) + 0.6 * saw(midi(m + 12), tt, 0.003) for m in notes)
        r = sum(saw(midi(m), tt, 0.005) + 0.6 * saw(midi(m + 12), tt, -0.002) for m in notes)
        shape = env(n, 0.35, length * 0.8, 0.75, 1.5, r=0.08)
        self.place('pad_l', gain * l * shape, at)
        self.place('pad_r', gain * r * shape, at)

    def keys(self, at: float, notes: list[int], length: float, gain: float = 1.0) -> None:
        """Soft electric-piano chord (two-operator FM)."""
        n = int(length * SR)
        tt = np.arange(n) / SR
        out = np.zeros(n)
        for m in notes:
            f = midi(m)
            mod = np.sin(2 * np.pi * f * 2 * tt) * 1.4 * np.exp(-tt * 6)
            out += np.sin(2 * np.pi * f * tt + mod)
        self.place('keys', gain * out * env(n, 0.005, length, 0.0, 2.6, r=0.05), at)

    def pluck(self, at: float, note: int, length: float, gain: float = 1.0) -> None:
        n = int(length * SR)
        tt = np.arange(n) / SR
        f = midi(note)
        s = np.sign(np.sin(2 * np.pi * f * tt)) * 0.5 + saw(f, tt) * 0.5
        self.place('lead', gain * s * env(n, 0.002, 0.09, 0.0, 5, r=0.005), at)

    def render(self, levels: dict[str, float], filters: dict[str, float], pump: float = 0.75, vinyl: float = 0.0) -> np.ndarray:
        st = {k: v.copy() for k, v in self.stems.items()}
        for k, cut in filters.items():
            if k in st:
                st[k] = lowpass(st[k], cut, res=0.5 if k == 'bass' else 0.0)
        # sidechain from the kick
        env_ = np.ones(self.n)
        for kt in self.kicks:
            i = int(round(kt * SR)) % self.n
            m = int(0.32 * SR)
            tt = np.arange(m) / SR
            shape = (1 - pump) + pump * (1 - np.exp(-tt * 9))
            j = min(self.n, i + m)
            env_[i:j] = np.minimum(env_[i:j], shape[: j - i])
        for k in ('bass', 'pad_l', 'pad_r', 'keys'):
            if k in st:
                st[k] *= env_
        lead = st.get('lead', np.zeros(self.n))
        delay = int(self.beat * 0.75 * SR)
        lead_l = lead + 0.35 * np.roll(lead, delay)
        lead_r = 0.8 * lead + 0.45 * np.roll(lead, delay // 2)
        g = lambda k: levels.get(k, 0.0) * st.get(k, np.zeros(self.n))  # noqa: E731
        L = g('drums') + g('bass') + g('pad_l') + g('keys') + levels.get('lead', 0) * lead_l
        R = g('drums') + g('bass') + g('pad_r') + g('keys') * 0.9 + levels.get('lead', 0) * lead_r
        if vinyl:
            crackle = (self.rng.random(self.n) > 0.9993) * self.rng.standard_normal(self.n) * 0.6
            hiss = highpass(self.rng.standard_normal(self.n), 4000) * 0.02
            L += vinyl * (crackle + hiss)
            R += vinyl * (np.roll(crackle, 37) + hiss)
        stereo = np.stack([L, R], 1)
        stereo = np.tanh(stereo * 1.3) / np.tanh(1.3)
        stereo *= 10 ** (-1.5 / 20) / np.max(np.abs(stereo))
        # 5 ms edge ramps (the loop wraps cleanly; this only avoids a click at the very first sample)
        e = int(0.005 * SR)
        stereo[:e] *= np.linspace(0.6, 1, e)[:, None]
        return stereo


# ------------------------------------------------------------------ presets

def neon_drive(seconds: float) -> np.ndarray:
    """Synthwave, 105 BPM, A minor: Am - F - C - G."""
    m = Mix(seconds, 105)
    chords = [(57, [57, 60, 64]), (53, [53, 57, 60]), (48, [52, 55, 60]), (55, [55, 59, 62])]
    for b in range(m.bars):
        root, tri_ = chords[b % 4]
        seq = tri_ + [tri_[0] + 12, tri_[2], tri_[1] + 12, tri_[0] + 12, tri_[1]]
        for s in range(16):
            t = m.step(b, s)
            if s % 4 == 0:
                m.kick(t)
            if s in (4, 12):
                m.clap(t)
            if s % 2 == 0:
                m.hat(t, 0.13 if s % 4 == 0 else 0.2, open_=s % 4 == 2)
            if s % 2 == 0:
                m.saw_bass(t, root - 12 + (12 if s == 14 else 0), m.beat / 2)
            if b % 8 >= 4:
                m.pluck(t, seq[s % len(seq)] + 12, m.beat / 4)
        m.pad(b * m.bar, tri_, m.bar)
    return m.render({'drums': 1.0, 'bass': 0.5, 'pad_l': 0.07, 'pad_r': 0.07, 'lead': 0.15}, {'bass': 420, 'pad_l': 1800, 'pad_r': 1800, 'lead': 3200})


def night_run(seconds: float) -> np.ndarray:
    """Dark driving synth, 122 BPM, D minor: Dm - Bb - F - C, rolling 16th bass."""
    m = Mix(seconds, 122)
    chords = [(50, [50, 53, 57]), (46, [46, 50, 53]), (53, [53, 57, 60]), (48, [48, 52, 55])]
    for b in range(m.bars):
        root, tri_ = chords[b % 4]
        for s in range(16):
            t = m.step(b, s)
            if s % 4 == 0:
                m.kick(t, 1.0, low=48, punch=110, decay=8.5)
            if s in (4, 12):
                m.snare(t, 0.42)
            m.hat(t, 0.16 if s % 2 else 0.08, open_=s % 4 == 2)
            m.saw_bass(t, root - 12 + (0 if s % 4 else 0) + (12 if s % 8 == 6 else 0), m.beat / 4 * 0.9, 0.9)
        m.pad(b * m.bar, tri_, m.bar, 0.9)
        if b % 2 == 1:
            for s, off in ((0, 12), (3, 19), (6, 15), (10, 12), (12, 24)):
                m.pluck(m.step(b, s), tri_[0] + off, m.beat / 2)
    return m.render({'drums': 1.0, 'bass': 0.42, 'pad_l': 0.055, 'pad_r': 0.055, 'lead': 0.12}, {'bass': 520, 'pad_l': 1400, 'pad_r': 1400, 'lead': 2600}, pump=0.85)


def lagos_glow(seconds: float) -> np.ndarray:
    """Amapiano-style groove, 112 BPM: log-drum bass, shakers, soft keys. Am7 - Dm7 - G - Cmaj7."""
    m = Mix(seconds, 112, swing=0.12)
    chords = [(45, [57, 60, 64, 67]), (50, [57, 60, 62, 65]), (43, [55, 59, 62, 66]), (48, [55, 59, 60, 64])]
    log_pattern = [0, 3, 6, 10, 11, 14]  # syncopated sixteenths
    for b in range(m.bars):
        root, notes = chords[b % 4]
        for s in range(16):
            t = m.step(b, s)
            if s % 4 == 0:
                m.kick(t, 0.75, low=50, punch=70, decay=9)
            m.shaker(t, 0.13 if s % 2 else 0.07)
            if s in (4, 12):
                m.rim(t, 0.22)
            if s in (7, 15) and b % 2 == 1:
                m.rim(t, 0.12)
            if s in log_pattern:
                note = root + (12 if s in (10, 14) else 0) + (7 if s == 11 else 0)
                m.log_drum(t, note, m.beat * 0.6, 0.9)
        for s in (0, 6, 10):
            m.keys(m.step(b, s), notes, m.beat * 1.4, 0.32)
        m.pad(b * m.bar, notes[:3], m.bar, 0.5)
    return m.render({'drums': 1.0, 'bass': 0.62, 'pad_l': 0.03, 'pad_r': 0.03, 'keys': 0.2}, {'bass': 900, 'pad_l': 1200, 'pad_r': 1200, 'keys': 4500}, pump=0.45)


def chill_tape(seconds: float) -> np.ndarray:
    """Lo-fi hip hop, 84 BPM, swung: Fmaj7 - Em7 - Dm7 - Cmaj7 on electric piano, vinyl hiss."""
    m = Mix(seconds, 84, swing=0.3)
    chords = [(41, [53, 57, 60, 64]), (40, [52, 55, 59, 62]), (38, [50, 53, 57, 60]), (36, [48, 52, 55, 59])]
    for b in range(m.bars):
        root, notes = chords[b % 4]
        for s in range(16):
            t = m.step(b, s)
            if s in (0, 7, 10):
                m.kick(t, 0.7, low=50, punch=60, decay=10)
            if s in (4, 12):
                m.snare(t, 0.32, soft=True)
            if s % 2 == 0 or s in (7, 15):
                m.hat(t, 0.08 if s % 4 else 0.05)
        m.keys(m.step(b, 0), notes, m.bar * 0.55, 0.42)
        m.keys(m.step(b, 10), notes, m.bar * 0.4, 0.3)
        for s, dur in ((0, 6), (10, 6)):
            m.saw_bass(m.step(b, s), root, m.beat * dur / 4, 0.8)
    return m.render({'drums': 0.9, 'bass': 0.32, 'keys': 0.26}, {'bass': 260, 'keys': 2600, 'drums': 9000}, pump=0.3, vinyl=0.5)


PRESETS = {
    'neon-drive': {'fn': neon_drive, 'name': 'Neon Drive', 'mood': 'Synthwave · night city', 'bpm': 105},
    'night-run': {'fn': night_run, 'name': 'Night Run', 'mood': 'Dark · driving', 'bpm': 122},
    'lagos-glow': {'fn': lagos_glow, 'name': 'Lagos Glow', 'mood': 'Amapiano groove', 'bpm': 112},
    'chill-tape': {'fn': chill_tape, 'name': 'Chill Tape', 'mood': 'Lo-fi · relaxed', 'bpm': 84},
}


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument('preset', nargs='?')
    ap.add_argument('out', nargs='?')
    ap.add_argument('--seconds', type=float, default=60)
    ap.add_argument('--list', action='store_true')
    a = ap.parse_args()
    if a.list or not a.preset:
        print(json.dumps({k: {x: v[x] for x in ('name', 'mood', 'bpm')} for k, v in PRESETS.items()}))
        return
    stereo = PRESETS[a.preset]['fn'](a.seconds)
    pcm = (stereo * 32767).astype(np.int16)
    with wave.open(a.out, 'wb') as w:
        w.setnchannels(2)
        w.setsampwidth(2)
        w.setframerate(SR)
        w.writeframes(pcm.tobytes())
    print(json.dumps({'preset': a.preset, 'seconds': round(len(pcm) / SR, 3)}))


if __name__ == '__main__':
    sys.exit(main())
