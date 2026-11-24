#!/bin/sh
# Builds the NatuFitness delivery images from a copy of the live store.
#
#   sh deploy/build-images.sh BUILD_DIR [VERSION]
#
# BUILD_DIR must contain (exported read-only from the live server, kept OUT of git):
#   db.sql.gz     mysqldump of the store database
#   html.tar.gz   tar of /var/www/html of the prestashop container (without var/cache, var/logs)
# Produces images natufitness-web:VERSION and natufitness-db:VERSION for linux/amd64
# (servers) and saves them as BUILD_DIR/../natufitness-images-VERSION.tar.gz,
# plus the delivery folder (compose, .env.example, Caddyfile, guide).
# Set PLATFORM=linux/arm64 to build for an Apple Silicon Mac (local tests).
set -eu

BUILD_DIR=${1:?usage: sh deploy/build-images.sh BUILD_DIR [VERSION]}
VERSION=${2:-1.0}
PLATFORM=${PLATFORM:-linux/amd64}
REPO_DIR=$(cd "$(dirname "$0")/.." && pwd)
BUILD_DIR=$(cd "$BUILD_DIR" && pwd)
OUT_DIR=$(dirname "$BUILD_DIR")

for f in db.sql.gz html.tar.gz; do
  [ -f "$BUILD_DIR/$f" ] || { echo "FAILED: $BUILD_DIR/$f missing" >&2; exit 1; }
done
gunzip -c "$BUILD_DIR/db.sql.gz" | tail -1 | grep -q 'Dump completed' || { echo "FAILED: incomplete dump" >&2; exit 1; }

echo "==> Preparing build context"
cp "$REPO_DIR/deploy/image/Dockerfile.web" "$REPO_DIR/deploy/image/Dockerfile.db" "$REPO_DIR/deploy/image/init-natufitness.sh" "$BUILD_DIR/"
rm -rf "$BUILD_DIR/scripts" && mkdir -p "$BUILD_DIR/scripts"
for f in "$REPO_DIR"/scripts/*.php "$REPO_DIR"/scripts/*.sh; do cp "$f" "$BUILD_DIR/scripts/"; done

echo "==> Building natufitness-db:$VERSION ($PLATFORM)"
docker build --platform "$PLATFORM" -f "$BUILD_DIR/Dockerfile.db" -t "natufitness-db:$VERSION" "$BUILD_DIR"
echo "==> Building natufitness-web:$VERSION ($PLATFORM)"
docker build --platform "$PLATFORM" -f "$BUILD_DIR/Dockerfile.web" -t "natufitness-web:$VERSION" "$BUILD_DIR"

if [ "$PLATFORM" = "linux/amd64" ]; then
  echo "==> Saving images"
  docker save "natufitness-web:$VERSION" "natufitness-db:$VERSION" | gzip -6 > "$OUT_DIR/natufitness-images-$VERSION.tar.gz"
  chmod 600 "$OUT_DIR/natufitness-images-$VERSION.tar.gz"
  cp "$REPO_DIR/deploy/image/docker-compose.yml" "$REPO_DIR/deploy/image/.env.example" "$REPO_DIR/deploy/image/Caddyfile" "$OUT_DIR/"
  [ -f "$REPO_DIR/docs/entrega-docker.md" ] && cp "$REPO_DIR/docs/entrega-docker.md" "$OUT_DIR/LEEME.md"
  ls -lh "$OUT_DIR"
fi
echo "OK"
