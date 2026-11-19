#!/usr/bin/env bash
# One-command setup (and update) of the Studio render worker on a Linux VPS, e.g. Hostinger KVM 2.
#
# First time, as root on the VPS:
#   curl -fsSL https://raw.githubusercontent.com/Manlikenonso1k/clippipeline/main/render-worker/setup-vps.sh -o setup-vps.sh
#   bash setup-vps.sh            # asks for the worker token (php artisan studio:worker-token)
# Update later (also what CI/CD runs): bash /opt/clippipeline/render-worker/setup-vps.sh
#
# Safe to re-run: it pulls the latest main, rebuilds the image and restarts the worker. Running
# jobs get up to 15 minutes to finish before the old container stops.
set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/Manlikenonso1k/clippipeline.git}"
BRANCH="${BRANCH:-main}"
DIR="${DIR:-/opt/clippipeline}"
NAME=clippipeline-render
ENV_FILE="$DIR/render-worker/.env"

[ "$(id -u)" -eq 0 ] || { echo "Run as root (or with sudo)."; exit 1; }

echo "==> 1/5 Docker and git"
if ! command -v docker >/dev/null 2>&1; then
  apt-get update -y
  apt-get install -y docker.io git
  systemctl enable --now docker
fi
command -v git >/dev/null 2>&1 || apt-get install -y git

echo "==> 2/5 Swap (renders can briefly need more than 8 GB)"
if ! swapon --show | grep -q .; then
  fallocate -l 4G /swapfile && chmod 600 /swapfile && mkswap /swapfile >/dev/null && swapon /swapfile
  grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
  echo "    added 4 GB swap"
fi

echo "==> 3/5 Code ($BRANCH)"
if [ -d "$DIR/.git" ]; then
  git -C "$DIR" fetch --prune origin "$BRANCH"
  git -C "$DIR" checkout -q "$BRANCH"
  git -C "$DIR" pull --ff-only origin "$BRANCH"
else
  git clone --branch "$BRANCH" "$REPO_URL" "$DIR"
fi
echo "    at $(git -C "$DIR" log --oneline -1)"

echo "==> 4/5 Settings ($ENV_FILE)"
if [ ! -f "$ENV_FILE" ]; then
  TOKEN="${CLIPPIPELINE_WORKER_TOKEN:-}"
  if [ -z "$TOKEN" ]; then
    echo "    Paste the worker token from: php artisan studio:worker-token (input hidden)"
    read -rsp "    Token: " TOKEN; echo
  fi
  [ -n "$TOKEN" ] || { echo "No token given."; exit 1; }
  CPUS=$(nproc)
  sed -e "s#^CLIPPIPELINE_WORKER_TOKEN=.*#CLIPPIPELINE_WORKER_TOKEN=$TOKEN#" \
      -e "s#^WORKER_ID=.*#WORKER_ID=$(hostname)#" \
      -e "s#^REMOTION_CONCURRENCY=.*#REMOTION_CONCURRENCY=$CPUS#" \
      -e "s#^PYTHON=.*#PYTHON=/opt/venv/bin/python#" \
      "$DIR/render-worker/.env.example" > "$ENV_FILE"
  chmod 600 "$ENV_FILE"
  echo "    written (edit it any time, then re-run this script)"
else
  echo "    keeping the existing settings"
fi

echo "==> 5/5 Build and (re)start the worker"
docker build -f "$DIR/render-worker/Dockerfile" -t "$NAME" "$DIR"
if docker ps -a --format '{{.Names}}' | grep -qx "$NAME"; then
  docker stop -t 900 "$NAME" >/dev/null || true
  docker rm "$NAME" >/dev/null
fi
docker run -d --name "$NAME" --restart unless-stopped --stop-timeout 900 \
  --env-file "$ENV_FILE" -v clippipeline-cache:/var/cache/clippipeline-render \
  --shm-size=1g "$NAME" >/dev/null
docker image prune -f >/dev/null || true

sleep 8
echo "==> Worker logs (follow with: docker logs -f $NAME)"
docker logs --tail 20 "$NAME"
