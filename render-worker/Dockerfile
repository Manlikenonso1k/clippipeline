# ClipPipeline Studio render worker. Build from the repository root:
#   docker build -f render-worker/Dockerfile -t clippipeline-render .
#   docker run -d --restart unless-stopped --env-file render-worker/.env \
#     -v clippipeline-cache:/var/cache/clippipeline-render --name clippipeline-render clippipeline-render
FROM node:22-bookworm-slim

# ffmpeg (with zscale for HDR tone-mapping), Python for vkit/faster-whisper, and the
# shared libraries Remotion's Chrome Headless Shell needs.
RUN apt-get update && apt-get install -y --no-install-recommends \
      ffmpeg python3 python3-venv ca-certificates fonts-dejavu-core \
      libnss3 libdbus-1-3 libatk1.0-0 libgbm1 libasound2 libxrandr2 libxkbcommon0 libxfixes3 \
      libxcomposite1 libxdamage1 libatk-bridge2.0-0 libpango-1.0-0 libcairo2 libcups2 \
    && rm -rf /var/lib/apt/lists/*

# faster-whisper in a venv (model downloads on first transcription and is cached in the volume).
RUN python3 -m venv /opt/venv && /opt/venv/bin/pip install --no-cache-dir faster-whisper==1.1.1 yt-dlp
ENV PATH="/opt/venv/bin:$PATH" PYTHON=/opt/venv/bin/python HF_HOME=/var/cache/clippipeline-render/hf

WORKDIR /app
COPY motion/package.json motion/package-lock.json* motion/
RUN cd motion && npm install --no-audit --no-fund
COPY render-worker/package.json render-worker/package-lock.json* render-worker/
RUN cd render-worker && npm install --no-audit --no-fund
COPY motion motion
COPY render-worker render-worker

# Download Remotion's browser at build time.
RUN cd render-worker && npx remotion browser ensure || true

ENV WORKER_CACHE_DIR=/var/cache/clippipeline-render NODE_ENV=production
VOLUME /var/cache/clippipeline-render
WORKDIR /app/render-worker
CMD ["npx", "tsx", "src/index.ts"]
